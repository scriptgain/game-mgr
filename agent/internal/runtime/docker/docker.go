// Package docker runs a game server as a container on the local Docker daemon.
//
// This is the runtime most community template definitions target, so it is the
// default and the widest supported. The other two runtimes exist because it is
// not always the right answer: a Source server can misbehave under a container
// network namespace, and on bare metal the container buys you nothing.
package docker

import (
	"context"
	"fmt"
	"io"
	"log"
	"os"
	"path/filepath"
	"slices"
	"sort"
	"strconv"
	"strings"
	"time"

	dockerapi "github.com/scriptgain/gamemgr-node/internal/docker"
	"github.com/scriptgain/gamemgr-node/internal/runtime"
	"github.com/scriptgain/gamemgr-node/internal/runtime/store"
	"github.com/scriptgain/gamemgr-node/internal/supervise"
)

type Driver struct {
	// Embedded, so every file, backup and restore operation is the shared one
	// rather than a third copy of the path traversal guard.
	store.Store

	api *dockerapi.Client

	// Who the container should run as. A bind mounted directory is owned by a
	// uid on the HOST, and the process inside the container is a uid too: if
	// they differ the game cannot write its own world, which is exactly what
	// happened. The host account here is uid 1001 while itzg's image defaults
	// to 1000, so the two never met.
	runAs *supervise.Credential
}

// New builds the Docker driver. The credential is the node's one game account,
// resolved in main, so a container's uid and the host directory's owner are the
// same answer to the same question rather than two independent guesses.
func New(socket, root string, runAs *supervise.Credential) *Driver {
	return &Driver{Store: store.New(root, runAs), api: dockerapi.New(socket), runAs: runAs}
}

func (d *Driver) Name() string { return "docker" }

// Available pokes the daemon rather than assuming. A node that lists docker as
// a supported runtime but has a dead daemon should say so on its own page, not
// fail at the moment somebody tries to start a server.
func (d *Driver) Available(ctx context.Context) (bool, string) {
	if err := d.api.Ping(ctx); err != nil {
		return false, "docker daemon not answering: " + err.Error()
	}
	version, err := d.api.Version(ctx)
	if err != nil {
		return true, "docker reachable"
	}

	detail := "docker " + version
	if note := mapCountNote(); note != "" {
		detail += ", " + note
	}

	return true, detail
}

// mapCountNote warns when the host's vm.max_map_count is too low for a Proton
// game server, and says nothing otherwise.
//
// A container cannot set this: it is a host-wide kernel parameter, and the only
// symptom of getting it wrong is a Wine process that dies during startup with
// an allocation failure nobody would connect to a sysctl. Reporting it on the
// node's own page is the difference between a five second fix and an evening.
//
// Not an error, because every other runtime and most Docker templates are
// entirely happy at the default: this must never stop a node being usable.
const minMapCount = 2147483642

func mapCountNote() string {
	raw, err := os.ReadFile("/proc/sys/vm/max_map_count")
	if err != nil {
		// Not Linux, or /proc is not mounted. Nothing useful to say.
		return ""
	}

	value, err := strconv.ParseInt(strings.TrimSpace(string(raw)), 10, 64)
	if err != nil || value >= minMapCount {
		return ""
	}

	return fmt.Sprintf("vm.max_map_count is %d, too low for Proton templates (needs %d)", value, minMapCount)
}

// container is the name a server's container carries. Prefixed so the daemon
// can tell its own containers from everything else on a shared box, and never
// removes something it did not create.
func container(s runtime.Server) string {
	return "gamemgr-" + store.Short(s.UUID)
}

// ----------------------------------------------------------------- lifecycle

func (d *Driver) Install(ctx context.Context, s runtime.Server, w io.Writer) error {
	path, err := d.EnsureDir(s)
	if err != nil {
		return err
	}
	fmt.Fprintf(w, "[gamemgr] data directory %s\n", path)

	if s.Image == "" {
		return fmt.Errorf("this template has no docker image set")
	}

	fmt.Fprintf(w, "[gamemgr] pulling %s\n", s.Image)
	if err := d.api.PullImage(ctx, s.Image, w); err != nil {
		return err
	}

	// A fresh container is always created, so a reinstall picks up a changed
	// image or startup command rather than reusing a stale one.
	if err := d.destroyContainer(ctx, s); err != nil {
		return err
	}

	fmt.Fprintln(w, "[gamemgr] install complete")

	return nil
}

