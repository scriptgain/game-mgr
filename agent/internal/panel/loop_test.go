package panel

import (
	"context"
	"net/http"
	"net/http/httptest"
	"testing"
	"time"
)

func TestBackoff(t *testing.T) {
	const interval = 30 * time.Second

	cases := []struct {
		name     string
		failures int
		want     time.Duration
	}{
		{"healthy nodes beat at the configured interval", 0, interval},
		{"the first failure retries at the interval", 1, interval},
		{"then doubles", 2, 60 * time.Second},
		{"and again", 3, 2 * time.Minute},
		{"and again", 4, 4 * time.Minute},
		{"until it caps", 5, MaxBackoff},
		// A panel down all weekend must not overflow the multiply into a
		// negative duration, which would turn the backoff into a hot loop at
		// exactly the moment the panel is least able to take one.
		{"a very long outage stays capped", 500, MaxBackoff},
	}

	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			if got := Backoff(interval, tc.failures); got != tc.want {
				t.Fatalf("Backoff(%s, %d) = %s, want %s", interval, tc.failures, got, tc.want)
			}
		})
	}
}

func TestJitterStaysWithinATenth(t *testing.T) {
	const d = 30 * time.Second
	for i := 0; i < 200; i++ {
		got := Jitter(d)
		if got < d-d/10 || got > d+d/10 {
			t.Fatalf("Jitter(%s) = %s, want within a tenth of %s", d, got, d)
		}
	}
}

// A panel that is down must cost the node a handful of requests, not a tight
// retry loop that hammers a struggling panel and burns a node's CPU while the
// game servers it is supposed to be running need it.
func TestHeartbeatBacksOffRatherThanHotLooping(t *testing.T) {
	const interval = time.Second

	attempts := 0
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		attempts++
		w.WriteHeader(http.StatusBadGateway)
		_, _ = w.Write([]byte(`{"message":"panel is down"}`))
	}))
	defer srv.Close()

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	// The waits are observed rather than spent, so the test is deterministic
	// and takes no longer than the requests themselves.
	var waits []time.Duration
	after := func(d time.Duration) <-chan time.Time {
		waits = append(waits, d)
		if len(waits) == 6 {
			cancel()
		}
		ch := make(chan time.Time, 1)
		ch <- time.Now()

		return ch
	}

	heartbeat(ctx, New(srv.URL, "token"), interval, func(context.Context) Metrics { return Metrics{} }, after)

	if len(waits) < 6 {
		t.Fatalf("the loop asked for %d waits before it stopped, want at least 6", len(waits))
	}
	// One request per iteration and no more: a loop that retried inside an
	// iteration would show up here as a request count larger than the waits.
	if attempts > len(waits) {
		t.Fatalf("%d requests for %d waits, want at most one request per wait", attempts, len(waits))
	}

	want := []time.Duration{interval, 2 * interval, 4 * interval, 8 * interval, 16 * interval, 32 * interval}
	for i, base := range want {
		if waits[i] < base-base/10 || waits[i] > base+base/10 {
			t.Fatalf("wait %d was %s, want about %s (jitter is a tenth)", i+1, waits[i], base)
		}
	}
}

// The counterpart: a healthy panel keeps the node beating at its configured
// interval rather than drifting upwards.
func TestHeartbeatHoldsTheIntervalWhileHealthy(t *testing.T) {
	const interval = 30 * time.Second

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		_, _ = w.Write([]byte(`{"ok":true}`))
	}))
	defer srv.Close()

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	var waits []time.Duration
	after := func(d time.Duration) <-chan time.Time {
		waits = append(waits, d)
		if len(waits) == 4 {
			cancel()
		}
		ch := make(chan time.Time, 1)
		ch <- time.Now()

		return ch
	}

	heartbeat(ctx, New(srv.URL, "token"), interval, func(context.Context) Metrics { return Metrics{} }, after)

	for i, got := range waits {
		if got < interval-interval/10 || got > interval+interval/10 {
			t.Fatalf("wait %d was %s, want about %s", i+1, got, interval)
		}
	}
}
