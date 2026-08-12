package docker

import (
	"context"
	"fmt"
	"io"
	"sort"
	"strconv"
	"strings"
	"time"

	dockerapi "github.com/scriptgain/gamemgr-node/internal/docker"
	"github.com/scriptgain/gamemgr-node/internal/runtime"
)

// Where a Pterodactyl-format install script expects to find the server's own
// directory. Not configurable: every community script in the catalogue has
// this path written into it.
const installMountPath = "/mnt/server"

// What a template gets when it names no installer image. Debian with curl,
// tar and unzip, which is what the overwhelming majority of scripts assume.
const defaultInstallerImage = "ghcr.io/parkervcp/installers:debian"

// An install script downloads a whole game server, so it gets a long leash,
// but not an unbounded one: a script that hangs would otherwise hold the
// install open until the panel's own six hour timeout.
const installScriptTimeout = 2 * time.Hour

// runInstallScript executes a template's install script in a throwaway
// container with the server's data directory mounted at /mnt/server.
//
// This is how community templates actually get their game files. Their image
// is a bare runtime with no game in it at all, and the script fetches the
// server into /mnt/server. Skipping it leaves the data directory empty, the
// install reports success, and the server then fails to start with "not found"
// on a binary nobody ever downloaded. SA-MP failed exactly that way, and so
// would all two hundred and fifty templates in the catalogue.
//
// The container is separate from the game's own container on purpose. It runs
// as root because scripts routinely chown and install packages, it holds tools
// the game image does not, and it is destroyed either way when the script ends.
func (d *Driver) runInstallScript(ctx context.Context, s runtime.Server, path string, w io.Writer) error {
	script := normaliseScript(s.ScriptInstall)
	if script == "" {
		return nil
	}

	image := strings.TrimSpace(s.ScriptContainer)
	if image == "" {
		image = defaultInstallerImage
	}
	entry := strings.TrimSpace(s.ScriptEntry)
	if entry == "" {
		entry = "bash"
	}

	fmt.Fprintf(w, "[gamemgr] running the template's install script in %s\n", image)

	if !d.api.ImageExists(ctx, image) {
		if err := d.api.PullImage(ctx, image, w); err != nil {
			return fmt.Errorf("could not fetch the installer image %s: %w", image, err)
		}
	}

	ctx, cancel := context.WithTimeout(ctx, installScriptTimeout)
	defer cancel()

	name := "gamemgr-install-" + shortID(s.UUID)
	// A previous attempt that died without cleaning up would otherwise make
	// this fail with a name conflict rather than doing the obvious thing.
	_ = d.api.RemoveContainer(ctx, name, true)

	id, err := d.api.CreateContainer(ctx, name, dockerapi.ContainerConfig{
		Image:      image,
		Entrypoint: []string{entry},
		Cmd:        []string{"-c", script},
		Env:        installEnv(s),
		WorkingDir: installMountPath,
		HostConfig: &dockerapi.HostConfig{
			Binds:       []string{path + ":" + installMountPath},
			NetworkMode: "bridge",
		},
	})
	if err != nil {
		return fmt.Errorf("could not create the installer container: %w", err)
	}
	defer func() { _ = d.api.RemoveContainer(context.WithoutCancel(ctx), id, true) }()

	if err := d.api.StartContainer(ctx, id); err != nil {
		return fmt.Errorf("could not start the installer container: %w", err)
	}

	// Streamed rather than collected, because this is the only thing on screen
	// for what can be a very long download.
	if err := d.api.Logs(ctx, id, 0, true, w); err != nil {
		fmt.Fprintf(w, "[gamemgr] lost the installer's output (%v); still waiting for it to finish\n", err)
	}

	code, err := d.api.Wait(ctx, id)
	if err != nil {
		return fmt.Errorf("lost track of the installer container: %w", err)
	}
	if code != 0 {
		return fmt.Errorf("the template's install script exited %d", code)
	}

	// The script ran as root and everything it wrote is root owned, which the
	// game then cannot touch. Handing the tree over is not optional.
	if err := d.OwnTree(path); err != nil {
		return fmt.Errorf("install script finished but its files could not be handed to the game account: %w", err)
	}

	// A script that exits 0 having downloaded nothing is common enough to be
	// worth calling out. Community scripts rarely check curl or tar, so a dead
	// URL or a missing variable produces "Installation completed..." over an
	// empty directory, and the real failure surfaces much later as a startup
	// command naming a file that was never fetched. SA-MP does exactly this:
	// its egg declares a variable called Version and its script reads $VERSION.
	//
	// A warning, not an error. Some templates legitimately install almost
	// nothing and generate their files on first boot, and refusing those would
	// be worse than the confusion this prevents.
	if size := d.DiskUsageMiB(s); size < 1 {
		fmt.Fprintln(w, "[gamemgr] WARNING: the install script finished but the data directory is essentially empty.")
		fmt.Fprintln(w, "[gamemgr] Most likely its download failed without saying so. Check the output above for")
		fmt.Fprintln(w, "[gamemgr] curl or tar errors, and check that the template's variable names match the")
		fmt.Fprintln(w, "[gamemgr] names its script actually reads.")
	}

	fmt.Fprintln(w, "[gamemgr] install script finished")

	return nil
}

// installEnv is the server's environment plus the three names every community
// script reads. SERVER_MEMORY and the port are genuinely used by some of them
// to write a config file during installation.
func installEnv(s runtime.Server) []string {
	keys := make([]string, 0, len(s.Environment))
	for k := range s.Environment {
		keys = append(keys, k)
	}
	sort.Strings(keys)

	env := make([]string, 0, len(keys)+4)
	for _, k := range keys {
		env = append(env, k+"="+s.Environment[k])
	}

	return append(env,
		"SERVER_MEMORY="+strconv.FormatInt(s.MemoryMiB, 10),
		"SERVER_IP=0.0.0.0",
		"SERVER_PORT="+strconv.Itoa(s.Port),
		// Pterodactyl sets this and a handful of scripts branch on it.
		"P_SERVER_UUID="+s.UUID,
	)
}

// normaliseScript makes a community install script runnable.
//
// 226 of the 249 scripts in the catalogue have CRLF line endings, because they
// were written on Windows. bash does not care about the file, it cares about
// the bytes: with \r ending every line, each command becomes "curl\r" and the
// script dies with exit 2 after a run of "command not found" that names
// commands which plainly exist. SA-MP failed exactly this way.
//
// Also strips a leading BOM, which produces the same class of unreadable
// failure on the very first line only.
func normaliseScript(script string) string {
	script = strings.TrimPrefix(script, "\ufeff")
	script = strings.ReplaceAll(script, "\r\n", "\n")
	// A lone CR is rarer and worse, because it leaves the whole script on one
	// line rather than failing per line.
	script = strings.ReplaceAll(script, "\r", "\n")

	return strings.TrimSpace(script)
}

func shortID(uuid string) string {
	clean := strings.ReplaceAll(uuid, "-", "")
	if len(clean) > 12 {
		return clean[:12]
	}

	return clean
}
