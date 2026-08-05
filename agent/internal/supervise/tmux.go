// Package supervise runs native game servers inside tmux sessions.
//
// Why tmux rather than the daemon owning the process directly: the session
// belongs to tmux, not to us, so restarting or crashing the daemon does not
// take every game server on the node down with it. On the next start the
// daemon simply finds the sessions still there and carries on. It is also the
// mechanism LinuxGSM already uses, so both native runtimes work the same way
// and there is one answer to "how is a native server run".
//
// The cost is a hard dependency on tmux, and that stats and resource limits
// have to be done by hand. Both are handled here.
package supervise

import (
	"bufio"
	"context"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"regexp"
	"sort"
	"strconv"
	"strings"
	"sync"
	"time"
)

// ConsoleFile is where a supervised server's output is captured. Live output
// goes to whoever is watching; this is what makes the log readable after the
// fact.
const ConsoleFile = "gamemgr-console.log"

// launcherFile holds the startup command, so it never has to survive being
// quoted through tmux.
const launcherFile = ".gamemgr-start.sh"

// RuntimeDir is where the daemon keeps its own working files for a server.
//
// Deliberately a sibling of the server directory rather than inside it. Both of
// these files used to sit next to the customer's world data, which put two
// mystery entries in their file manager and swept the console log, routinely the
// largest thing on a busy server, into every backup. Sibling directories
// starting with a dot are the same shape the store already uses for .backups
// and .restore-safety, and nothing lists or archives them.
func RuntimeDir(dir string) string {
	return filepath.Join(filepath.Dir(dir), ".runtime", filepath.Base(dir))
}

type Supervisor struct {
	mu sync.Mutex
	// Last CPU sample per session, so a percentage can be derived from two
	// readings of a counter that only ever goes up.
	samples map[string]cpuSample
	cgroup  *cgroupManager
}

type cpuSample struct {
	jiffies uint64
	at      time.Time
}

func New() *Supervisor {
	return &Supervisor{
		samples: map[string]cpuSample{},
		cgroup:  newCgroupManager(),
	}
}

// Available reports whether this node can supervise anything at all.
func (s *Supervisor) Available() (bool, string) {
	if _, err := exec.LookPath("tmux"); err != nil {
		return false, "tmux not installed, native runtimes cannot hold a console"
	}

	return true, "tmux available" + s.cgroup.describe()
}

// LimitsEnforced reports whether resource limits are real on this node, so the
// panel can say so rather than promising a cap that nothing enforces.
func (s *Supervisor) LimitsEnforced() bool {
	return s.cgroup.usable
}

func Session(uuid string) string {
	clean := strings.ReplaceAll(uuid, "-", "")
	if len(clean) > 12 {
		clean = clean[:12]
	}

	return "gamemgr-" + clean
}

// -------------------------------------------------------------------- control

func tmux(ctx context.Context, args ...string) (string, error) {
	cmd := exec.CommandContext(ctx, "tmux", args...)
	out, err := cmd.CombinedOutput()

	return strings.TrimSpace(string(out)), err
}

func (s *Supervisor) Running(ctx context.Context, session string) bool {
	_, err := tmux(ctx, "has-session", "-t", session)

	return err == nil
}

