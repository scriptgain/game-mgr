// Package stub is a runtime driver that never touches the machine it runs on.
// It answers every call with plausible synthetic data: a Minecraft-shaped boot
// log, a stats curve that moves, a small file tree you can browse and edit in
// memory. It exists so the whole panel can be built and exercised before the
// Docker, SteamCMD and LinuxGSM drivers are written, and so a demo instance can
// be shown without provisioning real game servers.
//
// It is never the right driver in production. The daemon refuses to select it
// unless NODE_DRIVER=stub is set explicitly.
package stub

import (
	"context"
	"fmt"
	"io"
	"math"
	"math/rand"
	"path/filepath"
	"sort"
	"strings"
	"sync"
	"time"

	"github.com/scriptgain/gamemgr-node/internal/runtime"
	"github.com/scriptgain/gamemgr-node/internal/runtime/store"
)

type Driver struct {
	mu     sync.Mutex
	states map[string]*serverState
	rnd    *rand.Rand
}

type serverState struct {
	power     runtime.PowerState
	startedAt time.Time
	files     map[string]*file
	players   int
	seed      int64
}

type file struct {
	body []byte
	dir  bool
	mode string
	mod  time.Time
}

func New() *Driver {
	return &Driver{
		states: map[string]*serverState{},
		rnd:    rand.New(rand.NewSource(1337)),
	}
}

func (d *Driver) Name() string { return "stub" }

func (d *Driver) Available(context.Context) (bool, string) {
	return true, "stub driver, synthetic data only"
}

func (d *Driver) state(s runtime.Server) *serverState {
	d.mu.Lock()
	defer d.mu.Unlock()
	st, ok := d.states[s.UUID]
	if !ok {
		st = &serverState{
			power: runtime.StateOffline,
			files: seedFiles(s),
			seed:  hash(s.UUID),
		}
		d.states[s.UUID] = st
	}
	return st
}

// ---------------------------------------------------------------- lifecycle

func (d *Driver) Install(ctx context.Context, s runtime.Server, w io.Writer) error {
	lines := []string{
		fmt.Sprintf("[gamemgr] installing %s using the %s runtime", s.Name, s.Runtime),
		"[gamemgr] preparing volume /var/lib/gamemgr/volumes/" + short(s.UUID),
	}
	switch s.Runtime {
	case "steamcmd":
		lines = append(lines,
			fmt.Sprintf("[steamcmd] logging in %s", loginName(s)),
			fmt.Sprintf("[steamcmd] app_update %d validate", s.SteamAppID),
			"[steamcmd] Update state (0x61) downloading, progress: 34.12 (1204238848 / 3529183232)",
			"[steamcmd] Update state (0x61) downloading, progress: 88.40 (3120439296 / 3529183232)",
			fmt.Sprintf("[steamcmd] Success! App '%d' fully installed.", s.SteamAppID),
		)
	case "linuxgsm":
		lines = append(lines,
			fmt.Sprintf("[linuxgsm] fetching %s", s.LGSMShortname),
			"[linuxgsm] checking dependencies",
			"[linuxgsm] installing server files",
			"[linuxgsm] install complete",
		)
	default:
		lines = append(lines,
			fmt.Sprintf("[docker] pulling %s", s.Image),
			"[docker] Digest: sha256:e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
			"[docker] Status: Downloaded newer image",
			"[install] running installation script",
			"[install] done",
		)
	}
	lines = append(lines, "[gamemgr] install finished successfully")
	for _, l := range lines {
		if ctx.Err() != nil {
			return ctx.Err()
		}
		fmt.Fprintln(w, l)
	}
	return nil
}

func (d *Driver) Start(_ context.Context, s runtime.Server) error {
	st := d.state(s)
	d.mu.Lock()
	defer d.mu.Unlock()
	st.power = runtime.StateRunning
	st.startedAt = time.Now()
	return nil
}

func (d *Driver) Stop(_ context.Context, s runtime.Server) error {
	st := d.state(s)
	d.mu.Lock()
	defer d.mu.Unlock()
	st.power = runtime.StateOffline
	st.players = 0
	return nil
}

func (d *Driver) Restart(ctx context.Context, s runtime.Server) error {
	if err := d.Stop(ctx, s); err != nil {
		return err
	}
	return d.Start(ctx, s)
}

func (d *Driver) Kill(ctx context.Context, s runtime.Server) error { return d.Stop(ctx, s) }

func (d *Driver) Update(ctx context.Context, s runtime.Server, w io.Writer) error {
	fmt.Fprintf(w, "[gamemgr] checking for updates using the %s runtime\n", s.Runtime)
	fmt.Fprintln(w, "[gamemgr] already up to date")
	return nil
}