func (d *Driver) Start(ctx context.Context, s runtime.Server) error {
	id, err := d.ensureContainer(ctx, s)
	if err != nil {
		return err
	}

	if err := d.api.StartContainer(ctx, id); err != nil {
		// Already running is not worth surfacing: the caller asked for it to be
		// up and it is up.
		if strings.Contains(strings.ToLower(err.Error()), "already started") {
			return nil
		}

		return err
	}

	// The throttle has to be applied after the container exists, because it is
	// written into the cgroup the container was given. Best effort on purpose:
	// a node where this cannot be written behaves exactly as it did before, and
	// a server must never fail to start over it.
	if s.MemoryMiB > 0 {
		if info, err := d.api.Inspect(ctx, container(s)); err == nil && info.State.Pid > 0 {
			if path, err := softLimit(info.State.Pid, s.MemoryMiB); err != nil {
				log.Printf("server %s: could not set a memory throttle (%s): %v; the hard limit still applies",
					s.UUID, path, err)
			}
		}
	}

	return nil
}

func (d *Driver) Stop(ctx context.Context, s runtime.Server) error {
	// Graceful first: send the template's stop command to stdin so the game
	// saves, then fall back to Docker's own stop. Killing a Minecraft server
	// without letting it save is how people lose a day of building.
	if cmd := strings.TrimSpace(s.StopCommand); cmd != "" && cmd != "^C" {
		if err := d.Command(ctx, s, cmd); err == nil {
			if d.waitForExit(ctx, s, 30*time.Second) {
				return nil
			}
		}
	}

	err := d.api.StopContainer(ctx, container(s), 30)
	if err != nil && dockerapi.NotFound(err) {
		return nil
	}

	return err
}

func (d *Driver) Restart(ctx context.Context, s runtime.Server) error {
	if err := d.Stop(ctx, s); err != nil && !dockerapi.NotFound(err) {
		return err
	}

	return d.Start(ctx, s)
}

func (d *Driver) Kill(ctx context.Context, s runtime.Server) error {
	err := d.api.KillContainer(ctx, container(s))
	if err != nil && dockerapi.NotFound(err) {
		return nil
	}

	return err
}

func (d *Driver) Update(ctx context.Context, s runtime.Server, w io.Writer) error {
	if s.Image == "" {
		return fmt.Errorf("this template has no docker image set")
	}

	fmt.Fprintf(w, "[gamemgr] pulling %s\n", s.Image)
	if err := d.api.PullImage(ctx, s.Image, w); err != nil {
		return err
	}

	// The container is pinned to the image it was created from, so a pull alone
	// changes nothing until it is recreated. Doing that here is the difference
	// between an update that works and one that silently does not.
	fmt.Fprintln(w, "[gamemgr] recreating the container on the new image")
	if err := d.destroyContainer(ctx, s); err != nil {
		return err
	}
	if _, err := d.ensureContainer(ctx, s); err != nil {
		return err
	}

	fmt.Fprintln(w, "[gamemgr] update complete, start the server to run it")

	return nil
}

func (d *Driver) Destroy(ctx context.Context, s runtime.Server) error {
	if err := d.destroyContainer(ctx, s); err != nil {
		return err
	}

	return d.DestroyDir(s)
}

func (d *Driver) destroyContainer(ctx context.Context, s runtime.Server) error {
	err := d.api.RemoveContainer(ctx, container(s), true)
	if err != nil && dockerapi.NotFound(err) {
		return nil
	}

	return err
}

