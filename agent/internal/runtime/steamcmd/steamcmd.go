// Package steamcmd installs and runs a game server natively, with no container
// in the way.
//
// This is the runtime Pterodactyl cannot offer at all, and it is not a novelty:
// Source and Unreal servers can misbehave under a container network namespace,
// anti-cheat occasionally objects to containers, and a node on bare metal pays
// the container overhead for nothing. steamcmd downloads the app into the
// server's own directory and tmux holds the console.
package steamcmd

import (
	"bufio"
	"context"
	"fmt"
	"io"
	"log"
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

type Driver struct {
	// Embedded, so files, backups and the path traversal guard are the shared
	// implementation rather than a second copy.
	store.Store

	binary string
	sup    *supervise.Supervisor
	// The account game files are downloaded as when this daemon is root. The
	// LinuxGSM driver has always had this; steamcmd never did, and the
	// consequence was silent: EnsureDir creates the server directory as root,
	// steamcmd runs unprivileged, and the download has nowhere to write. On a
	// real node it looked like a hang, with an empty data directory, no error,
	// and steamcmd sitting there burning a few seconds of CPU.
	runAs *credential
}

type credential struct {
	name string
	uid  uint32
	gid  uint32
}

func New(binary, root string, sup *supervise.Supervisor) *Driver {
	if binary == "" {
		binary = "steamcmd"
	}

	return &Driver{Store: store.New(root), binary: binary, sup: sup, runAs: unprivilegedUser()}
}

// unprivilegedUser mirrors the LinuxGSM driver: only relevant when the daemon
// is root, and a daemon already running as a normal user keeps its own identity.
func unprivilegedUser() *credential {
	if os.Geteuid() != 0 {
		return nil
	}
	for _, name := range []string{"gamemgr", "steam", "nobody"} {
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

// own hands the server directory to the account steamcmd downloads as.
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

func (d *Driver) Name() string { return "steamcmd" }

func (d *Driver) Available(context.Context) (bool, string) {
	if _, err := exec.LookPath(d.binary); err != nil {
		return false, "steamcmd not found on PATH"
	}
	ok, detail := d.sup.Available()
	if !ok {
		return false, detail
	}

	return true, "steamcmd ready, " + detail
}

// ----------------------------------------------------------------- lifecycle

func (d *Driver) Install(ctx context.Context, s runtime.Server, w io.Writer) error {
	dir, err := d.EnsureDir(s)
	if err != nil {
		return err
	}
	fmt.Fprintf(w, "[gamemgr] data directory %s\n", dir)

	// Before anything downloads: the directory was created by root and
	// steamcmd runs unprivileged, so without this the install writes nothing
	// and reports nothing.
	if err := d.own(dir); err != nil {
		return fmt.Errorf("hand the data directory to %s: %w", d.runAs.name, err)
	}

	if s.SteamAppID <= 0 {
		return fmt.Errorf("this template has no Steam app id, so there is nothing to download")
	}

	return d.runSteamCMD(ctx, s, dir, w)
}

// Update is the same app_update as the install. Steam is incremental, so this
// downloads only what changed, which is exactly what the auto-update schedule
// is for on a game that patches constantly.
func (d *Driver) Update(ctx context.Context, s runtime.Server, w io.Writer) error {
	dir, err := d.EnsureDir(s)
	if err != nil {
		return err
	}
	if s.SteamAppID <= 0 {
		return fmt.Errorf("this template has no Steam app id, so there is nothing to update")
	}

	if d.sup.Running(ctx, supervise.Session(s.UUID)) {
		return fmt.Errorf("stop the server before updating it: steamcmd will not overwrite files that are in use")
	}

	return d.runSteamCMD(ctx, s, dir, w)
}

func (d *Driver) runSteamCMD(ctx context.Context, s runtime.Server, dir string, w io.Writer) error {
	script, err := d.writeRunscript(s, dir)
	if err != nil {
		return err
	}
	// Removed whether the install succeeds or not: it holds a password.
	defer os.Remove(script)

	fmt.Fprintf(w, "[steamcmd] app_update %d%s\n", s.SteamAppID, branchNote(s.SteamBranch))

	cmd := exec.CommandContext(ctx, d.binary, "+runscript", script)
	cmd.Dir = dir
	if d.runAs != nil {
		cmd.SysProcAttr = &syscall.SysProcAttr{
			Credential: &syscall.Credential{Uid: d.runAs.uid, Gid: d.runAs.gid},
		}
		// Without this steamcmd inherits root's HOME and announces
		// "Redirecting stderr to /root/Steam/logs/stderr.txt" while running as
		// somebody who cannot write there.
		home := "/home/" + d.runAs.name
		if _, err := os.Stat(home); err != nil {
			home = dir
		}
		cmd.Env = append(os.Environ(), "HOME="+home)
	}

	pipe, err := cmd.StdoutPipe()
	if err != nil {
		return err
	}
	cmd.Stderr = cmd.Stdout

	if err := cmd.Start(); err != nil {
		return fmt.Errorf("steamcmd would not start: %w", err)
	}

	// Steam reports download progress by rewriting one line with carriage
	// returns, thousands of times. Streamed raw it buries everything useful, so
	// only meaningful changes reach the console, the same way the Docker image
	// pull is condensed.
	scanner := bufio.NewScanner(pipe)
	scanner.Buffer(make([]byte, 0, 64<<10), 1<<20)
	scanner.Split(scanLinesOrReturns)

	lastProgress := ""
	for scanner.Scan() {
		line := strings.TrimSpace(supervise.StripANSI(scanner.Text()))
		if line == "" {
			continue
		}

		if strings.Contains(line, "Update state") {
			// Keep only whole percentage points rather than every fractional
			// tick of the same download.
			progress := progressBucket(line)
			if progress == lastProgress {
				continue
			}
			lastProgress = progress
		}

		fmt.Fprintf(w, "[steamcmd] %s\n", line)
	}

	if err := cmd.Wait(); err != nil {
		return fmt.Errorf("steamcmd failed: %w", err)
	}

	fmt.Fprintln(w, "[gamemgr] install complete")

	return nil
}

// writeRunscript puts the steamcmd commands in a file and returns its path.
//
// This exists for one reason: credentials must not reach the command line.
// Passing "+login user password" as arguments puts the password in
// /proc/<pid>/cmdline, where every user on the node can read it for as long as
// the download runs, which for a large game is hours. A 0600 file readable only
// by the account steamcmd runs as keeps it off argv entirely.
func (d *Driver) writeRunscript(s runtime.Server, dir string) (string, error) {
	login := "login anonymous"
	if !s.SteamAnonymous {
		user := strings.TrimSpace(s.Environment["STEAM_USER"])
		if user == "" {
			return "", fmt.Errorf("this template needs a Steam account, so set STEAM_USER and STEAM_PASS on it")
		}
		login = "login " + user + " " + s.Environment["STEAM_PASS"]
	}

	update := "app_update " + strconv.Itoa(s.SteamAppID)
	if branch := strings.TrimSpace(s.SteamBranch); branch != "" {
		update += " -beta " + branch
		if password := strings.TrimSpace(s.Environment["STEAM_BETA_PASSWORD"]); password != "" {
			update += " -betapassword " + password
		}
	}
	update += " validate"

	body := strings.Join([]string{
		"@ShutdownOnFailedCommand 1",
		"@NoPromptForPassword 1",
		"force_install_dir " + dir,
		login,
		update,
		"quit",
		"",
	}, "\n")

	// Kept beside the server's data rather than in /tmp, so it inherits whatever
	// ownership that has and never outlives the node's storage, but in the
	// daemon's own runtime directory rather than the customer's: this file holds
	// their Steam password for as long as the install runs, and the file manager
	// must never list it.
	runtimeDir := supervise.RuntimeDir(dir)
	if err := os.MkdirAll(runtimeDir, 0o700); err != nil {
		return "", err
	}

	script := filepath.Join(runtimeDir, ".gamemgr-steamcmd")
	if err := os.WriteFile(script, []byte(body), 0o600); err != nil {
		return "", err
	}

	// steamcmd runs unprivileged, and both the directory and the file were
	// created by root, so without this it reports "Failed to load script file"
	// and exits having downloaded nothing. Ownership moves, the mode does not:
	// this file holds a Steam password for the length of the install.
	if d.runAs != nil {
		if err := os.Chown(runtimeDir, int(d.runAs.uid), int(d.runAs.gid)); err != nil {
			return "", fmt.Errorf("hand the runtime directory to %s: %w", d.runAs.name, err)
		}
		if err := os.Chown(script, int(d.runAs.uid), int(d.runAs.gid)); err != nil {
			return "", fmt.Errorf("hand the runscript to %s: %w", d.runAs.name, err)
		}
	}

	return script, nil
}

func branchNote(branch string) string {
	if strings.TrimSpace(branch) == "" {
		return ""
	}

	return " on branch " + branch
}

// progressBucket reduces "progress: 41.27 (...)" to "41", so a download emits a
// hundred lines rather than several thousand.
func progressBucket(line string) string {
	index := strings.Index(line, "progress:")
	if index < 0 {
		return line
	}
	rest := strings.TrimSpace(line[index+len("progress:"):])
	if dot := strings.IndexByte(rest, '.'); dot > 0 {
		return rest[:dot]
	}

	return rest
}

// scanLinesOrReturns splits on \r as well as \n, because steamcmd's progress
// output is one very long line separated by carriage returns and a normal line
// scanner would sit there buffering it until the download finished.
func scanLinesOrReturns(data []byte, atEOF bool) (int, []byte, error) {
	for i, b := range data {
		if b == '\n' || b == '\r' {
			return i + 1, data[:i], nil
		}
	}
	if atEOF && len(data) > 0 {
		return len(data), data, nil
	}

	return 0, nil, nil
}

// ------------------------------------------------------------------- control

func (d *Driver) Start(ctx context.Context, s runtime.Server) error {
	dir, err := d.EnsureDir(s)
	if err != nil {
		return err
	}

	// A template's startup command is written against the panel's variables, in
	// either spelling: {{SERVER_PORT}} for anything inherited from a Pterodactyl
	// egg, $SERVER_PORT for anything written here. Expand the first, export the
	// second, so both work and neither reaches the game as literal text.
	startup, missing := runtime.Expand(s.Startup, s.Environment)
	if len(missing) > 0 {
		log.Printf("server %s: startup command references %v, which this server has no value for; leaving those placeholders as they are",
			s.UUID, missing)
	}

	return d.sup.Start(ctx, supervise.Session(s.UUID), dir, startup, s.Environment, s.MemoryMiB, s.CPUPercent)
}

func (d *Driver) Stop(ctx context.Context, s runtime.Server) error {
	return d.sup.Stop(ctx, supervise.Session(s.UUID), s.StopCommand, 30*time.Second)
}

func (d *Driver) Restart(ctx context.Context, s runtime.Server) error {
	if err := d.Stop(ctx, s); err != nil {
		return err
	}

	return d.Start(ctx, s)
}

func (d *Driver) Kill(ctx context.Context, s runtime.Server) error {
	return d.sup.Kill(ctx, supervise.Session(s.UUID))
}

func (d *Driver) Command(ctx context.Context, s runtime.Server, cmd string) error {
	return d.sup.Command(ctx, supervise.Session(s.UUID), cmd)
}

func (d *Driver) Destroy(ctx context.Context, s runtime.Server) error {
	if err := d.sup.Kill(ctx, supervise.Session(s.UUID)); err != nil {
		return err
	}

	return d.DestroyDir(s)
}

func (d *Driver) Stats(ctx context.Context, s runtime.Server) (runtime.Stats, error) {
	session := supervise.Session(s.UUID)

	out := runtime.Stats{
		State:     runtime.StateOffline,
		MemoryCap: s.MemoryMiB,
		DiskMiB:   d.DiskUsageMiB(s),
		SampledAt: time.Now(),
	}

	if !d.sup.Running(ctx, session) {
		return out, nil
	}

	usage := d.sup.Usage(ctx, session)
	out.State = runtime.StateRunning
	out.CPU = usage.CPUPercent
	out.MemoryMiB = usage.MemoryMiB
	out.Uptime = usage.UptimeSec

	return out, nil
}

// Logs replays the captured console and then follows it. There is no equivalent
// of Docker's follow endpoint for a native process, so this tails the file tmux
// is piping into.
func (d *Driver) Logs(ctx context.Context, s runtime.Server, tail int, w io.Writer) error {
	return supervise.Follow(ctx, d.Dir(s), tail, w)
}

// Backlog is a plain read of the same capture file, no polling loop.
func (d *Driver) Backlog(_ context.Context, s runtime.Server, n int) ([]string, error) {
	return supervise.Tail(d.Dir(s), n)
}