func (d *Driver) Command(_ context.Context, s runtime.Server, cmd string) error {
	st := d.state(s)
	d.mu.Lock()
	defer d.mu.Unlock()
	// A couple of commands visibly do something, which makes the console feel
	// real when clicking through the panel.
	switch {
	case strings.HasPrefix(cmd, "stop"), strings.HasPrefix(cmd, "quit"):
		st.power = runtime.StateOffline
		st.players = 0
	case strings.HasPrefix(cmd, "kick"):
		if st.players > 0 {
			st.players--
		}
	}
	return nil
}

func (d *Driver) Destroy(_ context.Context, s runtime.Server) error {
	d.mu.Lock()
	defer d.mu.Unlock()
	delete(d.states, s.UUID)
	return nil
}

// -------------------------------------------------------------------- stats

func (d *Driver) Stats(_ context.Context, s runtime.Server) (runtime.Stats, error) {
	st := d.state(s)
	d.mu.Lock()
	defer d.mu.Unlock()

	now := time.Now()
	if st.power != runtime.StateRunning {
		return runtime.Stats{
			State:     st.power,
			MemoryCap: s.MemoryMiB,
			SampledAt: now,
		}, nil
	}

	// A slow sine plus jitter, seeded off the server uuid so each server has
	// its own recognisable curve rather than every graph moving in lockstep.
	t := float64(now.Unix()) / 90
	phase := float64(st.seed%360) * math.Pi / 180
	wave := (math.Sin(t+phase) + 1) / 2

	cpuCap := float64(s.CPUPercent)
	if cpuCap <= 0 {
		cpuCap = 200
	}
	memCap := s.MemoryMiB
	if memCap <= 0 {
		memCap = 4096
	}

	st.players = int(wave*8) + int(st.seed%4)

	return runtime.Stats{
		State:      runtime.StateRunning,
		CPU:        round2(cpuCap * (0.18 + wave*0.45)),
		MemoryMiB:  int64(float64(memCap) * (0.34 + wave*0.38)),
		MemoryCap:  memCap,
		DiskMiB:    s.DiskMiB / 3,
		NetRXBytes: int64(12000 + wave*90000),
		NetTXBytes: int64(8000 + wave*140000),
		Players:    st.players,
		MaxPlayers: 20,
		TickRate:   round2(20 - wave*1.4),
		Uptime:     int64(now.Sub(st.startedAt).Seconds()),
		SampledAt:  now,
	}, nil
}

// ------------------------------------------------------------------- console

func (d *Driver) Backlog(_ context.Context, s runtime.Server, n int) ([]string, error) {
	boot := bootLog(s)
	if n > 0 && n < len(boot) {
		boot = boot[len(boot)-n:]
	}

	return boot, nil
}

func (d *Driver) Logs(ctx context.Context, s runtime.Server, tail int, w io.Writer) error {
	boot := bootLog(s)
	if tail > 0 && tail < len(boot) {
		boot = boot[len(boot)-tail:]
	}
	for _, l := range boot {
		if ctx.Err() != nil {
			return nil
		}
		if _, err := fmt.Fprintln(w, l); err != nil {
			return err
		}
		flush(w)
	}

	// Then keep dripping plausible chatter until the caller goes away.
	tick := time.NewTicker(3 * time.Second)
	defer tick.Stop()
	i := 0
	for {
		select {
		case <-ctx.Done():
			return nil
		case <-tick.C:
			line := idleLine(s, i)
			i++
			if _, err := fmt.Fprintln(w, line); err != nil {
				return err
			}
			flush(w)
		}
	}
}

func flush(w io.Writer) {
	if f, ok := w.(interface{ Flush() }); ok {
		f.Flush()
	}
}

// --------------------------------------------------------------------- files

func (d *Driver) List(_ context.Context, s runtime.Server, path string) ([]runtime.FileEntry, error) {
	st := d.state(s)
	d.mu.Lock()
	defer d.mu.Unlock()

	path = normalise(path)
	seen := map[string]runtime.FileEntry{}
	for p, f := range st.files {
		parent, name := split(p)
		if parent != path {
			continue
		}
		seen[name] = runtime.FileEntry{
			Name:       name,
			Directory:  f.dir,
			Size:       int64(len(f.body)),
			Mode:       f.mode,
			MimeType:   mimeOf(name, f.dir),
			ModifiedAt: f.mod,
		}
	}
	out := make([]runtime.FileEntry, 0, len(seen))
	for _, e := range seen {
		out = append(out, e)
	}
	// Directories first, then alphabetical. Matches what every file manager
	// does and what the panel's table assumes.
	sort.Slice(out, func(i, j int) bool {
		if out[i].Directory != out[j].Directory {
			return out[i].Directory
		}
		return strings.ToLower(out[i].Name) < strings.ToLower(out[j].Name)
	})
	return out, nil
}

func (d *Driver) Read(_ context.Context, s runtime.Server, path string) ([]byte, error) {
	st := d.state(s)
	d.mu.Lock()
	defer d.mu.Unlock()
	f, ok := st.files[normalise(path)]
	if !ok || f.dir {
		return nil, fmt.Errorf("no such file: %s", path)
	}
	return f.body, nil
}

