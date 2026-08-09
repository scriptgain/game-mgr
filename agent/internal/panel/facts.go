package panel

import (
	"bufio"
	"context"
	"os"
	"runtime"
	"strconv"
	"strings"
	"syscall"
	"time"

	"github.com/scriptgain/gamemgr-node/internal/docker"
)

// Facts is what a node says about itself once, at enrollment: the things the
// panel shows on a node's Overview tab and uses to decide what it can run.
//
// The field lengths mirror the panel's validation rules. They are enforced here
// rather than hoped for, because a node whose /etc/os-release happens to be
// verbose would otherwise fail enrollment with a 422 and no obvious cause.
type Facts struct {
	OS           string   `json:"os"`
	Kernel       string   `json:"kernel"`
	Arch         string   `json:"arch"`
	Docker       string   `json:"docker"`
	AgentVersion string   `json:"agent_version"`
	CPUCores     int      `json:"cpu_cores"`
	Memory       int64    `json:"memory"`
	Disk         int64    `json:"disk"`
	Runtimes     []string `json:"runtimes"`
}

// Gather collects the node's facts. Every source is optional: a missing
// /proc, an unreachable Docker socket or an unreadable data root each leave one
// field empty rather than failing the enrollment the node needs to complete.
func Gather(ctx context.Context, socket, root, agentVersion string, runtimes []string) Facts {
	sysname, release, machine := unameFacts()

	f := Facts{
		OS:           clamp(distribution(sysname), 120),
		Kernel:       clamp(release, 120),
		Arch:         clamp(machine, 32),
		AgentVersion: clamp(agentVersion, 32),
		CPUCores:     runtime.NumCPU(),
		Memory:       totalMemory(),
		Runtimes:     runtimes,
	}
	if total, _, err := diskUsage(root); err == nil {
		f.Disk = total
	}

	// Bounded separately: a Docker daemon that accepts the connection and then
	// never answers must not hold up enrollment.
	dctx, cancel := context.WithTimeout(ctx, 5*time.Second)
	defer cancel()
	if v, err := docker.New(socket).Version(dctx); err == nil {
		f.Docker = clamp(v, 64)
	}

	return f
}

// Metrics is one heartbeat sample. Field names match what the panel validates
// and stores on node_metrics; memory and disk are bytes in use, not totals.
type Metrics struct {
	CPU          float64 `json:"cpu"`
	Memory       int64   `json:"memory"`
	Disk         int64   `json:"disk"`
	Load         float64 `json:"load"`
	Running      int     `json:"running"`
	AgentVersion string  `json:"agent_version"`
	// Whether this node is actually answering SFTP, and the host key it answers
	// with. Reported rather than configured in the panel: an admin ticking a box
	// would happily show a customer a username and a port with nothing behind
	// it, and the fingerprint is what lets that customer tell a real first
	// connection from an intercepted one.
	SFTPEnabled     bool   `json:"sftp_enabled"`
	SFTPFingerprint string `json:"sftp_fingerprint,omitempty"`
}

// Sampler produces heartbeat metrics. It holds the previous /proc/stat reading
// because host CPU use is only meaningful as a delta between two samples; a
// single reading is the average since boot, which is never what a graph wants.
type Sampler struct {
	root         string
	agentVersion string
	running      func(context.Context) int
	// Reported unchanged on every heartbeat, so a node whose SFTP listener
	// failed to start stops advertising itself as soon as it restarts.
	sftpEnabled     bool
	sftpFingerprint string

	prevIdle  uint64
	prevTotal uint64
}

func NewSampler(root, agentVersion string, running func(context.Context) int) *Sampler {
	return &Sampler{root: root, agentVersion: agentVersion, running: running}
}

// ReportSFTP tells the sampler what to say about file access. Called once, after
// the listener has actually bound, so a node that failed to start SFTP never
// claims to offer it.
func (s *Sampler) ReportSFTP(enabled bool, fingerprint string) {
	s.sftpEnabled = enabled
	s.sftpFingerprint = fingerprint
}

// Sample is called from the heartbeat goroutine only, so the counters need no
// lock.
func (s *Sampler) Sample(ctx context.Context) Metrics {
	m := Metrics{
		AgentVersion:    clamp(s.agentVersion, 32),
		Load:            loadAverage(),
		SFTPEnabled:     s.sftpEnabled,
		SFTPFingerprint: s.sftpFingerprint,
	}

	if total, avail, err := memory(); err == nil {
		m.Memory = total - avail
	}
	if total, free, err := diskUsage(s.root); err == nil {
		m.Disk = total - free
	}
	m.CPU = s.cpuPercent()
	if s.running != nil {
		m.Running = s.running(ctx)
	}

	return m
}

