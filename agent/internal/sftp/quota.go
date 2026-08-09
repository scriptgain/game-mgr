package sftp

import (
	"fmt"
	"sync"
)

/*
A server's disk limit, enforced while somebody is uploading.

servers.disk was a number the panel displayed and nothing measured. That was
survivable while the only way in was the web file manager, which caps a single
upload and is a nuisance to use for volume. SFTP is not a nuisance to use for
volume, so without this one customer can fill the node's disk from an ordinary
client, and when a node's disk fills it is not their server that stops writing,
it is every server on the box.

The accounting is deliberately simple, and the simplifications are all in the
direction of refusing too early rather than too late:

  - Usage is measured once, when the connection opens. A walk of a forty
    gigabyte world is a metadata walk and costs milliseconds, but doing it per
    file would put it in the path of every write.

  - Bytes written during the session are added as they are written. Files
    deleted during the same session do not credit anything back, so somebody
    who deletes a world and uploads a new one in one sitting may be told they
    are full when strictly they are not. Reconnecting re-measures and clears it.

  - Overwriting a file counts its new bytes without subtracting its old ones,
    for the same reason.

Being told "you are full" when you have just freed space is an annoyance.
Silently filling the node's disk takes everybody else down, so the trade is not
close.
*/

// ErrQuotaExceeded is returned to the client when a write would pass the limit.
var ErrQuotaExceeded = fmt.Errorf("this server is out of disk space")

type quota struct {
	// Zero means unlimited, which is what a server with no limit set means
	// everywhere else in the panel.
	limitBytes int64

	mu   sync.Mutex
	used int64
}

func newQuota(limitMiB, usedMiB int64) *quota {
	return &quota{
		limitBytes: limitMiB * 1024 * 1024,
		used:       usedMiB * 1024 * 1024,
	}
}

func (q *quota) unlimited() bool {
	return q == nil || q.limitBytes <= 0
}

// reserve accounts for bytes about to be written, or refuses.
//
// Reserved before the write rather than counted after it, because counting
// afterwards means the bytes are already on the disk by the time anyone objects.
func (q *quota) reserve(n int64) error {
	if q.unlimited() || n <= 0 {
		return nil
	}

	q.mu.Lock()
	defer q.mu.Unlock()

	if q.used+n > q.limitBytes {
		return ErrQuotaExceeded
	}
	q.used += n

	return nil
}

// release gives back bytes a write reserved and then failed to produce, so a
// broken transfer does not permanently consume the allowance it never used.
func (q *quota) release(n int64) {
	if q.unlimited() || n <= 0 {
		return
	}

	q.mu.Lock()
	defer q.mu.Unlock()

	q.used -= n
	if q.used < 0 {
		q.used = 0
	}
}

// remaining is what is left, for the log line and for tests.
func (q *quota) remaining() int64 {
	if q.unlimited() {
		return -1
	}

	q.mu.Lock()
	defer q.mu.Unlock()

	left := q.limitBytes - q.used
	if left < 0 {
		return 0
	}

	return left
}