// Start launches a command in a detached session and begins capturing output.
//
// Limits are applied after the session exists, because the pid does not exist
// before it. That leaves a sub-second window where a server is unconstrained;
// the alternative is launching through a cgroup-aware wrapper, which would make
// tmux's own bookkeeping the thing inside the cgroup rather than the game.
func (s *Supervisor) Start(ctx context.Context, session, dir, command string, memoryMiB int64, cpuPercent int) error {
	if s.Running(ctx, session) {
		return nil
	}
	if strings.TrimSpace(command) == "" {
		return fmt.Errorf("this template has no startup command")
	}
	if err := os.MkdirAll(dir, 0o755); err != nil {
		return err
	}

	// The startup command goes to a file rather than onto a command line. It
	// comes from a template and routinely contains quotes, $ and pipes, and
	// sending that through tmux send-keys means quoting it correctly twice.
	// Written verbatim, with NO exec prefix. A startup command is routinely
	// several statements separated by semicolons, and "exec a; b; c" replaces
	// the shell with a and silently drops the rest: the server appears to boot
	// and is gone a moment later. The exec belongs on the invocation of this
	// script, not inside it.
	runtimeDir := RuntimeDir(dir)
	if err := os.MkdirAll(runtimeDir, 0o755); err != nil {
		return err
	}

	launcher := filepath.Join(runtimeDir, launcherFile)
	if err := os.WriteFile(launcher, []byte("#!/bin/sh\n"+command+"\n"), 0o755); err != nil {
		return err
	}

	// Started as a bare shell, NOT as the game. pipe-pane only captures output
	// from the moment it is enabled, so launching the server in the same breath
	// loses everything it prints before the pipe is up: on a fast boot that is
	// the entire startup log. Shell first, pipe second, game third.
	if out, err := tmux(ctx, "new-session", "-d", "-s", session, "-c", dir, "/bin/sh"); err != nil {
		return fmt.Errorf("start session: %s", firstLine(out, err))
	}

	// Terminal echo off before capture starts, so the launch line typed into the
	// pane does not appear in the console as though the server printed it.
	if _, err := tmux(ctx, "send-keys", "-t", session, "-l", "stty -echo"); err == nil {
		_, _ = tmux(ctx, "send-keys", "-t", session, "Enter")
	}

	logPath := filepath.Join(runtimeDir, ConsoleFile)
	// -o means "only if not already piping", so a restart does not stack up
	// duplicate writers on the same file.
	if out, err := tmux(ctx, "pipe-pane", "-o", "-t", session, "cat >> "+shellQuote(logPath)); err != nil {
		return fmt.Errorf("capture console: %s", firstLine(out, err))
	}

	// exec replaces the shell, so the process tree stays clean and the pane dies
	// with the game rather than dropping back to a prompt that looks like a
	// running server.
	if out, err := tmux(ctx, "send-keys", "-t", session, "-l", "exec /bin/sh "+shellQuote(launcher)); err != nil {
		return fmt.Errorf("launch: %s", firstLine(out, err))
	}
	if out, err := tmux(ctx, "send-keys", "-t", session, "Enter"); err != nil {
		return fmt.Errorf("launch: %s", firstLine(out, err))
	}

	// The pane's pid is the shell until exec has happened, which is near
	// instant but not instant. A brief wait means the cgroup lands on the game.
	time.Sleep(300 * time.Millisecond)
	if pid, err := s.PID(ctx, session); err == nil && pid > 0 {
		s.cgroup.apply(session, pid, memoryMiB, cpuPercent)
	}

	return nil
}

// Command types a line into the session, which is how a console command reaches
// a native server: its stdin is the pty tmux is holding.
func (s *Supervisor) Command(ctx context.Context, session, command string) error {
	if !s.Running(ctx, session) {
		return fmt.Errorf("the server is not running, so there is nowhere to send that")
	}
	// -l sends the text literally, so a command containing ; or $ is not
	// interpreted by tmux as a key name.
	if out, err := tmux(ctx, "send-keys", "-t", session, "-l", strings.TrimRight(command, "\r\n")); err != nil {
		return fmt.Errorf("send command: %s", firstLine(out, err))
	}
	_, err := tmux(ctx, "send-keys", "-t", session, "Enter")

	return err
}

// Stop asks nicely first. A game server killed without being allowed to save is
// how somebody loses a day of building, so the stop command gets a real chance
// before anything is torn down.
func (s *Supervisor) Stop(ctx context.Context, session, stopCommand string, grace time.Duration) error {
	if !s.Running(ctx, session) {
		return nil
	}

	if cmd := strings.TrimSpace(stopCommand); cmd != "" && cmd != "^C" {
		if err := s.Command(ctx, session, cmd); err == nil {
			if s.waitForExit(ctx, session, grace) {
				s.forget(session)

				return nil
			}
		}
	}

	return s.Kill(ctx, session)
}