func (d *Driver) Write(_ context.Context, s runtime.Server, path string, body []byte) error {
	st := d.state(s)
	d.mu.Lock()
	defer d.mu.Unlock()
	p := normalise(path)
	st.files[p] = &file{body: body, mode: "0644", mod: time.Now()}
	return nil
}

// Upload keeps the file in the same in-memory map everything else here uses.
// The cap is still enforced, because the panel's "that file is too big"
// message is one of the things a stub node exists to let somebody exercise.
func (d *Driver) Upload(_ context.Context, s runtime.Server, path string, body io.Reader, maxBytes int64) (int64, error) {
	if maxBytes <= 0 || maxBytes > store.DefaultMaxUploadBytes {
		maxBytes = store.DefaultMaxUploadBytes
	}
	if strings.Contains(filepath.ToSlash(path), "../") || strings.HasSuffix(path, "/..") {
		return 0, fmt.Errorf("path escapes the server directory")
	}

	// One past the cap, so a file exactly one byte too large is refused rather
	// than truncated. Same reasoning as the real store.
	buf, err := io.ReadAll(io.LimitReader(body, maxBytes+1))
	if err != nil {
		return 0, err
	}
	if int64(len(buf)) > maxBytes {
		return 0, store.ErrTooLarge
	}

	st := d.state(s)
	d.mu.Lock()
	defer d.mu.Unlock()
	st.files[normalise(path)] = &file{body: buf, mode: "0644", mod: time.Now()}

	return int64(len(buf)), nil
}

func (d *Driver) Delete(_ context.Context, s runtime.Server, paths []string) error {
	st := d.state(s)
	d.mu.Lock()
	defer d.mu.Unlock()
	for _, p := range paths {
		p = normalise(p)
		delete(st.files, p)
		// Deleting a directory takes everything under it.
		for k := range st.files {
			if strings.HasPrefix(k, p+"/") {
				delete(st.files, k)
			}
		}
	}
	return nil
}

func (d *Driver) Rename(_ context.Context, s runtime.Server, from, to string) error {
	st := d.state(s)
	d.mu.Lock()
	defer d.mu.Unlock()
	f, ok := st.files[normalise(from)]
	if !ok {
		return fmt.Errorf("no such file: %s", from)
	}
	delete(st.files, normalise(from))
	st.files[normalise(to)] = f
	return nil
}

func (d *Driver) MakeDir(_ context.Context, s runtime.Server, path string) error {
	st := d.state(s)
	d.mu.Lock()
	defer d.mu.Unlock()
	st.files[normalise(path)] = &file{dir: true, mode: "0755", mod: time.Now()}
	return nil
}

// ------------------------------------------------------------------- backups

func (d *Driver) Backup(_ context.Context, s runtime.Server, backupUUID string, _ []string) (int64, string, error) {
	// A believable size derived from the uuid so the same backup always
	// reports the same bytes.
	size := 180*1024*1024 + hash(backupUUID)%(900*1024*1024)
	return size, fmt.Sprintf("%x", hash(backupUUID+s.UUID)), nil
}

func (d *Driver) Restore(context.Context, runtime.Server, string) error { return nil }

// ------------------------------------------------------------------- helpers

func round2(f float64) float64 { return math.Round(f*100) / 100 }

func hash(s string) int64 {
	var h int64 = 14695981039346656037 >> 1
	for _, c := range s {
		h = (h*16777619 + int64(c)) & 0x7fffffffffff
	}
	return h
}

func short(uuid string) string {
	if len(uuid) >= 8 {
		return uuid[:8]
	}
	return uuid
}

func loginName(s runtime.Server) string {
	if s.SteamAnonymous {
		return "anonymous"
	}
	return "steamuser"
}

func normalise(p string) string {
	p = strings.TrimSuffix("/"+strings.Trim(p, "/"), "/")
	if p == "" {
		return "/"
	}
	return p
}

func split(p string) (parent, name string) {
	p = normalise(p)
	i := strings.LastIndex(p, "/")
	if i <= 0 {
		return "/", strings.TrimPrefix(p, "/")
	}
	return p[:i], p[i+1:]
}

func mimeOf(name string, dir bool) string {
	if dir {
		return "inode/directory"
	}
	switch {
	case strings.HasSuffix(name, ".json"):
		return "application/json"
	case strings.HasSuffix(name, ".yml"), strings.HasSuffix(name, ".yaml"):
		return "text/yaml"
	case strings.HasSuffix(name, ".properties"), strings.HasSuffix(name, ".cfg"),
		strings.HasSuffix(name, ".ini"), strings.HasSuffix(name, ".txt"),
		strings.HasSuffix(name, ".log"), strings.HasSuffix(name, ".sh"):
		return "text/plain"
	case strings.HasSuffix(name, ".jar"):
		return "application/java-archive"
	}
	return "application/octet-stream"
}
