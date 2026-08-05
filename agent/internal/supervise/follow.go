package supervise

import (
	"bufio"
	"context"
	"io"
	"os"
	"path/filepath"
	"strings"
	"time"
)

// Follow replays the last n lines of a supervised server's console and then
// streams new ones until the caller goes away.
//
// Docker has a follow endpoint that does this for us. A native server has no
// such thing, so this is a tail: read to the end, then poll for growth. Polling
// rather than inotify because a game server writes constantly and a 400ms
// interval is imperceptible next to how fast the lines arrive anyway.
func Follow(ctx context.Context, dir string, tail int, w io.Writer) error {
	if tail <= 0 {
		tail = 200
	}

	return FollowFile(ctx, filepath.Join(RuntimeDir(dir), ConsoleFile), tail, w)
}

// FollowFile is Follow against an explicit path, for a runtime that keeps its
// own console log. LinuxGSM writes one, and reading it beats making a second
// capture of the same output.
func FollowFile(ctx context.Context, path string, tail int, w io.Writer) error {
	if tail <= 0 {
		tail = 200
	}

	backlog, err := tailFile(path, tail)
	if err != nil {
		return err
	}
	for _, line := range backlog {
		if _, err := io.WriteString(w, line+"\n"); err != nil {
			return err
		}
	}
	flush(w)

	if len(backlog) == 0 {
		if _, err := os.Stat(path); os.IsNotExist(err) {
			_, _ = io.WriteString(w, "[gamemgr] this server has not been started yet, so there is no output.\n")
			flush(w)
		}
	}

	file, err := os.Open(path)
	if err != nil {
		if os.IsNotExist(err) {
			// Wait for it to appear rather than giving up: somebody watching
			// the console before pressing Start should see the boot.
			return waitThenFollow(ctx, path, w)
		}

		return err
	}
	defer file.Close()

	if _, err := file.Seek(0, io.SeekEnd); err != nil {
		return err
	}

	return stream(ctx, file, w)
}

func waitThenFollow(ctx context.Context, path string, w io.Writer) error {
	for {
		select {
		case <-ctx.Done():
			return nil
		case <-time.After(time.Second):
		}

		file, err := os.Open(path)
		if err != nil {
			continue
		}
		defer file.Close()

		return stream(ctx, file, w)
	}
}

func stream(ctx context.Context, file *os.File, w io.Writer) error {
	reader := bufio.NewReader(file)

	for {
		if ctx.Err() != nil {
			return nil
		}

		line, err := reader.ReadString('\n')
		if err != nil {
			// A partial line means the server is mid-write. Rewind so the next
			// read picks it up whole rather than emitting half a line now and
			// half a line later.
			if len(line) > 0 {
				if _, seekErr := file.Seek(int64(-len(line)), io.SeekCurrent); seekErr == nil {
					reader.Reset(file)
				}
			}
			select {
			case <-ctx.Done():
				return nil
			case <-time.After(400 * time.Millisecond):
			}

			continue
		}

		clean := strings.TrimRight(ansi.ReplaceAllString(line, ""), " \t\n")
		if clean == "" {
			continue
		}
		if _, err := io.WriteString(w, clean+"\n"); err != nil {
			return err
		}
		flush(w)
	}
}

func flush(w io.Writer) {
	if f, ok := w.(interface{ Flush() }); ok {
		f.Flush()
	}
}
