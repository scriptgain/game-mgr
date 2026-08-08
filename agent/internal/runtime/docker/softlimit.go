package docker

import (
	"bufio"
	"errors"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"strconv"
	"strings"
)

// softLimit writes memory.high into a running container's own cgroup.
//
// Docker exposes memory.max, through HostConfig.Memory, and nothing else. That
// makes every limit a cliff: a container at its ceiling is killed rather than
// slowed, which is a poor trade on a node that has deliberately sold more
// memory than it owns. memory.high is the throttle, and the only way to set one
// for a container is to find its cgroup and write it directly.
//
// Best effort by design. A node where this fails is exactly as it was before,
// and no game operation may fail because of it.
func softLimit(pid int, memoryMiB int64) (string, error) {
	if pid <= 0 || memoryMiB <= 0 {
		return "", nil
	}

	dir, err := cgroupDirFor(pid)
	if err != nil {
		return "", err
	}

	path := filepath.Join(dir, "memory.high")
	value := strconv.FormatInt(memoryMiB*1024*1024, 10)
	if err := os.WriteFile(path, []byte(value), 0o644); err != nil {
		return path, err
	}

	return path, nil
}

// cgroupDirFor resolves a pid to its cgroup v2 directory.
//
// Read from /proc rather than assembled from the container id, because the path
// depends on the cgroup driver: systemd gives system.slice/docker-<id>.scope
// and cgroupfs gives docker/<id>. Asking the kernel where the process actually
// is works for both, and for whatever a future runtime does instead.
func cgroupDirFor(pid int) (string, error) {
	f, err := os.Open(fmt.Sprintf("/proc/%d/cgroup", pid))
	if err != nil {
		return "", err
	}
	defer f.Close()

	dir, err := parseCgroupLine(f)
	if err != nil {
		return "", fmt.Errorf("process %d: %w", pid, err)
	}

	return dir, nil
}

// parseCgroupLine pulls the cgroup v2 directory out of a /proc/<pid>/cgroup
// body. Split out from the file handling so the parsing, which is the part
// that fails silently by returning a plausible wrong path, can be tested.
func parseCgroupLine(r io.Reader) (string, error) {
	scanner := bufio.NewScanner(r)
	for scanner.Scan() {
		// cgroup v2 is the single line beginning "0::". A v1 machine has
		// numbered controller lines and no such entry, and writing
		// memory.high there would do nothing at all.
		line := scanner.Text()
		if !strings.HasPrefix(line, "0::") {
			continue
		}
		rel := strings.TrimPrefix(line, "0::")
		if rel == "" || rel == "/" {
			return "", errors.New("in the root cgroup, so there is nothing of its own to limit")
		}

		return filepath.Join("/sys/fs/cgroup", rel), nil
	}

	return "", errors.New("no cgroup v2 entry, so this node is on cgroup v1")
}
