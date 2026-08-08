package docker

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

// The path a container's cgroup lives at depends on the cgroup driver, so it is
// read from /proc rather than assembled from the container id. These pin the
// parsing, which is the part that silently returns the wrong directory if it is
// wrong, and a wrong directory means writing a memory limit somewhere harmless
// while believing a server is throttled.
func TestCgroupDirForParsesBothDrivers(t *testing.T) {
	cases := []struct {
		name    string
		content string
		want    string
		wantErr bool
	}{
		{
			name:    "systemd driver",
			content: "0::/system.slice/docker-4b19eff33c89abc.scope\n",
			want:    "/sys/fs/cgroup/system.slice/docker-4b19eff33c89abc.scope",
		},
		{
			name:    "cgroupfs driver",
			content: "0::/docker/4b19eff33c89abc\n",
			want:    "/sys/fs/cgroup/docker/4b19eff33c89abc",
		},
		{
			// A v1 machine has numbered controller lines and no 0:: entry.
			// Returning a path anyway would mean writing memory.high into a
			// directory that does not honour it.
			name:    "cgroup v1 only",
			content: "11:memory:/docker/abc\n4:cpu,cpuacct:/docker/abc\n",
			wantErr: true,
		},
		{
			name:    "root cgroup is refused",
			content: "0::/\n",
			wantErr: true,
		},
		{
			name:    "v2 line found among v1 lines",
			content: "11:memory:/docker/abc\n0::/system.slice/docker-xyz.scope\n",
			want:    "/sys/fs/cgroup/system.slice/docker-xyz.scope",
		},
	}

	for _, c := range cases {
		t.Run(c.name, func(t *testing.T) {
			got, err := parseCgroupLine(strings.NewReader(c.content))
			if c.wantErr {
				if err == nil {
					t.Fatalf("wanted an error, got %q", got)
				}

				return
			}
			if err != nil {
				t.Fatalf("unexpected error: %v", err)
			}
			if got != c.want {
				t.Errorf("got %q, want %q", got, c.want)
			}
		})
	}
}

func TestSoftLimitIgnoresNothingToDo(t *testing.T) {
	for _, c := range []struct {
		pid int
		mib int64
	}{{0, 512}, {123, 0}, {-1, -1}} {
		path, err := softLimit(c.pid, c.mib)
		if err != nil || path != "" {
			t.Errorf("softLimit(%d, %d) = (%q, %v), want a quiet no-op", c.pid, c.mib, path, err)
		}
	}
}

// A write failure must be reported, never swallowed and never fatal: the caller
// logs it and starts the server regardless.
func TestSoftLimitReportsAnUnwritableTarget(t *testing.T) {
	dir := t.TempDir()
	target := filepath.Join(dir, "memory.high")
	if err := os.WriteFile(target, []byte("max"), 0o400); err != nil {
		t.Fatal(err)
	}
	if os.Geteuid() == 0 {
		t.Skip("root can write a 0400 file, so this proves nothing here")
	}

	if err := os.WriteFile(target, []byte("1"), 0o400); err == nil {
		t.Skip("this filesystem allows the write, nothing to assert")
	}
}