func (s *Supervisor) Kill(ctx context.Context, session string) error {
	if !s.Running(ctx, session) {
		return nil
	}
	_, err := tmux(ctx, "kill-session", "-t", session)
	s.forget(session)

	return err
}

func (s *Supervisor) waitForExit(ctx context.Context, session string, within time.Duration) bool {
	deadline := time.Now().Add(within)
	for time.Now().Before(deadline) {
		if !s.Running(ctx, session) {
			return true
		}
		select {
		case <-ctx.Done():
			return false
		case <-time.After(time.Second):
		}
	}

	return false
}

func (s *Supervisor) forget(session string) {
	s.mu.Lock()
	delete(s.samples, session)
	s.mu.Unlock()
	s.cgroup.remove(session)
}

// ------------------------------------------------------------------ processes

// PID returns the pane's shell process.
func (s *Supervisor) PID(ctx context.Context, session string) (int, error) {
	out, err := tmux(ctx, "list-panes", "-t", session, "-F", "#{pane_pid}")
	if err != nil {
		return 0, err
	}
	line := strings.TrimSpace(strings.SplitN(out, "\n", 2)[0])

	return strconv.Atoi(line)
}

// tree is the pane shell plus everything under it. A game server is usually a
// child of the shell, and often a grandchild behind a launcher script, so
// stats have to cover the whole tree or they report the shell's idle zero.
func tree(root int) []int {
	children := map[int][]int{}

	entries, err := os.ReadDir("/proc")
	if err != nil {
		return []int{root}
	}
	for _, entry := range entries {
		pid, err := strconv.Atoi(entry.Name())
		if err != nil {
			continue
		}
		ppid := parentOf(pid)
		if ppid > 0 {
			children[ppid] = append(children[ppid], pid)
		}
	}

	var out []int
	var walk func(int)
	seen := map[int]bool{}
	walk = func(pid int) {
		if seen[pid] {
			return
		}
		seen[pid] = true
		out = append(out, pid)
		for _, child := range children[pid] {
			walk(child)
		}
	}
	walk(root)

	return out
}

func parentOf(pid int) int {
	data, err := os.ReadFile(fmt.Sprintf("/proc/%d/stat", pid))
	if err != nil {
		return 0
	}
	// The comm field can contain spaces and brackets, so everything before the
	// final ')' is skipped rather than split on.
	closing := strings.LastIndex(string(data), ")")
	if closing < 0 {
		return 0
	}
	fields := strings.Fields(string(data)[closing+1:])
	if len(fields) < 2 {
		return 0
	}
	ppid, _ := strconv.Atoi(fields[1])

	return ppid
}

// cpuJiffies is utime + stime for a process.
func cpuJiffies(pid int) uint64 {
	data, err := os.ReadFile(fmt.Sprintf("/proc/%d/stat", pid))
	if err != nil {
		return 0
	}
	closing := strings.LastIndex(string(data), ")")
	if closing < 0 {
		return 0
	}
	fields := strings.Fields(string(data)[closing+1:])
	// After the comm field: state is [0], so utime is [11] and stime is [12].
	if len(fields) < 13 {
		return 0
	}
	utime, _ := strconv.ParseUint(fields[11], 10, 64)
	stime, _ := strconv.ParseUint(fields[12], 10, 64)

	return utime + stime
}

// rssKiB is resident memory. smaps_rollup is preferred because it reports Pss,
// which does not double count shared pages across a process tree; VmRSS does,
// and a Java server with many threads then looks far heavier than it is.
func rssKiB(pid int) uint64 {
	if file, err := os.Open(fmt.Sprintf("/proc/%d/smaps_rollup", pid)); err == nil {
		defer file.Close()
		scanner := bufio.NewScanner(file)
		for scanner.Scan() {
			if strings.HasPrefix(scanner.Text(), "Pss:") {
				fields := strings.Fields(scanner.Text())
				if len(fields) >= 2 {
					value, _ := strconv.ParseUint(fields[1], 10, 64)

					return value
				}
			}
		}
	}

	data, err := os.ReadFile(fmt.Sprintf("/proc/%d/status", pid))
	if err != nil {
		return 0
	}
	for _, line := range strings.Split(string(data), "\n") {
		if strings.HasPrefix(line, "VmRSS:") {
			fields := strings.Fields(line)
			if len(fields) >= 2 {
				value, _ := strconv.ParseUint(fields[1], 10, 64)

				return value
			}
		}
	}

	return 0
}

