// Package linuxgsm drives a LinuxGSM-managed server.
//
// LinuxGSM already knows how to install, update, monitor and back up more than
// 130 games. Wrapping it means GameMGR inherits that catalogue rather than
// reimplementing it one template at a time, which is the single cheapest way to
// support a long tail of games nobody would otherwise get round to.
//
// The important design point: LinuxGSM runs its own tmux session and has its
// own idea of whether a server is up. This driver therefore drives the control
// script and does NOT wrap it in a second session of its own. Two things
// supervising the same process fight each other, and the loser is the person
// whose server keeps getting restarted.
package linuxgsm

import (
	"bufio"
	"context"
	"fmt"
	"io"
	"os"
	"os/exec"
	"os/user"
	"path/filepath"
	"strconv"
	"strings"
	"syscall"
	"time"

	"github.com/scriptgain/gamemgr-node/internal/runtime"
	"github.com/scriptgain/gamemgr-node/internal/runtime/store"
	"github.com/scriptgain/gamemgr-node/internal/supervise"
)

// InstallerURL is where the LinuxGSM bootstrap script comes from.
const InstallerURL = "https://linuxgsm.sh"

type Driver struct {
	store.Store

	sup *supervise.Supervisor
	// The account LinuxGSM runs as when this daemon is root. LinuxGSM refuses
	// to run as root, and it is right to: it downloads and executes game code.
	runAs *credential
}

type credential struct {
	name string
	uid  uint32
	gid  uint32
}

func New(root string, sup *supervise.Supervisor) *Driver {
	return &Driver{Store: store.New(root), sup: sup, runAs: unprivilegedUser()}
}

// unprivilegedUser finds an account to drop to. Only relevant when the daemon
// itself is root; a daemon already running as a normal user just uses its own
// identity, which is the expected setup on a real node.
func unprivilegedUser() *credential {
	if os.Geteuid() != 0 {
		return nil
	}
	for _, name := range []string{"gamemgr", "linuxgsm", "nobody"} {
		u, err := user.Lookup(name)
		if err != nil {
			continue
		}
		uid, err1 := strconv.Atoi(u.Uid)
		gid, err2 := strconv.Atoi(u.Gid)
		if err1 != nil || err2 != nil || uid == 0 {
			continue
		}

		return &credential{name: name, uid: uint32(uid), gid: uint32(gid)}
	}

	return nil
}

// prepare applies the unprivileged identity and the environment LinuxGSM needs.
func (d *Driver) prepare(cmd *exec.Cmd) {
	cmd.Env = append(os.Environ(), "TERM=xterm", "LGSM_GITHUBUSER=GameServerManagers")

	if d.runAs == nil {
		return
	}

	cmd.SysProcAttr = &syscall.SysProcAttr{
		Credential: &syscall.Credential{Uid: d.runAs.uid, Gid: d.runAs.gid},
	}
	// Without a HOME it belongs to, LinuxGSM writes its config into whatever
	// root's home is and then cannot read it back as the game user.
	cmd.Env = append(cmd.Env, "HOME=/home/"+d.runAs.name, "USER="+d.runAs.name)
}

// own hands the server directory to the account LinuxGSM will run as. Without
// it every download fails on permissions, having been created by root.
func (d *Driver) own(dir string) error {
	if d.runAs == nil {
		return nil
	}

	return filepath.Walk(dir, func(path string, _ os.FileInfo, err error) error {
		if err != nil {
			return err
		}

		return os.Chown(path, int(d.runAs.uid), int(d.runAs.gid))
	})
}

func (d *Driver) Name() string { return "linuxgsm" }

// Available checks tmux, because that is the dependency people actually lack:
// LinuxGSM holds every server's console in a tmux session and will not run
// without it.
func (d *Driver) Available(context.Context) (bool, string) {
	for _, binary := range []string{"tmux", "bash", "curl"} {
		if _, err := exec.LookPath(binary); err != nil {
			return false, binary + " not installed, LinuxGSM cannot run without it"
		}
	}

	return true, "LinuxGSM prerequisites present"
}

// script is the per-game control script LinuxGSM generates, for example
// ./vhserver or ./mcserver.
func (d *Driver) script(s runtime.Server) string {
	return "./" + s.LGSMShortname
}

func (d *Driver) shortname(s runtime.Server) (string, error) {
	name := strings.TrimSpace(s.LGSMShortname)
	if name == "" {
		return "", fmt.Errorf("this template has no LinuxGSM shortname, so there is nothing to install")
	}
	// The shortname becomes a filename and a command, so anything exotic in it
	// is a command injection rather than a typo.
	for _, r := range name {
		if !(r >= 'a' && r <= 'z') && !(r >= '0' && r <= '9') {
			return "", fmt.Errorf("%q is not a valid LinuxGSM shortname", name)
		}
	}

	return name, nil
}

// ----------------------------------------------------------------- lifecycle

