package panel

import (
	"bytes"
	"context"
	"encoding/base64"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log"
	"net/http"
	"net/url"
	"strconv"
	"strings"
	"sync"
	"time"
)

/*
Reverse mode: the node asks the panel for work.

A node behind NAT has nothing open. Rather than invent a protocol for that
case, this carries the panel's ordinary HTTP request in and out over the
daemon's own dial-out, and serves it against the SAME mux that a directly
reachable node exposes on its port. Every capability the daemon has therefore
works over the tunnel the day it is written, and neither side gets a second
implementation to keep in step with the first.

Nothing here is permitted to be fatal, in keeping with the rest of this
package: a panel that has gone away must not stop the game servers already
running on this node.
*/

// CallTimeout bounds one call end to end, unless the panel says otherwise in
// expires_in. An install legitimately runs for hours, so this is a ceiling for
// the ordinary case rather than a promise about all of them.
const CallTimeout = 2 * time.Minute

// maxConcurrent stops a node from being talked into running unbounded work at
// once. Eight is more than a panel ever has in flight for one node, and a
// bounded number means a slow install cannot starve a Stop.
const maxConcurrent = 8

// Call is one piece of parked work.
type Call struct {
	UUID      string   `json:"uuid"`
	Method    string   `json:"method"`
	Path      string   `json:"path"`
	Query     QueryMap `json:"query"`
	Body      string   `json:"body"`
	Encoding  string   `json:"encoding"`
	ExpiresIn int      `json:"expires_in"`
}

// QueryMap is the call's query parameters, which are text by definition and
// were not always sent that way.
//
// A panel that sent memory and port as JSON numbers made this whole decode fail
// and every call after the first look like an unreachable node, which is a
// miserable thing to debug from the panel side because the node was fine and
// saying so. The panel sends strings now; this decodes either, so a node
// running ahead of its panel keeps working instead of going quiet.
type QueryMap map[string]string

func (q *QueryMap) UnmarshalJSON(raw []byte) error {
	decoder := json.NewDecoder(bytes.NewReader(raw))
	// Numbers stay literal. Through `any` they would become float64 and 1000000
	// would reach the node as "1e+06".
	decoder.UseNumber()

	var loose map[string]any
	if err := decoder.Decode(&loose); err != nil {
		return err
	}

	out := make(QueryMap, len(loose))
	for key, value := range loose {
		switch v := value.(type) {
		case nil:
			out[key] = ""
		case string:
			out[key] = v
		case bool:
			out[key] = strconv.FormatBool(v)
		case json.Number:
			out[key] = v.String()
		default:
			out[key] = fmt.Sprint(v)
		}
	}
	*q = out

	return nil
}

// NextCall holds a long poll open at the panel until there is work or the
// panel gives up waiting. A 204 is the ordinary answer and is not an error.
func (c *Client) NextCall(ctx context.Context, wait time.Duration) (*Call, error) {
	req, err := http.NewRequestWithContext(ctx, http.MethodGet,
		fmt.Sprintf("%s/api/node/calls?wait=%d", c.base, int(wait.Seconds())), nil)
	if err != nil {
		return nil, err
	}
	req.Header.Set("Accept", "application/json")
	if c.token != "" {
		req.Header.Set("Authorization", "Bearer "+c.token)
	}

	// The shared client has a 15s timeout, which is shorter than the hold this
	// request is asking the panel for. Its own client, sized to the wait plus
	// slack, rather than making every other call wait longer.
	client := &http.Client{Timeout: wait + 30*time.Second}

	res, err := client.Do(req)
	if err != nil {
		return nil, err
	}
	defer res.Body.Close()

	raw, _ := io.ReadAll(io.LimitReader(res.Body, 32<<20))

	if res.StatusCode == http.StatusNoContent {
		return nil, nil
	}
	if res.StatusCode == http.StatusUnauthorized {
		return nil, fmt.Errorf("%w: %s", ErrUnauthorized, message(raw))
	}
	if res.StatusCode >= 400 {
		return nil, fmt.Errorf("panel /api/node/calls: %d %s", res.StatusCode, message(raw))
	}

	var payload struct {
		Call *Call `json:"call"`
	}
	if err := json.Unmarshal(raw, &payload); err != nil {
		return nil, err
	}

	return payload.Call, nil
}

// ReportProgress sends lines produced while a call is still running.
func (c *Client) ReportProgress(ctx context.Context, uuid string, lines []ProgressLine) error {
	return c.post(ctx, "/api/node/calls/"+uuid+"/progress", struct {
		Lines []ProgressLine `json:"lines"`
	}{lines}, nil)
}

// ProgressLine is one SSE event on its way back to the panel.
type ProgressLine struct {
	Event string `json:"event"`
	Data  string `json:"data"`
}

// ReportResult answers a call.
func (c *Client) ReportResult(ctx context.Context, uuid string, status int, body []byte) error {
	return c.post(ctx, "/api/node/calls/"+uuid+"/result", struct {
		Status   int    `json:"status"`
		Body     string `json:"body"`
		Encoding string `json:"encoding"`
	}{
		Status: status,
		// Always base64. A file read comes back as raw bytes and a JSON string
		// cannot hold arbitrary ones; encoding only the bodies that look
		// binary would mean guessing, and guessing wrong corrupts a file.
		Body:     base64.StdEncoding.EncodeToString(body),
		Encoding: "base64",
	}, nil)
}