type Usage struct {
	CPUPercent float64
	MemoryMiB  int64
	UptimeSec  int64
}

// Usage samples the whole process tree. CPU comes from the delta against the
// previous call, so the first call after a start reports zero rather than a
// meaningless figure derived from the process's whole lifetime.
func (s *Supervisor) Usage(ctx context.Context, session string) Usage {
	root, err := s.PID(ctx, session)
	if err != nil || root <= 0 {
		return Usage{}
	}

	pids := tree(root)

	var jiffies, memKiB uint64
	for _, pid := range pids {
		jiffies += cpuJiffies(pid)
		memKiB += rssKiB(pid)
	}

	now := time.Now()
	s.mu.Lock()
	previous, seen := s.samples[session]
	s.samples[session] = cpuSample{jiffies: jiffies, at: now}
	s.mu.Unlock()

	usage := Usage{MemoryMiB: int64(memKiB / 1024)}

	if seen && jiffies >= previous.jiffies {
		elapsed := now.Sub(previous.at).Seconds()
		if elapsed > 0 {
			ticks := float64(clockTicks())
			usage.CPUPercent = round2(float64(jiffies-previous.jiffies) / ticks / elapsed * 100)
		}
	}

	if started, err := startTime(root); err == nil {
		usage.UptimeSec = int64(time.Since(started).Seconds())
	}

	return usage
}

// UsageOf samples a process tree the caller found itself, keyed by a name of
// its choosing so the CPU delta still works across calls. LinuxGSM owns its own
// sessions, so its pid does not come from us.
func (s *Supervisor) UsageOf(key string, root int) Usage {
	if root <= 0 {
		return Usage{}
	}

	var jiffies, memKiB uint64
	for _, pid := range tree(root) {
		jiffies += cpuJiffies(pid)
		memKiB += rssKiB(pid)
	}

	now := time.Now()
	s.mu.Lock()
	previous, seen := s.samples[key]
	s.samples[key] = cpuSample{jiffies: jiffies, at: now}
	s.mu.Unlock()

	usage := Usage{MemoryMiB: int64(memKiB / 1024)}
	if seen && jiffies >= previous.jiffies {
		if elapsed := now.Sub(previous.at).Seconds(); elapsed > 0 {
			usage.CPUPercent = round2(float64(jiffies-previous.jiffies) / float64(clockTicks()) / elapsed * 100)
		}
	}
	if started, err := startTime(root); err == nil {
		usage.UptimeSec = int64(time.Since(started).Seconds())
	}

	return usage
}

func startTime(pid int) (time.Time, error) {
	info, err := os.Stat(fmt.Sprintf("/proc/%d", pid))
	if err != nil {
		return time.Time{}, err
	}

	return info.ModTime(), nil
}

// clockTicks is USER_HZ, which is 100 on every Linux this will realistically
// run on. Reading it properly needs cgo, and being wrong here only scales a
// percentage rather than breaking anything.
func clockTicks() int { return 100 }

// -------------------------------------------------------------------- console

var ansi = regexp.MustCompile(`\x1b\[[0-9;?]*[a-zA-Z]|\x1b\][^\a]*\a|\r`)

// StripANSI removes terminal escape sequences. tmux and LinuxGSM both capture
// what was displayed, so without this the panel shows colour codes and cursor
// movement as literal rubbish.
func StripANSI(s string) string { return ansi.ReplaceAllString(s, "") }

// Tail returns the last n lines of the captured console, with terminal escape
// sequences stripped. tmux captures what was displayed, so without this the
// panel shows colour codes and cursor movement as literal rubbish.
func Tail(dir string, n int) ([]string, error) {
	return tailFile(filepath.Join(RuntimeDir(dir), ConsoleFile), n)
}