func (d *Driver) Install(ctx context.Context, s runtime.Server, w io.Writer) error {
	name, err := d.shortname(s)
	if err != nil {
		return err
	}

	dir, err := d.EnsureDir(s)
	if err != nil {
		return err
	}
	fmt.Fprintf(w, "[gamemgr] data directory %s\n", dir)

	if d.runAs != nil {
		fmt.Fprintf(w, "[gamemgr] running LinuxGSM as %s, since it refuses to run as root\n", d.runAs.name)
		if err := d.own(dir); err != nil {
			return fmt.Errorf("hand the directory to %s: %w", d.runAs.name, err)
		}
	}

	fmt.Fprintln(w, "[linuxgsm] fetching the installer")
	if err := d.run(ctx, dir, w, "bash", "-c",
		"curl -fsSL "+InstallerURL+" -o linuxgsm.sh && chmod +x linuxgsm.sh"); err != nil {
		return err
	}

	fmt.Fprintf(w, "[linuxgsm] generating the %s control script\n", name)
	if err := d.run(ctx, dir, w, "bash", "./linuxgsm.sh", name); err != nil {
		return err
	}

	fmt.Fprintf(w, "[linuxgsm] auto-install %s, this downloads the game and can take a while\n", name)
	if err := d.run(ctx, dir, w, "bash", d.script(s), "auto-install"); err != nil {
		return err
	}

	fmt.Fprintln(w, "[gamemgr] install complete")

	return nil
}

func (d *Driver) Update(ctx context.Context, s runtime.Server, w io.Writer) error {
	if _, err := d.shortname(s); err != nil {
		return err
	}

	fmt.Fprintln(w, "[linuxgsm] update")

	return d.run(ctx, d.Dir(s), w, "bash", d.script(s), "update")
}

// run executes a LinuxGSM command and streams its output. Everything LinuxGSM
// does is a long-running shell script, so the output goes out line by line
// rather than being collected and dumped at the end.
func (d *Driver) run(ctx context.Context, dir string, w io.Writer, name string, args ...string) error {
	cmd := exec.CommandContext(ctx, name, args...)
	cmd.Dir = dir
	d.prepare(cmd)

	pipe, err := cmd.StdoutPipe()
	if err != nil {
		return err
	}
	cmd.Stderr = cmd.Stdout

	if err := cmd.Start(); err != nil {
		return err
	}

	scanner := bufio.NewScanner(pipe)
	scanner.Buffer(make([]byte, 0, 64<<10), 1<<20)
	for scanner.Scan() {
		line := strings.TrimRight(supervise.StripANSI(scanner.Text()), " \t")
		if line == "" {
			continue
		}
		fmt.Fprintf(w, "[linuxgsm] %s\n", line)
		if f, ok := w.(interface{ Flush() }); ok {
			f.Flush()
		}
	}

	if err := cmd.Wait(); err != nil {
		return fmt.Errorf("linuxgsm %s failed: %w", strings.Join(args, " "), err)
	}

	return nil
}

// ------------------------------------------------------------------- control

func (d *Driver) Start(ctx context.Context, s runtime.Server) error {
	if _, err := d.shortname(s); err != nil {
		return err
	}
	// Ownership is re-applied on every start, not just at install: a backup
	// restore or a file written through the panel lands as root and LinuxGSM
	// then cannot read its own configuration.
	if err := d.own(d.Dir(s)); err != nil {
		return err
	}

	return d.control(ctx, s, "start")
}

func (d *Driver) Stop(ctx context.Context, s runtime.Server) error {
	if _, err := d.shortname(s); err != nil {
		return err
	}

	// LinuxGSM's own stop sends the game's stop command and waits, which is
	// exactly the graceful behaviour wanted here, so it is not second guessed.
	return d.control(ctx, s, "stop")
}

func (d *Driver) Restart(ctx context.Context, s runtime.Server) error {
	return d.control(ctx, s, "restart")
}

// Kill is LinuxGSM's own force stop. Reaching past it to the process would
// leave its lockfile behind, and it would then believe the server is still up.
func (d *Driver) Kill(ctx context.Context, s runtime.Server) error {
	return d.control(ctx, s, "force-stop")
}

func (d *Driver) control(ctx context.Context, s runtime.Server, action string) error {
	cmd := exec.CommandContext(ctx, "bash", d.script(s), action)
	cmd.Dir = d.Dir(s)
	d.prepare(cmd)

	out, err := cmd.CombinedOutput()
	if err != nil {
		return fmt.Errorf("linuxgsm %s: %s", action, firstMeaningfulLine(string(out), err))
	}

	return nil
}

// Command goes through LinuxGSM's own send, which knows how to reach the game
// inside the tmux session it created.
func (d *Driver) Command(ctx context.Context, s runtime.Server, command string) error {
	if !d.running(ctx, s) {
		return fmt.Errorf("the server is not running, so there is nowhere to send that")
	}

	cmd := exec.CommandContext(ctx, "bash", d.script(s), "send", command)
	cmd.Dir = d.Dir(s)
	d.prepare(cmd)

	if out, err := cmd.CombinedOutput(); err != nil {
		// Not every LinuxGSM game has send. Falling back to typing into its
		// session directly covers the ones that do not.
		if fallbackErr := d.sendKeys(ctx, s, command); fallbackErr != nil {
			return fmt.Errorf("send: %s", firstMeaningfulLine(string(out), err))
		}
	}

	return nil
}

