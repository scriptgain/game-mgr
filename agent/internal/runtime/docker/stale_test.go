package docker

import (
	"testing"

	dockerapi "github.com/scriptgain/gamemgr-node/internal/docker"
	"github.com/scriptgain/gamemgr-node/internal/runtime"
)

// A container is frozen at the spec it was created with, and Start used to just
// start whatever was already there. That is why fixing the Mumble template did
// nothing until somebody pressed Reinstall, and why containers built before the
// multi-port fix still published one port.
//
// These pin the two halves of the answer: a container that no longer matches is
// recognised as stale, and one that does match is left alone, because
// recreating on every start would throw away the console history for nothing.

func server() runtime.Server {
	return runtime.Server{
		UUID:        "dba6af3a-00bc-49f7-8537-8d571344a8a9",
		Image:       "mumblevoip/mumble-server:latest",
		Startup:     "exec /entrypoint.sh /usr/bin/mumble-server",
		DataPath:    "/data",
		Port:        64738,
		Environment: map[string]string{"MUMBLE_CONFIG_users": "100"},
		Ports: []runtime.AllocatedPort{
			{Port: 64738, Protocol: "both", Primary: true},
		},
	}
}

// The container Docker would report for exactly that spec.
func matching(d *Driver, s runtime.Server) *dockerapi.Inspect {
	desired := d.config(s, d.Dir(s))

	info := &dockerapi.Inspect{ID: "abc123"}
	info.Config.Image = s.Image
	info.Config.Cmd = desired.Cmd
	// Docker also reports the image's own variables. Ours are a subset, which
	// is exactly the case the comparison has to allow for.
	info.Config.Env = append([]string{"PATH=/usr/bin", "LANG=C.UTF-8"}, desired.Env...)
	info.HostConfig.PortBindings = desired.HostConfig.PortBindings

	return info
}

func TestAMatchingContainerIsLeftAlone(t *testing.T) {
	d := New("/var/run/docker.sock", t.TempDir(), nil)
	s := server()

	if reason := d.stale(matching(d, s), s); reason != "" {
		t.Fatalf("an unchanged container should not be recreated, got %q", reason)
	}
}

// The Mumble case, exactly: the template's startup was fixed and the container
// kept running the old command.
func TestAChangedStartupIsStale(t *testing.T) {
	d := New("/var/run/docker.sock", t.TempDir(), nil)
	s := server()
	info := matching(d, s)

	s.Startup = "exec /usr/bin/mumble-server -fg"

	if reason := d.stale(info, s); reason == "" {
		t.Fatal("a changed startup command must recreate the container")
	}
}

func TestAChangedImageIsStale(t *testing.T) {
	d := New("/var/run/docker.sock", t.TempDir(), nil)
	s := server()
	info := matching(d, s)

	s.Image = "mumblevoip/mumble-server:v1.5.0"

	if reason := d.stale(info, s); reason == "" {
		t.Fatal("a changed image must recreate the container")
	}
}

// The multi-port fix left every existing container publishing one port. This is
// what makes those repair themselves on the next start.
func TestAnAddedAllocationIsStale(t *testing.T) {
	d := New("/var/run/docker.sock", t.TempDir(), nil)
	s := server()
	info := matching(d, s)

	s.Ports = append(s.Ports, runtime.AllocatedPort{Port: 10011, Protocol: "tcp"})

	if reason := d.stale(info, s); reason == "" {
		t.Fatal("a new allocation must recreate the container")
	}
}

// A variable the panel sets, changed on the Startup tab.
func TestAChangedEnvironmentIsStale(t *testing.T) {
	d := New("/var/run/docker.sock", t.TempDir(), nil)
	s := server()
	info := matching(d, s)

	s.Environment = map[string]string{"MUMBLE_CONFIG_users": "250"}

	if reason := d.stale(info, s); reason == "" {
		t.Fatal("a changed environment variable must recreate the container")
	}
}

// The image's own variables are not ours to police. If the base image adds one,
// that is not a reason to rebuild somebody's server.
func TestExtraEnvironmentFromTheImageIsNotStale(t *testing.T) {
	d := New("/var/run/docker.sock", t.TempDir(), nil)
	s := server()
	info := matching(d, s)
	info.Config.Env = append(info.Config.Env, "SOMETHING_THE_IMAGE_ADDED=1")

	if reason := d.stale(info, s); reason != "" {
		t.Fatalf("an image's own variables must not trigger a rebuild, got %q", reason)
	}
}
