package panel

import (
	"context"
	"log"
	"math/rand"
	"time"
)

// MaxBackoff caps the retry interval. A panel that has been down for an hour
// should cost the node a handful of requests, not thousands, but the node still
// has to notice within a few minutes of it coming back.
const MaxBackoff = 5 * time.Minute

// Heartbeat sends a sample every interval until ctx is cancelled, backing off
// when the panel does not answer. It never returns an error and never touches a
// server: an unreachable panel is a reporting outage, not an outage.
func Heartbeat(ctx context.Context, c *Client, interval time.Duration, sample func(context.Context) Metrics) {
	HeartbeatWith(ctx, c, interval, sample, nil)
}

// HeartbeatWith is Heartbeat plus a look at what the panel answered. Used for
// the one thing the panel tells a node this way: whether it is in reverse mode.
// The callback runs on every successful beat, not only on a change, so the
// caller decides what counts as a change.
func HeartbeatWith(ctx context.Context, c *Client, interval time.Duration, sample func(context.Context) Metrics, onResult func(HeartbeatResult)) {
	heartbeat(ctx, c, interval, sample, time.After, onResult)
}

// after is injected so the tests can observe the delays the loop asks for
// without spending them.
func heartbeat(ctx context.Context, c *Client, interval time.Duration, sample func(context.Context) Metrics, after func(time.Duration) <-chan time.Time, onResult func(HeartbeatResult)) {
	if interval <= 0 {
		interval = 30 * time.Second
	}

	failures := 0
	for {
		result, err := c.Heartbeat(ctx, sample(ctx))
		if err != nil {
			if ctx.Err() != nil {
				return
			}
			failures++
			// Every failure is logged: a node that silently stopped reporting
			// is the one thing an operator cannot debug from the panel, since
			// the panel is precisely what it stopped talking to.
			log.Printf("heartbeat failed (%d in a row): %v", failures, err)
		} else {
			if failures > 0 {
				log.Printf("panel reachable again after %d failed %s", failures, plural("heartbeat", failures))
			}
			failures = 0

			if onResult != nil {
				onResult(result)
			}
		}

		select {
		case <-ctx.Done():
			return
		case <-after(Jitter(Backoff(interval, failures))):
		}
	}
}

// Backoff is the wait after n consecutive failures: the normal interval while
// things are healthy, then doubling up to MaxBackoff.
func Backoff(interval time.Duration, failures int) time.Duration {
	if interval <= 0 {
		interval = time.Second
	}

	d := interval
	for i := 0; i < failures-1 && d < MaxBackoff; i++ {
		d *= 2
	}
	if d > MaxBackoff {
		d = MaxBackoff
	}

	return d
}

// Jitter spreads a wait by up to a tenth either way. A rack of nodes that all
// booted from the same image at the same moment would otherwise heartbeat in
// lockstep, and the panel would see the whole fleet arrive in one spike every
// interval instead of a steady trickle.
func Jitter(d time.Duration) time.Duration {
	spread := int64(d) / 10
	if spread <= 0 {
		return d
	}

	return d + time.Duration(rand.Int63n(2*spread)-spread)
}

func plural(word string, n int) string {
	if n == 1 {
		return word
	}

	return word + "s"
}
