// Package runtime defines the contract every GameMGR runtime backend
// implements. This is the whole point of the daemon: Pterodactyl's wings can
// only start a Docker container, so a template that wants a native SteamCMD
// install or a LinuxGSM-managed instance simply cannot exist. Here a template
// declares its runtime and the daemon picks the matching driver.
package runtime

import (
	"context"
	"errors"
	"io"
	"strings"
	"time"
)

// ErrNotImplemented is what the real drivers return until they are written.
// The panel treats it as "this node cannot run that", which is the correct
// behaviour anyway for a node that lacks Docker or steamcmd.
var ErrNotImplemented = errors.New("runtime driver not implemented yet")

// PowerState is the coarse state the panel colours a status dot from.
type PowerState string

const (
	StateOffline  PowerState = "offline"
	StateStarting PowerState = "starting"
	StateRunning  PowerState = "running"
	StateStopping PowerState = "stopping"
	StateCrashed  PowerState = "crashed"
)

// Stats is one sample of a server's resource use. Bytes and MiB are kept
// distinct on purpose: mixing them is the classic source of graphs that are
// wrong by a factor of 1048576.
type Stats struct {
	State      PowerState `json:"state"`
	CPU        float64    `json:"cpu"` // percent, 100 = one full core
	MemoryMiB  int64      `json:"memory_mib"`
	MemoryCap  int64      `json:"memory_cap_mib"`
	DiskMiB    int64      `json:"disk_mib"`
	NetRXBytes int64      `json:"net_rx_bytes"`
	NetTXBytes int64      `json:"net_tx_bytes"`
	Players    int        `json:"players"`
	MaxPlayers int        `json:"max_players"`
	TickRate   float64    `json:"tick_rate"` // TPS for Minecraft, FPS elsewhere
	Uptime     int64      `json:"uptime_sec"`
	SampledAt  time.Time  `json:"sampled_at"`
}

// FileEntry is one row in the file manager.
type FileEntry struct {
	Name       string    `json:"name"`
	Directory  bool      `json:"directory"`
	Symlink    bool      `json:"symlink"`
	Size       int64     `json:"size"`
	Mode       string    `json:"mode"`
	MimeType   string    `json:"mime_type"`
	ModifiedAt time.Time `json:"modified_at"`
}

// Server is the subset of a panel server the daemon needs to act on it. The
// daemon deliberately holds no database: the panel is the source of truth and
// passes everything required with the request.
type Server struct {
	UUID    string `json:"uuid"`
	Name    string `json:"name"`
	Runtime string `json:"runtime"`
	Image   string `json:"image"`
	Startup string `json:"startup"`
	// Where inside the container the server keeps its files, which is what the
	// data directory gets bind mounted over. /home/container is the convention
	// most community images follow, but not all: itzg/minecraft-server uses
	// /data, and mounting the wrong path leaves the volume empty while the
	// world quietly fills the container's writable layer instead.
	DataPath    string            `json:"data_path"`
	StopCommand string            `json:"stop_command"`
	Environment map[string]string `json:"environment"`
	MemoryMiB   int64             `json:"memory_mib"`
	DiskMiB     int64             `json:"disk_mib"`
	CPUPercent  int               `json:"cpu_percent"`
	IP          string            `json:"ip"`
	Port        int               `json:"port"`

	// What the node's firewall has to allow. Port above is the allocation and
	// is authoritative: the query and RCON ports are these template offsets
	// applied to it, because the panel stores offsets rather than absolute
	// numbers. Zero means the game does not use a separate port.
	//
	// DefaultProtocol is the template's default_protocol: tcp, udp or both.
	// Empty is read as both, which is what a panel too old to send it produces.
	RconPortOffset  int    `json:"rcon_port_offset"`
	QueryPortOffset int    `json:"query_port_offset"`
	DefaultProtocol string `json:"default_protocol"`

	// Every address this server holds, not just the primary one.
	//
	// The panel has always sent this and the daemon never read it, so a
	// container was published on Port alone. For a one-port game that is the
	// same thing; for TeamSpeak it meant 9987 worked while ServerQuery on
	// 10011 and file transfer on 30033 were allocated, shown in the UI and
	// opened in ufw, and then not mapped into the container at all.
	//
	// Empty means an older panel that does not send it, in which case Port is
	// still the whole truth.
	Ports []AllocatedPort `json:"ports"`

	// SteamCMD.
	SteamAppID     int    `json:"steam_app_id"`
	SteamAnonymous bool   `json:"steam_anonymous"`
	SteamBranch    string `json:"steam_branch"`

	// LinuxGSM.
	LGSMShortname string `json:"lgsm_shortname"`
}

// AllocatedPort is one address the panel has reserved for a server.
//
// Protocol is tcp, udp or both, and "both" is a real value here rather than a
// missing one: Mumble genuinely needs 64738 on each, TCP for control and text
// and UDP for voice, and opening one leaves a server that connects but where
// nobody can hear anybody.
type AllocatedPort struct {
	Port     int      `json:"port"`
	Protocol string   `json:"protocol"`
	Roles    []string `json:"roles"`
	Primary  bool     `json:"primary"`
}