// ServeCalls polls for work and serves each call against handler until ctx is
// cancelled. Started only for a node the panel says is in reverse mode.
func ServeCalls(ctx context.Context, c *Client, handler http.Handler, token func() string, hold time.Duration) {
	if hold <= 0 {
		hold = 25 * time.Second
	}

	slots := make(chan struct{}, maxConcurrent)
	var wg sync.WaitGroup
	failures := 0

	log.Printf("reverse mode: polling %s for work", c.base)

	for {
		if ctx.Err() != nil {
			break
		}

		call, err := c.NextCall(ctx, hold)
		if err != nil {
			if ctx.Err() != nil {
				break
			}
			failures++
			log.Printf("reverse poll failed (%d in a row): %v", failures, err)

			// Reuses the heartbeat's backoff rather than inventing a second
			// one, so a panel outage costs the same handful of requests here.
			select {
			case <-ctx.Done():
			case <-time.After(Jitter(Backoff(time.Second, failures))):
			}

			continue
		}

		if failures > 0 {
			log.Printf("reverse poll recovered after %d failed %s", failures, plural("attempt", failures))
			failures = 0
		}

		if call == nil {
			continue // The hold expired with nothing to do. Normal.
		}

		// Straight back to polling. A long install must not stop this node from
		// being told to stop a different server.
		slots <- struct{}{}
		wg.Add(1)
		go func(call *Call) {
			defer wg.Done()
			defer func() { <-slots }()
			serve(ctx, c, handler, token, call)
		}(call)
	}

	wg.Wait()
}

// serve rebuilds the panel's request, runs it against the daemon's own handler
// and reports what came back.
func serve(ctx context.Context, c *Client, handler http.Handler, token func() string, call *Call) {
	timeout := CallTimeout
	if call.ExpiresIn > 0 {
		timeout = time.Duration(call.ExpiresIn) * time.Second
	}

	ctx, cancel := context.WithTimeout(ctx, timeout)
	defer cancel()

	req, err := buildRequest(ctx, call, token())
	if err != nil {
		report(c, call, http.StatusBadRequest, []byte(`{"error":"the node could not rebuild that request"}`))
		log.Printf("reverse call %s: %v", call.UUID, err)

		return
	}

	rec := &recorder{
		status: http.StatusOK,
		// An SSE response is a live install, and the panel is watching. Batched
		// so a chatty SteamCMD download is a request a second rather than a
		// request a line.
		flush: func(lines []ProgressLine) {
			if err := c.ReportProgress(ctx, call.UUID, lines); err != nil {
				log.Printf("reverse call %s: progress lost: %v", call.UUID, err)
			}
		},
	}

	// A panic in a handler must not take the daemon down, and it must not leave
	// the panel waiting for an answer that will never come either. The mux's
	// own recoverer covers the first; this covers the second.
	func() {
		defer func() {
			if r := recover(); r != nil {
				rec.status = http.StatusInternalServerError
				rec.body.Reset()
				rec.body.WriteString(`{"error":"the node failed while running that call"}`)
				log.Printf("reverse call %s panicked: %v", call.UUID, r)
			}
		}()

		handler.ServeHTTP(rec, req)
	}()

	rec.finish()
	report(c, call, rec.status, rec.body.Bytes())
}

func report(c *Client, call *Call, status int, body []byte) {
	// Its own context: the call's may already be cancelled, and an answer that
	// is never delivered leaves the panel waiting out the full deadline for
	// work that is actually finished.
	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()

	if err := c.ReportResult(ctx, call.UUID, status, body); err != nil {
		log.Printf("reverse call %s: could not report the result: %v", call.UUID, err)
	}
}

func buildRequest(ctx context.Context, call *Call, token string) (*http.Request, error) {
	if !strings.HasPrefix(call.Path, "/") {
		return nil, errors.New("path must be absolute")
	}

	body := []byte(call.Body)
	if call.Encoding == "base64" {
		decoded, err := base64.StdEncoding.DecodeString(call.Body)
		if err != nil {
			return nil, fmt.Errorf("body: %w", err)
		}
		body = decoded
	}

	target := call.Path
	if len(call.Query) > 0 {
		values := url.Values{}
		for k, v := range call.Query {
			values.Set(k, v)
		}
		target += "?" + values.Encode()
	}

	method := call.Method
	if method == "" {
		method = http.MethodGet
	}

	req, err := http.NewRequestWithContext(ctx, method, target, bytes.NewReader(body))
	if err != nil {
		return nil, err
	}

	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Accept", "application/json")
	// Served against the daemon's own mux, auth middleware and all. The panel
	// is not trusted to have skipped it: a call arriving over the tunnel is
	// authenticated exactly like one arriving over a socket.
	req.Header.Set("Authorization", "Bearer "+token)
	// The upload handler reads a length to refuse an oversized file before
	// reading it, and there is no socket here to infer one from.
	req.ContentLength = int64(len(body))
	if req.URL != nil {
		req.RequestURI = ""
	}

	return req, nil
}
