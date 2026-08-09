// Package config holds the daemon's runtime configuration. A node is
// configured entirely by environment or by the small YAML-free config file the
// enroll one-liner writes; there is deliberately no database on a node.
package config

import (
	"os"
	"strconv"
	"strings"
)

type Config struct {
	// Listen address, for example ":8942".
	Listen string
	// Bearer token the panel must present. Written by the enroll step.
	Token string
	// Human name reported back to the panel.
	Name string
	// Which driver to force. Empty means "pick per server runtime".
	// "stub" makes every runtime answer with synthetic data.
	Driver string
	// Where server data lives.
	Root string
	// Docker socket path.
	DockerSocket string
	// Panel base URL, used by reverse mode and by enrollment.
	PanelURL string
	// Enroll token, consumed once at first boot to obtain the real token.
	EnrollToken string
	// Seconds between heartbeats to the panel.
	HeartbeatInterval int
	// The largest file the file manager will accept on this node, in MiB.
	//
	// The panel has its own per-node limit and checks it before it starts
	// sending, but this one is the node's own and is applied whatever the
	// panel asked for. A daemon that took its cap from the request would have
	// no cap at all the day something else starts talking to it.
	MaxUploadMiB int
	// The env file this daemon was configured from. Enrollment rewrites it so
	// the long-lived token survives a restart; without that, a restart would
	// try to enroll again with a token the panel has already spent and the node
	// would come back unenrolled.
	ConfigFile string
	// Where SFTP listens, for example ":2022". Empty turns it off entirely,
	// which is the right answer for a node whose customers only ever use the
	// panel's file manager and a listener that answers passwords on the public
	// internet is one more thing to attack.
	SFTPListen string
	// The node's SSH host key. Generated on first run and then kept, because a
	// key that changed on every restart would give every client the warning
	// that normally means the connection is being intercepted.
	SFTPHostKey string
}

func Load() Config {
	c := Config{
		Listen:       env("NODE_LISTEN", ":8942"),
		Token:        env("NODE_TOKEN", ""),
		Name:         env("NODE_NAME", hostname()),
		Driver:       strings.ToLower(env("NODE_DRIVER", "")),
		Root:         env("NODE_ROOT", "/var/lib/gamemgr/volumes"),
		DockerSocket: env("NODE_DOCKER_SOCKET", "/var/run/docker.sock"),
		PanelURL:     strings.TrimRight(env("NODE_PANEL_URL", ""), "/"),
		// Backward compatibility: node.env files written by installers older
		// than the enrol/enroll rename still hold NODE_ENROL_TOKEN. Reading the
		// legacy key second means a box that is upgraded binary-first, before
		// its config file is rewritten, still enrolls instead of sitting there
		// with a token it cannot see. Safe to drop once no field node predates
		// the rename.
		EnrollToken:       env("NODE_ENROLL_TOKEN", env("NODE_ENROL_TOKEN", "")),
		HeartbeatInterval: envInt("NODE_HEARTBEAT", 30),
		MaxUploadMiB:      envInt("NODE_MAX_UPLOAD", 4096),
		ConfigFile:        env("NODE_CONFIG_FILE", "/etc/gamemgr-node/node.env"),
		// Not env(), which treats an empty value as absent and would hand back
		// the default. "NODE_SFTP_LISTEN=" in the config file is how an operator
		// turns file access off, and it has to mean off rather than :2022.
		SFTPListen:  lookup("NODE_SFTP_LISTEN", ":2022"),
		SFTPHostKey: env("NODE_SFTP_HOST_KEY", "/etc/gamemgr-node/ssh_host_ed25519_key"),
	}
	return c
}

func env(k, def string) string {
	if v, ok := os.LookupEnv(k); ok && v != "" {
		return v
	}
	return def
}

// lookup is env() without the "empty means unset" rule: a variable that is
// present and empty is an explicit empty value.
func lookup(k, def string) string {
	if v, ok := os.LookupEnv(k); ok {
		return v
	}
	return def
}

func envInt(k string, def int) int {
	if v, ok := os.LookupEnv(k); ok {
		if n, err := strconv.Atoi(v); err == nil {
			return n
		}
	}
	return def
}

func hostname() string {
	h, err := os.Hostname()
	if err != nil {
		return "gamemgr-node"
	}
	return h
}
