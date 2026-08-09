package panel

import (
	"context"
	"encoding/base64"
	"encoding/json"
	"io"
	"net/http"
	"net/http/httptest"
	"strings"
	"sync"
	"testing"
	"time"
)

// A tunnelled call has to reach the daemon's real handler, carry its query and
// body, and come back with the status and bytes the handler produced. If any of
// that is lossy, reverse mode is a quieter kind of broken than being offline.
func TestServeCallRunsAgainstTheHandler(t *testing.T) {
	var (
		mu     sync.Mutex
		result struct {
			Status   int    `json:"status"`
			Body     string `json:"body"`
			Encoding string `json:"encoding"`
		}
		gotAuth  string
		gotQuery string
		gotBody  string
		served   = make(chan struct{})
	)

	handler := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		gotAuth = r.Header.Get("Authorization")
		gotQuery = r.URL.Query().Get("path")
		raw, _ := io.ReadAll(r.Body)
		gotBody = string(raw)

		w.WriteHeader(http.StatusCreated)
		w.Write([]byte(`{"ok":true}`))
	})

	panelSrv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch {
		case r.URL.Path == "/api/node/calls":
			mu.Lock()
			defer mu.Unlock()
			// One call, then nothing: the loop must keep polling rather than
			// treating an empty answer as a reason to stop.
			if result.Status != 0 {
				w.WriteHeader(http.StatusNoContent)

				return
			}
			json.NewEncoder(w).Encode(map[string]any{"call": Call{
				UUID:   "abc",
				Method: "POST",
				Path:   "/api/servers/x/files/write",
				Query:  map[string]string{"path": "server.properties"},
				Body:   `{"content":"hello"}`,
			}})

		case strings.HasSuffix(r.URL.Path, "/result"):
			mu.Lock()
			json.NewDecoder(r.Body).Decode(&result)
			mu.Unlock()
			w.Write([]byte(`{"ok":true}`))
			close(served)
		}
	}))
	defer panelSrv.Close()

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	go ServeCalls(ctx, New(panelSrv.URL, "node-token"), handler, func() string { return "node-token" }, time.Second)

	select {
	case <-served:
	case <-time.After(5 * time.Second):
		t.Fatal("the call was never answered")
	}

	mu.Lock()
	defer mu.Unlock()

	if result.Status != http.StatusCreated {
		t.Fatalf("status = %d, want 201", result.Status)
	}

	body, err := base64.StdEncoding.DecodeString(result.Body)
	if err != nil {
		t.Fatalf("body was not base64: %v", err)
	}
	if string(body) != `{"ok":true}` {
		t.Fatalf("body = %q", body)
	}
	if gotAuth != "Bearer node-token" {
		t.Fatalf("the handler saw auth %q: a tunnelled call must authenticate like any other", gotAuth)
	}
	if gotQuery != "server.properties" {
		t.Fatalf("query lost: %q", gotQuery)
	}
	if gotBody != `{"content":"hello"}` {
		t.Fatalf("body lost: %q", gotBody)
	}
}

