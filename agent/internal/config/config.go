// Package config holds the daemon's runtime configuration. A node is
// configured entirely by environment or by the small YAML-free config file the
// enrol one-liner writes; there is deliberately no database on a node.
package config

import (
	"os"
	"strconv"
	"strings"
)

type Config struct {
	// Listen address, for example ":8942".
	Listen string
	// Bearer token the panel must present. Written by the enrol step.
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
	// Panel base URL, used by reverse mode and by enrolment.
	PanelURL string
	// Enrol token, consumed once at first boot to obtain the real token.
	EnrolToken string
	// Seconds between heartbeats to the panel.
	HeartbeatInterval int
}

func Load() Config {
	c := Config{
		Listen:            env("NODE_LISTEN", ":8942"),
		Token:             env("NODE_TOKEN", ""),
		Name:              env("NODE_NAME", hostname()),
		Driver:            strings.ToLower(env("NODE_DRIVER", "")),
		Root:              env("NODE_ROOT", "/var/lib/gamemgr/volumes"),
		DockerSocket:      env("NODE_DOCKER_SOCKET", "/var/run/docker.sock"),
		PanelURL:          strings.TrimRight(env("NODE_PANEL_URL", ""), "/"),
		EnrolToken:        env("NODE_ENROL_TOKEN", ""),
		HeartbeatInterval: envInt("NODE_HEARTBEAT", 30),
	}
	return c
}

func env(k, def string) string {
	if v, ok := os.LookupEnv(k); ok && v != "" {
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
