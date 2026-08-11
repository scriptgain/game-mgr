package api

import (
	"errors"
	"sync"
	"time"
)

// How long an install will wait at a Steam Guard prompt before giving up.
//
// Long enough for somebody to find their phone, short enough that an install
// nobody is watching does not hold a worker all day. The old behaviour, before
// any of this existed, was to wait the full six hour install timeout.
const guardWait = 10 * time.Minute

var (
	errGuardTimeout = errors.New("nobody entered a Steam Guard code in time")
	errGuardNoOne   = errors.New("that server is not waiting for a Steam Guard code")
)

// guardBroker hands a code typed into the panel to the install that is blocked
// waiting for one.
//
// The install and the code arrive on two different HTTP requests: the install
// is holding an SSE stream open while steamcmd sits at its prompt, and the code
// comes in later on its own POST. Something has to join them, and the server
// UUID is the only identifier both sides have.
//
// One waiter per server, deliberately. Two installs of the same server cannot
// run at once anyway, and a map of queues would only add a way for a code to
// reach the wrong prompt.
type guardBroker struct {
	mu      sync.Mutex
	waiting map[string]chan string
}

func newGuardBroker() *guardBroker {
	return &guardBroker{waiting: make(map[string]chan string)}
}

// begin registers a waiter and returns the channel a code will arrive on,
// plus the cleanup that must run when the wait ends however it ends.
//
// Buffered by one so submit never blocks: the submitter is an HTTP handler and
// must not be left hanging if the install gives up between the send and the
// receive.
func (b *guardBroker) begin(uuid string) (<-chan string, func()) {
	ch := make(chan string, 1)

	b.mu.Lock()
	// A previous waiter for this server is abandoned rather than merged. It can
	// only exist if an earlier install died without cleaning up, and leaving it
	// registered would send the next code to a prompt nobody is reading.
	b.waiting[uuid] = ch
	b.mu.Unlock()

	return ch, func() {
		b.mu.Lock()
		if b.waiting[uuid] == ch {
			delete(b.waiting, uuid)
		}
		b.mu.Unlock()
	}
}

// submit delivers a code. Reports whether anyone was actually waiting, so the
// panel can say "that install is no longer at the prompt" rather than accepting
// a code into nothing.
func (b *guardBroker) submit(uuid, code string) bool {
	b.mu.Lock()
	ch, ok := b.waiting[uuid]
	b.mu.Unlock()

	if !ok {
		return false
	}

	select {
	case ch <- code:
		return true
	default:
		// Already holds an unread code. A second one would be stale by the time
		// the first was consumed anyway.
		return false
	}
}

// pending reports whether a server is sitting at a prompt. Used by the panel to
// re-render the code box after a page reload, because the SSE event that opened
// it is not replayed.
func (b *guardBroker) pending(uuid string) bool {
	b.mu.Lock()
	defer b.mu.Unlock()
	_, ok := b.waiting[uuid]

	return ok
}
