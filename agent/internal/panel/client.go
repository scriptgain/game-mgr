// Package panel is the node daemon's client for the GameMGR panel.
//
// The relationship is dial-out only: a node calls the panel, the panel never
// has to reach in. That is what lets a node sit behind NAT, a home connection
// or a firewall that allows nothing inbound, and it is why enrollment and
// heartbeats both start here rather than at the panel.
//
// Nothing in this package is permitted to be fatal. A node whose panel is
// unreachable keeps running the game servers it already has: players do not get
// dropped because a web app is down. Every call returns an error to be logged
// and retried, and no code path here stops or touches a server.
package panel

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"strings"
	"time"
)

// ErrUnauthorized is the panel rejecting a credential: an enroll token that was
// already spent or has expired, or a daemon token the panel no longer knows.
// Enrollment stops retrying on this, because no amount of waiting turns a spent
// single-use token back into a good one.
var ErrUnauthorized = errors.New("panel rejected the credential")

type Client struct {
	base  string
	token string
	http  *http.Client
}

func New(base, token string) *Client {
	return &Client{
		base:  strings.TrimRight(base, "/"),
		token: token,
		// A finite timeout on every panel call. None of these endpoints stream,
		// and a half-open connection to a panel that vanished must not pin the
		// heartbeat goroutine forever.
		http: &http.Client{Timeout: 15 * time.Second},
	}
}

// Token is the credential the client is currently using, which after a
// successful Enroll is the long-lived one the panel just issued.
func (c *Client) Token() string { return c.token }

// Enrollment is what the panel hands back in exchange for a single-use token.
type Enrollment struct {
	Node struct {
		UUID string `json:"uuid"`
		Name string `json:"name"`
	} `json:"node"`
	Token             string `json:"token"`
	Panel             string `json:"panel"`
	HeartbeatInterval int    `json:"heartbeat_interval"`
}

// Enroll exchanges the single-use enroll token for the long-lived daemon
// credential and adopts it, so the caller cannot forget to.
//
// This is the one endpoint that carries its token in the body rather than the
// Authorization header: at this point the node has no bearer credential yet,
// which is the entire reason for the call.
func (c *Client) Enroll(ctx context.Context, enrollToken string, facts Facts) (*Enrollment, error) {
	body := struct {
		Token string `json:"token"`
		Facts
	}{Token: enrollToken, Facts: facts}

	var out Enrollment
	if err := c.post(ctx, "/api/node/enroll", body, &out); err != nil {
		return nil, err
	}
	if out.Token == "" {
		return nil, errors.New("panel accepted the enrollment but returned no token")
	}
	c.token = out.Token

	return &out, nil
}

// HeartbeatResult is what the panel says back. Small on purpose: the heartbeat
// is the one call that always happens, so it is where the panel tells the node
// things it needs to know without inventing a second channel for them.
type HeartbeatResult struct {
	// Whether this node is in reverse mode. The node does not decide this and
	// does not read it from its own config: an admin flips it in the panel and
	// the node finds out on its next beat, which matters because a reverse node
	// is by definition one nobody can log into to edit a file.
	Reverse bool `json:"reverse"`
}

// Heartbeat tells the panel the node is alive and hands over one metrics
// sample. The panel's agent.auth middleware reads a plain bearer token.
func (c *Client) Heartbeat(ctx context.Context, m Metrics) (HeartbeatResult, error) {
	var out HeartbeatResult
	err := c.post(ctx, "/api/node/heartbeat", m, &out)

	return out, err
}

func (c *Client) post(ctx context.Context, path string, body, out any) error {
	payload, err := json.Marshal(body)
	if err != nil {
		return err
	}

	req, err := http.NewRequestWithContext(ctx, http.MethodPost, c.base+path, bytes.NewReader(payload))
	if err != nil {
		return err
	}
	req.Header.Set("Content-Type", "application/json")
	// Without this Laravel can decide an error is a web request and answer with
	// an HTML error page, which turns a readable 401 into a JSON decode failure.
	req.Header.Set("Accept", "application/json")
	if c.token != "" {
		req.Header.Set("Authorization", "Bearer "+c.token)
	}

	res, err := c.http.Do(req)
	if err != nil {
		return err
	}
	defer res.Body.Close()

	raw, _ := io.ReadAll(io.LimitReader(res.Body, 64<<10))

	if res.StatusCode == http.StatusUnauthorized {
		return fmt.Errorf("%w: %s", ErrUnauthorized, message(raw))
	}
	if res.StatusCode >= 400 {
		return fmt.Errorf("panel %s: %d %s", path, res.StatusCode, message(raw))
	}

	if out == nil {
		return nil
	}

	return json.Unmarshal(raw, out)
}

// message pulls Laravel's error string out of a response, falling back to the
// raw body so an unexpected proxy page still says something useful in the log.
func message(raw []byte) string {
	var payload struct {
		Message string `json:"message"`
	}
	if err := json.Unmarshal(raw, &payload); err == nil && payload.Message != "" {
		return payload.Message
	}

	text := strings.TrimSpace(string(raw))
	if len(text) > 200 {
		text = text[:200] + "..."
	}

	return text
}
