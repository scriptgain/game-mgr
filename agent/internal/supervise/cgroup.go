package supervise

import (
	"fmt"
	"os"
	"path/filepath"
	"strconv"
	"strings"
)

// Docker gives memory and CPU limits for free. A native process gets nothing
// unless somebody puts it in a cgroup, so that is what this does.
//
// The important behaviour is what happens when it cannot: the panel shows a
// server's memory limit on every screen, and a limit that nothing enforces is
// worse than no limit at all, because it reads as a promise. So the manager
// reports whether limits are real and the node surfaces that, rather than
// quietly running unconstrained.
const cgroupRoot = "/sys/fs/cgroup"

type cgroupManager struct {
	usable bool
	reason string
	base   string
}

func newCgroupManager() *cgroupManager {
	m := &cgroupManager{base: filepath.Join(cgroupRoot, "gamemgr")}

	// cgroup v2 only. v1's split hierarchy needs a different controller path
	// per resource and is not worth supporting on anything new.
	if _, err := os.Stat(filepath.Join(cgroupRoot, "cgroup.controllers")); err != nil {
		m.reason = "no cgroup v2 on this node, so limits are not enforced"

		return m
	}

	if err := os.MkdirAll(m.base, 0o755); err != nil {
		m.reason = "cannot create a cgroup, so limits are not enforced: " + err.Error()

		return m
	}

	// The memory and cpu controllers have to be delegated to children before a
	// child cgroup can set memory.max or cpu.max. Without this the directories
	// appear and the limit files simply are not there.
	if err := m.enableControllers(); err != nil {
		m.reason = "cgroup controllers not delegated, so limits are not enforced: " + err.Error()

		return m
	}

	m.usable = true

	return m
}

func (m *cgroupManager) enableControllers() error {
	available, err := os.ReadFile(filepath.Join(cgroupRoot, "cgroup.controllers"))
	if err != nil {
		return err
	}

	var want []string
	for _, controller := range []string{"memory", "cpu"} {
		if strings.Contains(string(available), controller) {
			want = append(want, "+"+controller)
		}
	}
	if len(want) == 0 {
		return fmt.Errorf("neither the memory nor the cpu controller is available")
	}

	// Enabling on the root and then on our own directory: the first delegates
	// down to gamemgr/, the second delegates from gamemgr/ to each server.
	for _, path := range []string{
		filepath.Join(cgroupRoot, "cgroup.subtree_control"),
		filepath.Join(m.base, "cgroup.subtree_control"),
	} {
		if err := os.WriteFile(path, []byte(strings.Join(want, " ")), 0o644); err != nil {
			return err
		}
	}

	return nil
}

func (m *cgroupManager) describe() string {
	if m.usable {
		return ", cgroup v2 limits enforced"
	}
	if m.reason != "" {
		return ", " + m.reason
	}

	return ""
}

// apply puts a process into its own cgroup and sets the limits. Best effort by
// design: a server that runs without a cap is better than a server that refuses
// to start because the node's cgroup layout is unusual.
func (m *cgroupManager) apply(session string, pid int, memoryMiB int64, cpuPercent int) {
	if !m.usable || pid <= 0 {
		return
	}

	dir := filepath.Join(m.base, session)
	if err := os.MkdirAll(dir, 0o755); err != nil {
		return
	}

	if memoryMiB > 0 {
		limit := memoryMiB * 1024 * 1024

		// memory.high FIRST, and this is the whole point. high is a throttle:
		// at this figure the kernel reclaims hard and the process is slowed
		// down. max is a cliff: crossing it is an immediate kill.
		//
		// Setting only max, which is what this did, means over-allocation is a
		// promise the kernel will collect on violently. A node that has sold
		// more memory than it owns is fine right up to the moment two servers
		// are busy together, and then one dies mid save with no warning. With
		// high at the promised figure and max above it, the same server gets
		// slow instead, which is recoverable and visible on the metrics.
		_ = os.WriteFile(filepath.Join(dir, "memory.high"),
			[]byte(strconv.FormatInt(limit, 10)), 0o644)

		// The cliff stays, moved up. It is a runaway guard now rather than the
		// normal way a busy server is treated: something that blows a quarter
		// past its limit despite being throttled is leaking, not busy.
		_ = os.WriteFile(filepath.Join(dir, "memory.max"),
			[]byte(strconv.FormatInt(limit+limit/4, 10)), 0o644)

		// Swap still capped to zero. Reclaim under memory.high pushes cold
		// pages out; letting a game swap instead trades a slow server for a
		// node that feels broken to everyone on it.
		_ = os.WriteFile(filepath.Join(dir, "memory.swap.max"), []byte("0"), 0o644)
	}

	if cpuPercent > 0 {
		// cpu.max is "quota period" in microseconds. 100 percent is one core,
		// matching how the panel states the limit everywhere else.
		const period = 100000
		quota := int64(cpuPercent) * period / 100
		_ = os.WriteFile(filepath.Join(dir, "cpu.max"),
			[]byte(fmt.Sprintf("%d %d", quota, period)), 0o644)
	}

	// Writing the pid moves it and every future child into the cgroup, which is
	// what makes this cover the game rather than just the shell tmux started.
	_ = os.WriteFile(filepath.Join(dir, "cgroup.procs"), []byte(strconv.Itoa(pid)), 0o644)
}

// remove tidies up after a server stops. A cgroup with no processes left is
// harmless but accumulates, and a node that has started a thousand servers
// should not have a thousand empty directories.
func (m *cgroupManager) remove(session string) {
	if !m.usable {
		return
	}
	_ = os.Remove(filepath.Join(m.base, session))
}