func (d *Driver) sendKeys(ctx context.Context, s runtime.Server, command string) error {
	session := s.LGSMShortname
	if out, err := d.tmuxAs(ctx, "send-keys", "-t", session, "-l", command).CombinedOutput(); err != nil {
		return fmt.Errorf("%s", firstMeaningfulLine(string(out), err))
	}

	return d.tmuxAs(ctx, "send-keys", "-t", session, "Enter").Run()
}

func (d *Driver) Destroy(ctx context.Context, s runtime.Server) error {
	if s.LGSMShortname != "" {
		_ = d.control(ctx, s, "force-stop")
	}

	return d.DestroyDir(s)
}

// ---------------------------------------------------------------------- state

// tmuxAs runs a tmux command as the account LinuxGSM runs under.
//
// This is load-bearing, not tidiness. tmux keeps its socket under
// /tmp/tmux-<uid>/, so a root process asking "is session ts3server running"
// looks in /tmp/tmux-0/ and finds nothing, while the server is happily running
// in the game user's own tmux. The panel then reports a healthy server as
// offline, and the watchdog would restart something that never stopped.
func (d *Driver) tmuxAs(ctx context.Context, args ...string) *exec.Cmd {
	cmd := exec.CommandContext(ctx, "tmux", args...)
	d.prepare(cmd)

	return cmd
}

// running does NOT ask LinuxGSM. Its own status call spawns a shell and takes
// the better part of a second, which is far too slow for something the panel
// polls for every server on the node.
//
// It also does not rely on tmux alone. Most LinuxGSM games live in a tmux
// session, but some ship their own daemonising start script (TeamSpeak is one),
// and those have no session to ask about. Finding the process running out of
// the server's directory covers both.
func (d *Driver) running(ctx context.Context, s runtime.Server) bool {
	if s.LGSMShortname == "" {
		return false
	}
	if d.tmuxAs(ctx, "has-session", "-t", s.LGSMShortname).Run() == nil {
		return true
	}

	return supervise.ProcessIn(d.Dir(s)) > 0
}

// pid finds the running game. LinuxGSM's lock files hold a start timestamp
// rather than a pid, despite the name, so they cannot be used for this.
func (d *Driver) pid(ctx context.Context, s runtime.Server) int {
	if out, err := d.tmuxAs(ctx, "list-panes", "-t", s.LGSMShortname, "-F", "#{pane_pid}").Output(); err == nil {
		if pid, err := strconv.Atoi(strings.TrimSpace(strings.SplitN(string(out), "\n", 2)[0])); err == nil && pid > 0 {
			return pid
		}
	}

	return supervise.ProcessIn(d.Dir(s))
}

func (d *Driver) Stats(ctx context.Context, s runtime.Server) (runtime.Stats, error) {
	out := runtime.Stats{
		State:     runtime.StateOffline,
		MemoryCap: s.MemoryMiB,
		DiskMiB:   d.DiskUsageMiB(s),
		SampledAt: time.Now(),
	}

	if !d.running(ctx, s) {
		return out, nil
	}

	out.State = runtime.StateRunning
	if pid := d.pid(ctx, s); pid > 0 {
		usage := d.sup.UsageOf(s.LGSMShortname, pid)
		out.CPU = usage.CPUPercent
		out.MemoryMiB = usage.MemoryMiB
		out.Uptime = usage.UptimeSec
	}

	return out, nil
}

// Backlog reads LinuxGSM's console capture once and returns.
func (d *Driver) Backlog(_ context.Context, s runtime.Server, n int) ([]string, error) {
	lines, err := supervise.TailFile(d.consoleLog(s), n)
	if err != nil || len(lines) > 0 {
		return lines, err
	}

	return []string{"[gamemgr] no LinuxGSM console log yet. It appears the first time the server starts."}, nil
}

func (d *Driver) consoleLog(s runtime.Server) string {
	return filepath.Join(d.Dir(s), "log", "console", s.LGSMShortname+"-console.log")
}

// Logs reads LinuxGSM's own console capture rather than making a second one.
func (d *Driver) Logs(ctx context.Context, s runtime.Server, tail int, w io.Writer) error {
	path := d.consoleLog(s)

	if _, err := os.Stat(path); err != nil {
		fmt.Fprintln(w, "[gamemgr] no LinuxGSM console log yet. It appears the first time the server starts.")

		return supervise.FollowFile(ctx, path, tail, w)
	}

	return supervise.FollowFile(ctx, path, tail, w)
}

func firstMeaningfulLine(out string, err error) string {
	for _, line := range strings.Split(supervise.StripANSI(out), "\n") {
		line = strings.TrimSpace(line)
		if line != "" {
			return line
		}
	}

	return err.Error()
}
