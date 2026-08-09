package panel

import (
	"bytes"
	"net/http"
	"strings"
	"sync"
	"time"
)

/*
The http.ResponseWriter a tunnelled call is served into.

Ordinary responses are simply collected and returned as the answer. One kind is
not ordinary: an install answers with text/event-stream and then talks for
minutes or hours, and collecting that would mean the panel sees a blank progress
bar until the whole thing finishes, which for a two-hour SteamCMD download is
the same as no progress bar at all.

So when the handler declares itself a stream, complete lines are parsed as they
are written and pushed to the panel as progress instead of being buffered. The
final answer is then just the status, which is what the panel is waiting on.
*/
type recorder struct {
	mu      sync.Mutex
	header  http.Header
	status  int
	body    bytes.Buffer
	written bool

	// SSE handling
	streaming bool
	pending   bytes.Buffer   // bytes not yet forming a complete line
	batch     []ProgressLine // lines waiting to be sent
	event     string         // the current SSE event name
	lastSend  time.Time
	flush     func([]ProgressLine)
}

func (r *recorder) Header() http.Header {
	if r.header == nil {
		r.header = http.Header{}
	}

	return r.header
}

func (r *recorder) WriteHeader(status int) {
	if r.written {
		return
	}
	r.written = true
	r.status = status
	r.streaming = strings.HasPrefix(r.Header().Get("Content-Type"), "text/event-stream")
}

func (r *recorder) Write(p []byte) (int, error) {
	if !r.written {
		r.WriteHeader(http.StatusOK)
	}

	r.mu.Lock()
	defer r.mu.Unlock()

	if !r.streaming {
		return r.body.Write(p)
	}

	r.pending.Write(p)
	r.consume()

	// Time-based rather than size-based. A download emits many lines a second
	// and a quiet verify step emits one a minute; batching by count would make
	// the quiet phase look like a hang.
	if time.Since(r.lastSend) >= time.Second {
		r.send()
	}

	return len(p), nil
}

// Flush is what an SSE handler calls after each event. Honouring it is what
// makes the progress arrive while the install is running rather than after.
func (r *recorder) Flush() {
	r.mu.Lock()
	defer r.mu.Unlock()

	r.consume()
	if time.Since(r.lastSend) >= time.Second {
		r.send()
	}
}

// finish pushes whatever is left, once the handler has returned.
func (r *recorder) finish() {
	r.mu.Lock()
	defer r.mu.Unlock()

	if r.pending.Len() > 0 {
		// A last line with no trailing newline still counts.
		r.line(r.pending.String())
		r.pending.Reset()
	}
	r.send()
}

// consume turns whole lines in the pending buffer into batched progress.
func (r *recorder) consume() {
	for {
		raw := r.pending.Bytes()
		idx := bytes.IndexByte(raw, '\n')
		if idx < 0 {
			return
		}

		line := string(raw[:idx])
		r.pending.Next(idx + 1)
		r.line(line)
	}
}

// line parses one line of the SSE wire format.
func (r *recorder) line(line string) {
	line = strings.TrimRight(line, "\r")

	switch {
	case line == "":
		// A blank line ends an event; the next one starts fresh.
		r.event = ""
	case strings.HasPrefix(line, "event: "):
		r.event = strings.TrimPrefix(line, "event: ")
	case strings.HasPrefix(line, "data: "):
		event := r.event
		if event == "" {
			event = "message"
		}
		r.batch = append(r.batch, ProgressLine{Event: event, Data: strings.TrimPrefix(line, "data: ")})
	}
}

func (r *recorder) send() {
	if len(r.batch) == 0 || r.flush == nil {
		r.lastSend = time.Now()

		return
	}

	batch := r.batch
	r.batch = nil
	r.lastSend = time.Now()

	// Called with the lock held on purpose: the ordering of progress batches is
	// the one thing the panel cannot recover if it is wrong, and an install log
	// with its lines shuffled is worse than one that arrives a moment later.
	r.flush(batch)
}
