package api

import (
	"bytes"
	"fmt"
	"io"
	"net/http"
	"strings"
	"sync"
)

// sseWriter turns writes into Server-Sent Events. Anything written through it
// directly becomes a "console" event, which lets a driver's Logs method take a
// plain io.Writer and know nothing about HTTP.
type sseWriter struct {
	mu  sync.Mutex
	w   http.ResponseWriter
	f   http.Flusher
	buf bytes.Buffer
}

func newSSE(w http.ResponseWriter) *sseWriter {
	f, ok := w.(http.Flusher)
	if !ok {
		return nil
	}
	w.Header().Set("Content-Type", "text/event-stream")
	w.Header().Set("Cache-Control", "no-cache")
	w.Header().Set("Connection", "keep-alive")
	// Without this an intermediate proxy will happily buffer the whole stream
	// and the console stays empty until the server stops, which looks exactly
	// like a broken console.
	w.Header().Set("X-Accel-Buffering", "no")
	w.WriteHeader(http.StatusOK)
	f.Flush()
	return &sseWriter{w: w, f: f}
}

// Write splits on newlines so each console line is its own event.
func (s *sseWriter) Write(p []byte) (int, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.buf.Write(p)
	for {
		line, err := s.buf.ReadString('\n')
		if err != nil {
			// Partial line: put it back and wait for the rest.
			s.buf.Reset()
			s.buf.WriteString(line)
			break
		}
		s.emit("console", strings.TrimRight(line, "\r\n"))
	}
	return len(p), nil
}

func (s *sseWriter) Event(name, data string) {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.emit(name, data)
}

// emit assumes the lock is held.
func (s *sseWriter) emit(name, data string) {
	// A data payload containing newlines needs one data: line each, or the
	// browser silently truncates at the first newline.
	fmt.Fprintf(s.w, "event: %s\n", name)
	for _, l := range strings.Split(data, "\n") {
		fmt.Fprintf(s.w, "data: %s\n", l)
	}
	fmt.Fprint(s.w, "\n")
	s.f.Flush()
}

func (s *sseWriter) Flush() { s.f.Flush() }

// ConsoleWriter is the io.Writer a driver's Logs method writes into.
func (s *sseWriter) ConsoleWriter() io.Writer { return s }

// lineBuffer collects the last N lines written to it. Used by the non-streaming
// /logs endpoint, which returns a backlog rather than holding a connection.
type lineBuffer struct {
	mu    sync.Mutex
	limit int
	buf   bytes.Buffer
	lines []string
}

func (b *lineBuffer) Write(p []byte) (int, error) {
	b.mu.Lock()
	defer b.mu.Unlock()
	b.buf.Write(p)
	for {
		line, err := b.buf.ReadString('\n')
		if err != nil {
			b.buf.Reset()
			b.buf.WriteString(line)
			break
		}
		b.lines = append(b.lines, strings.TrimRight(line, "\r\n"))
		if b.limit > 0 && len(b.lines) > b.limit {
			b.lines = b.lines[len(b.lines)-b.limit:]
		}
	}
	return len(p), nil
}

func (b *lineBuffer) Lines() []string {
	b.mu.Lock()
	defer b.mu.Unlock()
	out := make([]string, len(b.lines))
	copy(out, b.lines)
	return out
}