func (d *Driver) waitForExit(ctx context.Context, s runtime.Server, within time.Duration) bool {
	deadline := time.Now().Add(within)
	for time.Now().Before(deadline) {
		info, err := d.api.Inspect(ctx, container(s))
		if err != nil || !info.State.Running {
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

// ensureContainer returns the container id, creating it if it is not there.
func (d *Driver) ensureContainer(ctx context.Context, s runtime.Server) (string, error) {
	name := container(s)

	info, err := d.api.Inspect(ctx, name)
	if err == nil {
		/*
		 * A container is frozen at the spec it was created with, and Start has
		 * always just started whatever was already there. So a template fixed
		 * today did nothing for a server created yesterday: the Mumble startup
		 * fix needed a Reinstall before it took, and a stop/start looked like
		 * it should have worked. Same for a changed image, a changed
		 * environment, or an allocation added after the fact.
		 *
		 * Nothing is lost by rebuilding it. The server's files live in a bind
		 * mount; the only casualty is the container's own log buffer, which is
		 * a fair price for not running the wrong command.
		 */
		if reason := d.stale(info, s); reason != "" && !info.State.Running {
			log.Printf("server %s: recreating the container (%s)", s.UUID, reason)

			if id, err := d.recreate(ctx, s, info.ID); err == nil {
				return id, nil
			} else {
				// The old container is still there and still starts. A server
				// running last week's command beats a server that will not
				// come up at all, so this is a note rather than a failure.
				log.Printf("server %s: could not recreate the container, starting the existing one: %v", s.UUID, err)
			}
		}

		return info.ID, nil
	}
	if !dockerapi.NotFound(err) {
		return "", err
	}

	path, err := d.EnsureDir(s)
	if err != nil {
		return "", err
	}

	if s.Image == "" {
		return "", fmt.Errorf("this template has no docker image set")
	}
	if !d.api.ImageExists(ctx, s.Image) {
		if err := d.api.PullImage(ctx, s.Image, io.Discard); err != nil {
			return "", err
		}
	}

	d.handOverData(ctx, s, path)

	return d.api.CreateContainer(ctx, name, d.config(s, path))
}

/*
 * Why the existing container is no longer the one we would create, or "".
 *
 * Deliberately only the fields that change what the server IS. Docker fills in
 * a great deal else, and comparing the whole config would recreate a container
 * on every start for no reason.
 *
 * Environment is checked one way round: the image bakes in variables of its
 * own, so the question is whether everything WE ask for is present, not
 * whether the two lists match.
 */
func (d *Driver) stale(info *dockerapi.Inspect, s runtime.Server) string {
	desired := d.config(s, d.Dir(s))

	if len(desired.Cmd) > 0 && !slices.Equal(info.Config.Cmd, desired.Cmd) {
		return "the startup command changed"
	}

	if s.Image != "" && info.Config.Image != s.Image {
		return "the image changed to " + s.Image
	}

	have := make(map[string]bool, len(info.Config.Env))
	for _, entry := range info.Config.Env {
		have[entry] = true
	}
	for _, entry := range desired.Env {
		if !have[entry] {
			// Named, because "an environment variable changed" sends somebody
			// diffing forty of them by hand.
			return "environment changed: " + strings.SplitN(entry, "=", 2)[0]
		}
	}

	if desired.HostConfig != nil && !samePorts(info.HostConfig.PortBindings, desired.HostConfig.PortBindings) {
		return "the published ports changed"
	}

	return ""
}

/** Same set of port/proto keys, each bound to the same host port. */
func samePorts(have, want map[string][]dockerapi.PortBinding) bool {
	if len(have) != len(want) {
		return false
	}

	for key, wanted := range want {
		existing, ok := have[key]
		if !ok || len(existing) != len(wanted) {
			return false
		}
		for i := range wanted {
			if existing[i].HostPort != wanted[i].HostPort {
				return false
			}
		}
	}

	return true
}

// recreate removes the old container and builds it again from the current spec.
// The remove comes first because Docker will not have two of one name, and it
// is safe: everything that matters is in the bind mount.
func (d *Driver) recreate(ctx context.Context, s runtime.Server, id string) (string, error) {
	if err := d.api.RemoveContainer(ctx, id, true); err != nil {
		return "", err
	}

	path, err := d.EnsureDir(s)
	if err != nil {
		return "", err
	}

	if s.Image != "" && !d.api.ImageExists(ctx, s.Image) {
		if err := d.api.PullImage(ctx, s.Image, io.Discard); err != nil {
			return "", err
		}
	}

	d.handOverData(ctx, s, path)

	return d.api.CreateContainer(ctx, container(s), d.config(s, path))
}

/*
 * Make the data directory writable by whoever the image actually runs as.
 *
 * Three kinds of image, and only one of them was working:
 *
 *   Runs as ROOT, prepares its own data and drops privileges. Told who to
 *   become through UID/GID or PUID/PGID. Nothing to do here.
 *
 *   Runs as a uid we can ask it to change, the linuxserver.io convention. PUID
 *   and PGID cover it, and those are sent now.
 *
 *   PINS its own non-root user and ignores both. acekorneya/asa_server runs as
 *   pok, uid 7777. The directory belongs to the node's game account, so ARK
 *   died on "Permission denied" before downloading a byte.
 *
 * The obvious fix for the third case, running the container as our uid instead,
 * is wrong: it cannot execute the image's own scripts, which are owned by the
 * image's user. Tried, and it fails a step later with the same words. So the
 * directory is handed to the image's user instead, which is the only party that
 * has to write it.
 *
 * Best effort throughout. A node where this cannot be determined behaves
 * exactly as it did before rather than refusing to start anything.
 */
func (d *Driver) handOverData(ctx context.Context, s runtime.Server, path string) {
	if s.Image == "" || path == "" {
		return
	}

	user, err := d.api.ImageUser(ctx, s.Image)
	if err != nil || user == "" || user == "root" || user == "0" || strings.HasPrefix(user, "0:") {
		return
	}

	uid, gid, err := d.api.ImageUserID(ctx, s.Image, user)
	if err != nil || uid == 0 {
		log.Printf("server %s: could not resolve the image's user %q, leaving the data directory as it is: %v",
			s.UUID, user, err)

		return
	}

	if err := chownTree(path, uid, gid); err != nil {
		log.Printf("server %s: could not hand %s to uid %d: %v", s.UUID, path, uid, err)

		return
	}

	log.Printf("server %s: data directory handed to the image's own user %s (%d:%d)", s.UUID, user, uid, gid)
}

// chownTree hands a directory and everything under it to one uid and gid.
func chownTree(root string, uid, gid int) error {
	return filepath.Walk(root, func(name string, _ os.FileInfo, err error) error {
		if err != nil {
			return err
		}

		return os.Lchown(name, uid, gid)
	})
}

func (d *Driver) config(s runtime.Server, path string) dockerapi.ContainerConfig {
	// The user is resolved by the callers that have a context; see userFor.
	keys := make([]string, 0, len(s.Environment))
	for k := range s.Environment {
		keys = append(keys, k)
	}
	// Sorted so the container config is stable between runs; an unordered map
	// makes every inspect diff look like a change.
	sort.Strings(keys)

	env := make([]string, 0, len(keys)+3)
	for _, k := range keys {
		env = append(env, k+"="+s.Environment[k])
	}
	// Tell the image which uid to be, rather than hoping it guessed the same
	// one we chowned to. itzg's images read UID and GID for exactly this, and
	// an image that ignores them is no worse off than before. This is done
	// instead of forcing HostConfig.User, because these images legitimately
	// start as root to prepare /data and drop privileges themselves; forcing
	// the user would break that setup before it ran.
	if d.runAs != nil {
		uid := strconv.FormatUint(uint64(d.runAs.Uid), 10)
		gid := strconv.FormatUint(uint64(d.runAs.Gid), 10)
		// Two dialects for one question. itzg's images read UID and GID;
		// linuxserver.io's and many game images read PUID and PGID. Sending
		// only the first meant an image following the other convention never
		// adopted our uid, and then could not write its own directory.
		env = append(env, "UID="+uid, "GID="+gid, "PUID="+uid, "PGID="+gid)
	}

	env = append(env,
		"SERVER_MEMORY="+strconv.FormatInt(s.MemoryMiB, 10),
		"SERVER_IP=0.0.0.0",
		"SERVER_PORT="+strconv.Itoa(s.Port),
	)

	// The three the daemon supplies itself have to be visible to placeholder
	// expansion as well as to the container, or {{SERVER_PORT}} - far and away
	// the most common placeholder in the egg catalogue - is the one thing that
	// cannot be resolved.
	vars := make(map[string]string, len(s.Environment)+3)
	for k, v := range s.Environment {
		vars[k] = v
	}
	vars["SERVER_MEMORY"] = strconv.FormatInt(s.MemoryMiB, 10)
	vars["SERVER_IP"] = "0.0.0.0"
	vars["SERVER_PORT"] = strconv.Itoa(s.Port)

	startup, missing := runtime.Expand(s.Startup, vars)
	if len(missing) > 0 {
		log.Printf("server %s: startup command references %v, which this server has no value for; leaving those placeholders as they are",
			s.UUID, missing)
	}

	dataPath := strings.TrimSpace(s.DataPath)
	if dataPath == "" {
		dataPath = "/home/container"
	}

	config := dockerapi.ContainerConfig{
		Image: s.Image,
		// Through a shell so a startup command with variables, pipes or
		// redirection behaves the way the template author wrote it.
		Entrypoint: []string{"/bin/sh", "-c"},
		// $VAR resolves inside the container because Env below sets it, but
		// {{VAR}} is not shell syntax and would reach the game as literal text.
		// Every imported Pterodactyl egg is written in that spelling, so expand
		// it here rather than requiring the catalogue to be rewritten.
		Cmd:          []string{startup},
		Env:          env,
		WorkingDir:   dataPath,
		OpenStdin:    true,
		AttachStdin:  true,
		AttachStdout: true,
		AttachStderr: true,
		Labels: map[string]string{
			"io.gamemgr.server": s.UUID,
			"io.gamemgr.name":   s.Name,
		},
		ExposedPorts: map[string]struct{}{},
	}

	host := &dockerapi.HostConfig{
		Binds:        []string{path + ":" + dataPath},
		PortBindings: map[string][]dockerapi.PortBinding{},
		NetworkMode:  "bridge",
	}

	// Every allocation the panel gave this server, not just the primary one.
	//
	// Publishing s.Port alone is right for a one-port game and silently wrong
	// for everything else: TeamSpeak's ServerQuery on 10011 and file transfer
	// on 30033 were reserved, listed in the UI and opened in the firewall, and
	// then never mapped, so the only thing that worked was voice. The protocol
	// comes from the allocation now rather than being assumed to be both, so a
	// UDP-only game stops claiming a TCP port it never listens on.
	for _, p := range s.PublishedPorts() {
		for _, proto := range p.Protocols() {
			key := strconv.Itoa(p.Port) + "/" + proto
			config.ExposedPorts[key] = struct{}{}
			host.PortBindings[key] = []dockerapi.PortBinding{{
				HostIP:   "0.0.0.0",
				HostPort: strconv.Itoa(p.Port),
			}}
		}
	}

	if s.MemoryMiB > 0 {
		limit := s.MemoryMiB * 1024 * 1024
		// A quarter of headroom above the promised figure, matching the native
		// runtimes. Docker's Memory is memory.max, a hard kill, and it is the
		// runaway guard rather than the everyday ceiling. The everyday ceiling
		// is memory.high, which Docker has no field for and which softLimit
		// writes into the container's own cgroup once it is running.
		host.Memory = limit + limit/4
		// Swap set equal to memory means no swap: Docker reads MemorySwap as
		// the combined total, so leaving it unset grants unlimited swap and a
		// server quietly blows past the limit the panel promised.
		host.MemorySwap = host.Memory
	}
	if s.CPUPercent > 0 {
		host.CPUPeriod = 100000
		host.CPUQuota = int64(s.CPUPercent) * 1000
	}

	host.LogConfig.Type = "json-file"
	host.LogConfig.Config = map[string]string{"max-size": "16m", "max-file": "2"}
	// The panel's watchdog decides whether a crashed server comes back, not
	// Docker. Two things restarting the same container fight each other.
	host.RestartPolicy.Name = "no"

	config.HostConfig = host

	return config
}

// ------------------------------------------------------------------- runtime

func (d *Driver) Command(ctx context.Context, s runtime.Server, cmd string) error {
	conn, err := d.api.Attach(ctx, container(s))
	if err != nil {
		return err
	}
	defer conn.Close()

	_, err = conn.Write([]byte(strings.TrimRight(cmd, "\r\n") + "\n"))

	return err
}

func (d *Driver) Stats(ctx context.Context, s runtime.Server) (runtime.Stats, error) {
	info, err := d.api.Inspect(ctx, container(s))
	if err != nil {
		if dockerapi.NotFound(err) {
			return runtime.Stats{State: runtime.StateOffline, MemoryCap: s.MemoryMiB, SampledAt: time.Now()}, nil
		}

		return runtime.Stats{}, err
	}

	out := runtime.Stats{
		State:     stateOf(info),
		MemoryCap: s.MemoryMiB,
		DiskMiB:   d.DiskUsageMiB(s),
		SampledAt: time.Now(),
	}

	if !info.State.Running {
		return out, nil
	}

	if started, err := time.Parse(time.RFC3339Nano, info.State.StartedAt); err == nil {
		out.Uptime = int64(time.Since(started).Seconds())
	}

	stats, err := d.api.StatsOnce(ctx, container(s))
	if err != nil {
		return out, nil
	}

	out.CPU = round2(stats.CPUPercent())
	out.MemoryMiB = stats.MemoryUsedMiB()
	out.NetRXBytes, out.NetTXBytes = stats.Network()

	return out, nil
}

func stateOf(info *dockerapi.Inspect) runtime.PowerState {
	switch {
	case info.State.Running:
		return runtime.StateRunning
	case info.State.OOMKilled:
		return runtime.StateCrashed
	// A non-zero exit is a crash, not a clean stop. Reporting it as offline is
	// what makes a crash loop invisible until somebody notices the server is
	// down, which is the whole reason the watchdog exists.
	case info.State.ExitCode != 0 && info.State.Status == "exited":
		return runtime.StateCrashed
	default:
		return runtime.StateOffline
	}
}

// Backlog asks Docker for the same lines without follow, so it returns as soon
// as the container has finished handing them over.
func (d *Driver) Backlog(ctx context.Context, s runtime.Server, n int) ([]string, error) {
	if n <= 0 {
		n = 200
	}

	buf := &strings.Builder{}
	if err := d.api.Logs(ctx, container(s), n, false, buf); err != nil {
		if dockerapi.NotFound(err) {
			return []string{"[gamemgr] this server has not been started yet, so there is no output."}, nil
		}

		return nil, err
	}

	return lastLines(buf.String(), n), nil
}

// lastLines keeps the tail of a blob of output. Docker honours the tail count
// itself, but a single log line can be split across frames, so the count coming
// back is a guide rather than a guarantee.
func lastLines(blob string, n int) []string {
	lines := strings.Split(strings.TrimRight(blob, "\n"), "\n")
	if len(lines) == 1 && lines[0] == "" {
		return nil
	}
	if n > 0 && len(lines) > n {
		lines = lines[len(lines)-n:]
	}

	return lines
}

func (d *Driver) Logs(ctx context.Context, s runtime.Server, tail int, w io.Writer) error {
	if tail <= 0 {
		tail = 200
	}

	err := d.api.Logs(ctx, container(s), tail, true, w)
	if err != nil && dockerapi.NotFound(err) {
		fmt.Fprintln(w, "[gamemgr] this server has not been started yet, so there is no output.")

		return nil
	}

	return err
}

// round2 keeps a CPU percentage readable. The panel charts it, and four decimal
// places of jitter only makes the line noisy.
func round2(f float64) float64 {
	return float64(int64(f*100+0.5)) / 100
}