// Protocols expands Protocol into the concrete ones to publish or open.
// Anything unrecognised, including the empty string, means both: an unreachable
// server is a worse outcome than one extra mapping.
func (p AllocatedPort) Protocols() []string {
	switch strings.ToLower(strings.TrimSpace(p.Protocol)) {
	case "tcp":
		return []string{"tcp"}
	case "udp":
		return []string{"udp"}
	}

	return []string{"tcp", "udp"}
}

// PublishedPorts is every port a container should expose, primary first.
//
// Falls back to the primary allocation alone when the panel sent no list,
// which is what an older panel produces and what every single-port game needs
// anyway.
func (s Server) PublishedPorts() []AllocatedPort {
	if len(s.Ports) == 0 {
		if s.Port <= 0 {
			return nil
		}

		return []AllocatedPort{{Port: s.Port, Protocol: s.DefaultProtocol, Primary: true}}
	}

	out := make([]AllocatedPort, 0, len(s.Ports))
	seen := map[int]bool{}

	for _, p := range s.Ports {
		if p.Port < 1 || p.Port > 65535 || seen[p.Port] {
			continue
		}
		seen[p.Port] = true
		out = append(out, p)
	}

	// The allocation the panel calls primary is what SERVER_PORT and every
	// address in the UI say, so it is published first and never dropped, even
	// if it somehow failed to appear in the list.
	if s.Port > 0 && !seen[s.Port] {
		out = append([]AllocatedPort{{Port: s.Port, Protocol: s.DefaultProtocol, Primary: true}}, out...)
	}

	return out
}

// Driver is what a runtime backend must provide. Every method takes a context
// so a wedged install cannot pin a goroutine forever.
type Driver interface {
	// Name is the runtime key the panel stores on a template: docker,
	// steamcmd or linuxgsm.
	Name() string

	// Available reports whether this node can actually use the driver right
	// now, for example whether the Docker socket answers or steamcmd is on
	// PATH. The panel shows this on the node's Overview tab.
	Available(ctx context.Context) (bool, string)

	// Install provisions a server for the first time, or re-provisions it.
	// Progress is written to w so the panel can stream the install log.
	Install(ctx context.Context, s Server, w io.Writer) error

	// Start, Stop, Restart and Kill move power state. Stop is graceful and
	// uses the template's stop command; Kill is not.
	Start(ctx context.Context, s Server) error
	Stop(ctx context.Context, s Server) error
	Restart(ctx context.Context, s Server) error
	Kill(ctx context.Context, s Server) error

	// Update runs the runtime's own update path: pull a new image, a
	// SteamCMD app_update, or lgsm update.
	Update(ctx context.Context, s Server, w io.Writer) error

	// Command writes a line to the server's stdin or RCON.
	Command(ctx context.Context, s Server, cmd string) error

	// Stats returns one sample.
	Stats(ctx context.Context, s Server) (Stats, error)

	// Logs streams console output to w until ctx is cancelled. tail is how
	// many historical lines to replay first.
	Logs(ctx context.Context, s Server, tail int, w io.Writer) error

	// Backlog returns the last n lines and returns immediately.
	//
	// Separate from Logs because Logs follows: against a running server it only
	// returns when the caller's context expires. The console page needs a
	// backlog before it can render anything, so calling Logs for it meant every
	// single page load sat on its two second timeout, measured and confirmed.
	Backlog(ctx context.Context, s Server, n int) ([]string, error)

	// Files.
	List(ctx context.Context, s Server, path string) ([]FileEntry, error)
	Read(ctx context.Context, s Server, path string) ([]byte, error)
	Write(ctx context.Context, s Server, path string, body []byte) error
	// Upload streams a file in from an open reader and returns its size.
	// Separate from Write because Write holds the whole body in memory, which
	// is fine for a config file and not fine for a modpack. maxBytes is the
	// cap the driver must enforce itself.
	Upload(ctx context.Context, s Server, path string, body io.Reader, maxBytes int64) (int64, error)
	Delete(ctx context.Context, s Server, paths []string) error
	Rename(ctx context.Context, s Server, from, to string) error
	MakeDir(ctx context.Context, s Server, path string) error

	// Backups.
	Backup(ctx context.Context, s Server, backupUUID string, ignore []string) (int64, string, error)
	Restore(ctx context.Context, s Server, backupUUID string) error

	// Destroy removes the server's data directory or container entirely.
	Destroy(ctx context.Context, s Server) error
}

// Registry maps a runtime name to its driver.
type Registry map[string]Driver

// Get returns the driver for a runtime, falling back to the stub driver when
// one is registered under "stub". A node configured with NODE_DRIVER=stub
// answers every runtime with synthetic data, which is what makes the panel
// fully exercisable before the real drivers exist.
func (r Registry) Get(name string) (Driver, bool) {
	if d, ok := r[name]; ok {
		return d, true
	}
	if d, ok := r["stub"]; ok {
		return d, true
	}
	return nil, false
}