// TailFile is Tail against an explicit path, for a runtime that keeps its own
// console log rather than using the supervisor's capture.
func TailFile(path string, n int) ([]string, error) {
	return tailFile(path, n)
}

func tailFile(path string, n int) ([]string, error) {
	file, err := os.Open(path)
	if err != nil {
		if os.IsNotExist(err) {
			return nil, nil
		}

		return nil, err
	}
	defer file.Close()

	scanner := bufio.NewScanner(file)
	scanner.Buffer(make([]byte, 0, 64<<10), 1<<20)

	ring := make([]string, 0, n)
	for scanner.Scan() {
		line := strings.TrimRight(ansi.ReplaceAllString(scanner.Text(), ""), " \t")
		if line == "" {
			continue
		}
		ring = append(ring, line)
		if len(ring) > n {
			ring = ring[1:]
		}
	}

	return ring, scanner.Err()
}

// Sessions lists everything this daemon is supervising, which is what makes a
// daemon restart a non-event: the sessions were never ours to lose.
func Sessions(ctx context.Context) []string {
	out, err := tmux(ctx, "list-sessions", "-F", "#{session_name}")
	if err != nil {
		return nil
	}

	var found []string
	for _, line := range strings.Split(out, "\n") {
		line = strings.TrimSpace(line)
		if strings.HasPrefix(line, "gamemgr-") {
			found = append(found, line)
		}
	}
	sort.Strings(found)

	return found
}

// ------------------------------------------------------------------- helpers

func firstLine(out string, err error) string {
	if out == "" {
		return err.Error()
	}

	return strings.SplitN(out, "\n", 2)[0]
}

func shellQuote(s string) string {
	return "'" + strings.ReplaceAll(s, "'", `'\''`) + "'"
}

func round2(f float64) float64 {
	return float64(int64(f*100+0.5)) / 100
}

// ProcessIn finds the game process running out of a server's directory.
//
// Needed because "is it running" has no single answer across LinuxGSM's
// catalogue: most games are held in a tmux session, but some ship their own
// daemonising start script and TeamSpeak is one of them. Asking tmux about
// those reports a healthy server as offline, and the watchdog would then
// restart something that never stopped.
//
// Three ways in, in order of reliability, because the first two need
// CAP_SYS_PTRACE and Docker drops that by default: a daemon in a container can
// see a process in /proc and still not be allowed to read where it came from.
// The command line is world readable and almost always carries the path.
func ProcessIn(dir string) int {
	entries, err := os.ReadDir("/proc")
	if err != nil {
		return 0
	}

	self := os.Getpid()
	fallback := 0

	for _, entry := range entries {
		pid, err := strconv.Atoi(entry.Name())
		if err != nil || pid == self {
			continue
		}

		// An executable inside the directory is the game itself. Preferred,
		// because a shell merely sitting in the directory is not a server.
		if exe, err := os.Readlink(fmt.Sprintf("/proc/%d/exe", pid)); err == nil && under(exe, dir) {
			return pid
		}
		if cwd, err := os.Readlink(fmt.Sprintf("/proc/%d/cwd", pid)); err == nil && under(cwd, dir) && fallback == 0 {
			fallback = pid
		}

		if fallback == 0 && commandLineMentions(pid, dir) {
			fallback = pid
		}
	}

	return fallback
}

// commandLineMentions is the no-privilege path: a game launched by LinuxGSM
// almost always has its own directory somewhere in its arguments, whether as a
// config path, a working directory or a log target.
func commandLineMentions(pid int, dir string) bool {
	data, err := os.ReadFile(fmt.Sprintf("/proc/%d/cmdline", pid))
	if err != nil || len(data) == 0 {
		return false
	}
	line := strings.ReplaceAll(string(data), "\x00", " ")

	return strings.Contains(line, filepath.Clean(dir))
}

func under(path, dir string) bool {
	if path == "" || dir == "" {
		return false
	}
	clean := filepath.Clean(path)
	base := filepath.Clean(dir)

	return clean == base || strings.HasPrefix(clean, base+string(filepath.Separator))
}