// An install answers 200 and then talks for an hour. Its lines have to reach
// the panel while it is running, not in one lump when it finishes.
func TestStreamingCallReportsProgressBeforeItFinishes(t *testing.T) {
	release := make(chan struct{})
	handler := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "text/event-stream")
		w.WriteHeader(http.StatusOK)
		w.Write([]byte("event: message\ndata: downloading\n\n"))
		w.(http.Flusher).Flush()
		<-release
		w.Write([]byte("event: done\ndata: finished\n\n"))
	})

	var (
		mu       sync.Mutex
		progress []ProgressLine
		handed   bool
	)
	firstBatch := make(chan struct{})
	finished := make(chan struct{})

	panelSrv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch {
		case r.URL.Path == "/api/node/calls":
			mu.Lock()
			defer mu.Unlock()
			if handed {
				w.WriteHeader(http.StatusNoContent)

				return
			}
			handed = true
			json.NewEncoder(w).Encode(map[string]any{"call": Call{
				UUID: "abc", Method: "POST", Path: "/api/servers/x/install",
			}})

		case strings.HasSuffix(r.URL.Path, "/progress"):
			var payload struct {
				Lines []ProgressLine `json:"lines"`
			}
			json.NewDecoder(r.Body).Decode(&payload)
			mu.Lock()
			first := len(progress) == 0
			progress = append(progress, payload.Lines...)
			mu.Unlock()
			w.Write([]byte(`{"ok":true}`))
			if first {
				close(firstBatch)
			}

		case strings.HasSuffix(r.URL.Path, "/result"):
			w.Write([]byte(`{"ok":true}`))
			close(finished)
		}
	}))
	defer panelSrv.Close()

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	go ServeCalls(ctx, New(panelSrv.URL, "t"), handler, func() string { return "t" }, time.Second)

	select {
	case <-firstBatch:
	case <-time.After(5 * time.Second):
		t.Fatal("no progress arrived while the handler was still running")
	}

	mu.Lock()
	if len(progress) == 0 || progress[0].Data != "downloading" {
		mu.Unlock()
		t.Fatalf("first batch = %+v", progress)
	}
	mu.Unlock()

	close(release)

	select {
	case <-finished:
	case <-time.After(5 * time.Second):
		t.Fatal("the call never completed")
	}
}

// A path the daemon does not serve must come back as the handler's own 404.
// Anything else and a panel with a newer idea of the API than the node gets a
// hang instead of an answer.
func TestUnknownPathAnswers404(t *testing.T) {
	rec := &recorder{status: http.StatusOK}
	req, err := buildRequest(context.Background(), &Call{Method: "GET", Path: "/api/nope"}, "t")
	if err != nil {
		t.Fatal(err)
	}

	http.NewServeMux().ServeHTTP(rec, req)
	rec.finish()

	if rec.status != http.StatusNotFound {
		t.Fatalf("status = %d, want 404", rec.status)
	}
}

func TestBuildRequestRejectsARelativePath(t *testing.T) {
	if _, err := buildRequest(context.Background(), &Call{Method: "GET", Path: "api/system"}, "t"); err == nil {
		t.Fatal("a path without a leading slash must be refused, not resolved against something")
	}
}

// Uploads arrive base64 because a JSON string cannot hold arbitrary bytes.
func TestBuildRequestDecodesABase64Body(t *testing.T) {
	req, err := buildRequest(context.Background(), &Call{
		Method:   "POST",
		Path:     "/api/servers/x/files/upload",
		Body:     base64.StdEncoding.EncodeToString([]byte{0x00, 0xff, 0x10}),
		Encoding: "base64",
	}, "t")
	if err != nil {
		t.Fatal(err)
	}

	body, _ := io.ReadAll(req.Body)
	if len(body) != 3 || body[1] != 0xff {
		t.Fatalf("body = % x", body)
	}
	if req.ContentLength != 3 {
		t.Fatalf("ContentLength = %d: the upload handler refuses an oversized file by reading it", req.ContentLength)
	}
}

// The panel used to send memory and port as JSON numbers, which made the whole
// call fail to decode and every request after the first look like an
// unreachable node. Pinned in both directions.
func TestQueryDecodesNumbersAndStrings(t *testing.T) {
	var call Call
	if err := json.Unmarshal([]byte(`{"query":{"name":"Survival","memory":2048,"port":25565,"big":1000000,"on":true}}`), &call); err != nil {
		t.Fatalf("a numeric query value must not break the decode: %v", err)
	}

	for key, want := range map[string]string{
		"name": "Survival", "memory": "2048", "port": "25565", "big": "1000000", "on": "true",
	} {
		if got := call.Query[key]; got != want {
			t.Fatalf("query[%q] = %q, want %q", key, got, want)
		}
	}
}
