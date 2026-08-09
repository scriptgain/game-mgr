package sftp

import (
	"sync"
	"time"
)

// A port answering passwords on the public internet gets found and guessed at,
// usually within a day of opening. The panel checks every password, so without
// something here a guessing run becomes an unbounded stream of HTTP calls into
// the panel's database, and the first sign of trouble would be the panel
// slowing down rather than the node saying it is under attack.
//
// Per address, not per username: a run tries thousands of usernames from one
// place, and locking per username would let it lock every real customer out.
const (
	// Attempts allowed before an address is made to wait.
	maxFailures = 5
	// How long a blocked address waits. Long enough to make guessing pointless,
	// short enough that somebody who genuinely forgot their password can try
	// again without opening a support ticket.
	blockFor = 15 * time.Minute
	// A run of failures is forgotten after this, so yesterday's typo does not
	// count towards today's.
	forgetAfter = 30 * time.Minute
)

type attempts struct {
	failures int
	last     time.Time
	until    time.Time
}

type limiter struct {
	mu sync.Mutex
	by map[string]*attempts
	// now is a variable so tests do not have to wait fifteen minutes.
	now func() time.Time
}

func newLimiter() *limiter {
	return &limiter{by: map[string]*attempts{}, now: time.Now}
}

// blocked reports whether an address must wait, and for how long.
func (l *limiter) blocked(ip string) (time.Duration, bool) {
	l.mu.Lock()
	defer l.mu.Unlock()

	record, ok := l.by[ip]
	if !ok {
		return 0, false
	}
	now := l.now()
	if record.until.After(now) {
		return record.until.Sub(now), true
	}
	// The block has expired. Forget the run that caused it, or the very next
	// failure would immediately re-block on a stale count.
	if !record.until.IsZero() {
		delete(l.by, ip)
	}

	return 0, false
}

func (l *limiter) failed(ip string) {
	l.mu.Lock()
	defer l.mu.Unlock()

	now := l.now()
	record, ok := l.by[ip]
	if !ok || now.Sub(record.last) > forgetAfter {
		record = &attempts{}
		l.by[ip] = record
	}
	record.failures++
	record.last = now
	if record.failures >= maxFailures {
		record.until = now.Add(blockFor)
	}

	l.sweep(now)
}

// succeeded clears the record: a correct password proves the address is not
// running a guessing list, and a customer who mistyped twice should not carry
// those two towards a block for the rest of the day.
func (l *limiter) succeeded(ip string) {
	l.mu.Lock()
	defer l.mu.Unlock()

	delete(l.by, ip)
}

// sweep drops records nobody is counting any more, so a long guessing run from
// many addresses cannot grow this map without limit.
func (l *limiter) sweep(now time.Time) {
	if len(l.by) < 1024 {
		return
	}
	for ip, record := range l.by {
		if record.until.Before(now) && now.Sub(record.last) > forgetAfter {
			delete(l.by, ip)
		}
	}
}