func (s *Sampler) cpuPercent() float64 {
	idle, total, err := cpuJiffies()
	if err != nil {
		return 0
	}
	defer func() { s.prevIdle, s.prevTotal = idle, total }()

	// The first sample has nothing to subtract from. Reporting 0 is honest;
	// reporting the since-boot average would be a plausible-looking lie.
	if s.prevTotal == 0 || total <= s.prevTotal {
		return 0
	}

	busy := float64((total - s.prevTotal) - (idle - s.prevIdle))

	return round1(busy / float64(total-s.prevTotal) * 100)
}

// ------------------------------------------------------------------- sources

// distribution prefers the human name from /etc/os-release, because "Linux" on
// its own tells an operator staring at the node list nothing at all.
func distribution(fallback string) string {
	f, err := os.Open("/etc/os-release")
	if err != nil {
		return fallback
	}
	defer f.Close()

	scanner := bufio.NewScanner(f)
	for scanner.Scan() {
		name, value, ok := strings.Cut(scanner.Text(), "=")
		if ok && name == "PRETTY_NAME" {
			if v := strings.Trim(strings.TrimSpace(value), `"'`); v != "" {
				return v
			}
		}
	}

	return fallback
}

func totalMemory() int64 {
	total, _, err := memory()
	if err != nil {
		return 0
	}

	return total
}

// memory returns total and available bytes. Available, not free: free excludes
// the page cache, which the kernel will hand back on demand, so a healthy box
// looks like it is out of memory.
func memory() (total, available int64, err error) {
	f, err := os.Open("/proc/meminfo")
	if err != nil {
		return 0, 0, err
	}
	defer f.Close()

	scanner := bufio.NewScanner(f)
	for scanner.Scan() {
		key, value, ok := strings.Cut(scanner.Text(), ":")
		if !ok {
			continue
		}
		kb, err := strconv.ParseInt(strings.TrimSuffix(strings.TrimSpace(value), " kB"), 10, 64)
		if err != nil {
			continue
		}
		switch key {
		case "MemTotal":
			total = kb * 1024
		case "MemAvailable":
			available = kb * 1024
		}
	}

	return total, available, scanner.Err()
}

// diskUsage returns the total and free bytes of the filesystem holding path.
// Free is the unprivileged figure so it matches what df reports, and what a
// customer filling their server directory will actually be allowed to use.
func diskUsage(path string) (total, free int64, err error) {
	var st syscall.Statfs_t
	if err := syscall.Statfs(path, &st); err != nil {
		return 0, 0, err
	}
	size := int64(st.Bsize)

	return int64(st.Blocks) * size, int64(st.Bavail) * size, nil
}

func loadAverage() float64 {
	raw, err := os.ReadFile("/proc/loadavg")
	if err != nil {
		return 0
	}
	fields := strings.Fields(string(raw))
	if len(fields) == 0 {
		return 0
	}
	load, _ := strconv.ParseFloat(fields[0], 64)

	return load
}

// cpuJiffies reads the aggregate CPU line from /proc/stat. iowait counts as
// idle: a node waiting on a disk is not a node that is busy computing.
func cpuJiffies() (idle, total uint64, err error) {
	raw, err := os.ReadFile("/proc/stat")
	if err != nil {
		return 0, 0, err
	}

	line, _, _ := strings.Cut(string(raw), "\n")
	fields := strings.Fields(line)
	if len(fields) < 5 || fields[0] != "cpu" {
		return 0, 0, os.ErrInvalid
	}

	for i, field := range fields[1:] {
		v, err := strconv.ParseUint(field, 10, 64)
		if err != nil {
			continue
		}
		total += v
		// Columns 4 and 5 of the cpu line are idle and iowait.
		if i == 3 || i == 4 {
			idle += v
		}
	}

	return idle, total, nil
}

// ------------------------------------------------------------------- helpers

func clamp(s string, n int) string {
	if len(s) <= n {
		return s
	}

	return s[:n]
}

func round1(f float64) float64 {
	return float64(int64(f*10+0.5)) / 10
}
