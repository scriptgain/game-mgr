package steamcmd

import (
	"context"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"strconv"

	"github.com/scriptgain/gamemgr-node/internal/runtime"
	"github.com/scriptgain/gamemgr-node/internal/supervise"
)

// Steam Workshop items, which are not like the other three catalogues at all.
//
// Modrinth, Hangar and SpigotMC hand the PANEL a file, which it verifies and
// streams to the node. A Workshop item cannot work that way: Valve serves it
// only to an authenticated Steam client, so it is steamcmd on the node that
// fetches it, and the panel never touches the bytes.
//
// Where the item lands is Steam's decision, not ours:
// <install dir>/steamapps/workshop/content/<app>/<item>. That is where the
// games that read Workshop content look, so it is left there.
//
// WHAT THIS DOES NOT DO, said plainly because the alternative is somebody
// discovering it: ARK expects mods unpacked into ShooterGame/Content/Mods with
// its own .z files expanded, and that conversion is not implemented. An ARK
// item downloads correctly and is not yet in the place ARK reads. Games that
// read straight out of the workshop directory, which includes Counter-Strike
// and Garry's Mod, work today.

// WorkshopInstaller is implemented by drivers that can fetch a Workshop item.
// Only steamcmd can: it is the only one with a Steam client.
type WorkshopInstaller interface {
	InstallWorkshopItem(ctx context.Context, s runtime.Server, appID, itemID int, w io.Writer) (string, error)
}

// InstallWorkshopItem downloads one item and returns its path inside the
// server's directory, relative to it, so the panel can record where it went.
func (d *Driver) InstallWorkshopItem(ctx context.Context, s runtime.Server, appID, itemID int, w io.Writer) (string, error) {
	if appID <= 0 || itemID <= 0 {
		return "", fmt.Errorf("a Steam app id and a workshop item id are both required")
	}

	// EnsureDir both creates it and hands it to the game account, which matters
	// here for the same reason it does on an install: steamcmd runs
	// unprivileged and a directory made by root gives it nowhere to write, with
	// no error and an empty result.
	dir, err := d.EnsureDir(s)
	if err != nil {
		return "", err
	}

	script, err := d.writeWorkshopRunscript(s, dir, appID, itemID)
	if err != nil {
		return "", err
	}
	defer os.Remove(script)

	fmt.Fprintf(w, "[steamcmd] workshop_download_item %d %d\n", appID, itemID)

	if err := d.runScriptFile(ctx, script, dir, w); err != nil {
		return "", err
	}

	rel := filepath.Join("steamapps", "workshop", "content", strconv.Itoa(appID), strconv.Itoa(itemID))

	// Steam reports success on its own exit code, which it is not reliable
	// about for workshop items, so the directory existing is the real check.
	if info, err := os.Stat(filepath.Join(dir, rel)); err != nil || !info.IsDir() {
		return "", fmt.Errorf("steamcmd finished but the item is not in %s, which usually means the app id is wrong or the item is not public", rel)
	}

	return "/" + filepath.ToSlash(rel), nil
}

func (d *Driver) writeWorkshopRunscript(s runtime.Server, dir string, appID, itemID int) (string, error) {
	login, err := d.loginLine(s)
	if err != nil {
		return "", err
	}

	body := "@ShutdownOnFailedCommand 1\n@NoPromptForPassword 1\n" +
		"force_install_dir " + dir + "\n" +
		login + "\n" +
		"workshop_download_item " + strconv.Itoa(appID) + " " + strconv.Itoa(itemID) + " validate\n" +
		"quit\n"

	runtimeDir := supervise.RuntimeDir(dir)
	if err := os.MkdirAll(runtimeDir, 0o700); err != nil {
		return "", err
	}

	script := filepath.Join(runtimeDir, ".gamemgr-workshop")
	if err := os.WriteFile(script, []byte(body), 0o600); err != nil {
		return "", err
	}

	// Same reason as the install runscript: created by root, read by an
	// unprivileged steamcmd, and it holds a Steam password while it exists.
	if err := d.OwnTree(runtimeDir); err != nil {
		return "", fmt.Errorf("hand the runscript to the game account: %w", err)
	}

	return script, nil
}
