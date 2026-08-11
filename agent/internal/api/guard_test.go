package api

import (
	"sync"
	"testing"
	"time"
)

func TestACodeReachesTheWaitingInstall(t *testing.T) {
	b := newGuardBroker()
	codes, done := b.begin("server-1")
	defer done()

	if !b.submit("server-1", "K9J4M") {
		t.Fatal("submit reported nobody was waiting")
	}

	select {
	case got := <-codes:
		if got != "K9J4M" {
			t.Fatalf("got %q", got)
		}
	case <-time.After(time.Second):
		t.Fatal("the code never arrived")
	}
}

// A code for a server nobody is installing must be refused rather than
// swallowed. The panel says "that install is no longer waiting" off the back of
// this; accepting it silently would leave somebody watching a spinner.
func TestACodeForNobodyIsRefused(t *testing.T) {
	b := newGuardBroker()

	if b.submit("server-1", "K9J4M") {
		t.Fatal("accepted a code with no waiter")
	}
	if b.pending("server-1") {
		t.Fatal("pending said yes with no waiter")
	}
}

// Cleanup has to deregister, or the next code goes to a prompt nobody is
// reading and the install that follows waits the full ten minutes.
func TestCleanupDeregisters(t *testing.T) {
	b := newGuardBroker()
	_, done := b.begin("server-1")

	if !b.pending("server-1") {
		t.Fatal("pending said no while waiting")
	}

	done()

	if b.pending("server-1") {
		t.Fatal("still pending after cleanup")
	}
	if b.submit("server-1", "K9J4M") {
		t.Fatal("accepted a code after cleanup")
	}
}

// A late cleanup from an abandoned install must not deregister the waiter that
// replaced it. Otherwise a retried install is unanswerable and nothing says why.
func TestAStaleCleanupLeavesTheNewWaiterAlone(t *testing.T) {
	b := newGuardBroker()

	_, doneFirst := b.begin("server-1")
	codes, doneSecond := b.begin("server-1")
	defer doneSecond()

	doneFirst()

	if !b.pending("server-1") {
		t.Fatal("the stale cleanup deregistered the live waiter")
	}
	if !b.submit("server-1", "K9J4M") {
		t.Fatal("the live waiter cannot be reached")
	}
	if got := <-codes; got != "K9J4M" {
		t.Fatalf("got %q", got)
	}
}

// submit is called from an HTTP handler and must never block, whatever the
// install is doing. A second code arriving before the first is read is dropped
// rather than parked, because it would be stale by the time anyone read it.
func TestSubmitNeverBlocks(t *testing.T) {
	b := newGuardBroker()
	_, done := b.begin("server-1")
	defer done()

	var wg sync.WaitGroup
	for i := 0; i < 8; i++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			b.submit("server-1", "K9J4M")
		}()
	}

	finished := make(chan struct{})
	go func() { wg.Wait(); close(finished) }()

	select {
	case <-finished:
	case <-time.After(2 * time.Second):
		t.Fatal("submit blocked")
	}
}

// Two servers waiting at once must not cross. Same node, two installs, and a
// code typed for one landing in the other would be maddening to diagnose.
func TestCodesDoNotCrossBetweenServers(t *testing.T) {
	b := newGuardBroker()
	one, doneOne := b.begin("server-1")
	defer doneOne()
	two, doneTwo := b.begin("server-2")
	defer doneTwo()

	b.submit("server-2", "SECOND")

	select {
	case got := <-one:
		t.Fatalf("server-1 received %q, which was meant for server-2", got)
	default:
	}

	if got := <-two; got != "SECOND" {
		t.Fatalf("server-2 got %q", got)
	}
}
