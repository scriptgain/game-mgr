package store

import (
	"context"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"testing"

	"github.com/scriptgain/gamemgr-node/internal/supervise"
)

// Ownership had been fixed five separate times, each in a different code path,
// and a sixth was still open: Restore chowned nothing it unpacked. None of it
// was ever caught by a test, because chowning needs root and the test suite does
// not run as root.
//
// So chown is a package variable. These tests replace it with a recorder and
// assert which paths a store hands to the game account, which is the part that
// was actually wrong every time. Nothing here needs privilege.

const (
	gameUID = 1001
	gameGID = 1001
)

// recorder swaps out chown for the duration of a test and collects the paths.
type recorder struct {
	paths []string
	uids  []int
}

func record(t *testing.T) *recorder {
	t.Helper()
	r := &recorder{}
	original := chown
	chown = func(path string, uid, gid int) error {
		r.paths = append(r.paths, path)
		r.uids = append(r.uids, uid)

		return nil
	}
	t.Cleanup(func() { chown = original })

	return r
}

// relative reports the recorded paths relative to base, sorted, so assertions
// read as the tree the game was handed rather than as temp directory noise.
func (r *recorder) relative(base string) []string {
	out := make([]string, 0, len(r.paths))
	for _, path := range r.paths {
		rel, err := filepath.Rel(base, path)
		if err != nil {
			rel = path
		}
		out = append(out, rel)
	}
	sort.Strings(out)

	return out
}

func (r *recorder) sawPath(base, want string) bool {
	for _, got := range r.relative(base) {
		if got == want {
			return true
		}
	}

	return false
}

func gameAccount() *supervise.Credential {
	return &supervise.Credential{Name: "gamemgr", Uid: gameUID, Gid: gameGID}
}

// The single first-creation point for every runtime. Docker, SteamCMD and
// LinuxGSM all call this before anything else, so a chown here is one every
// driver gets without having to remember it.
func TestEnsureDirHandsTheServerDirectoryOver(t *testing.T) {
	seen := record(t)
	store := New(t.TempDir(), gameAccount())
	server := testServer()

	dir, err := store.EnsureDir(server)
	if err != nil {
		t.Fatal(err)
	}

	if len(seen.paths) != 1 || seen.paths[0] != dir {
		t.Fatalf("EnsureDir chowned %v, want exactly [%s]", seen.paths, dir)
	}
	if seen.uids[0] != gameUID {
		t.Fatalf("handed to uid %d, want %d", seen.uids[0], gameUID)
	}
}

// The bug this whole pass exists for. A tar carries the uid the file had when it
// was archived and nothing honoured it, so every restored file landed owned by
// root. LinuxGSM hid it by re-chowning on every start; Docker and SteamCMD had
// no such habit and were left with a server that could read its files and never
// write them.
func TestRestoreHandsEveryUnpackedFileOver(t *testing.T) {
	record(t) // building the fixture must not need root either
	root := t.TempDir()
	store := New(root, gameAccount())
	server := testServer()
	ctx := context.Background()

	dir, err := store.EnsureDir(server)
	if err != nil {
		t.Fatal(err)
	}
	write(t, filepath.Join(dir, "server.properties"), "level-name=world")
	write(t, filepath.Join(dir, "world", "level.dat"), "a world")
	write(t, filepath.Join(dir, "plugins", "nested", "deep.jar"), "a plugin")

	if _, _, err := store.Backup(ctx, server, "backup-one", nil); err != nil {
		t.Fatalf("backup: %v", err)
	}

	// Only the restore is watched, so the recording is not full of the writes
	// that built the fixture.
	seen := record(t)
	if err := store.Restore(ctx, server, "backup-one"); err != nil {
		t.Fatalf("restore: %v", err)
	}

	for _, want := range []string{
		".",
		"server.properties",
		"world",
		filepath.Join("world", "level.dat"),
		"plugins",
		filepath.Join("plugins", "nested"),
		filepath.Join("plugins", "nested", "deep.jar"),
	} {
		if !seen.sawPath(dir, want) {
			t.Errorf("restore left %q owned by root; it chowned %v", want, seen.relative(dir))
		}
	}
}

// Moving a file into a folder that does not exist creates that folder, and it
// was being created root-owned. The rename succeeded, so the panel reported
// success and the game then could not write into the directory its own file had
// just been moved into.
func TestRenameHandsOverADirectoryItHadToCreate(t *testing.T) {
	record(t)
	store := New(t.TempDir(), gameAccount())
	server := testServer()
	ctx := context.Background()

	dir, err := store.EnsureDir(server)
	if err != nil {
		t.Fatal(err)
	}
	write(t, filepath.Join(dir, "config.yml"), "a: 1")

	seen := record(t)
	if err := store.Rename(ctx, server, "config.yml", "backups/old/config.yml"); err != nil {
		t.Fatalf("rename: %v", err)
	}

	for _, want := range []string{"backups", filepath.Join("backups", "old")} {
		if !seen.sawPath(dir, want) {
			t.Errorf("rename left %q owned by root; it chowned %v", want, seen.relative(dir))
		}
	}
}

// A daemon that is not root has nothing to hand over: everything it creates
// already belongs to the right user. Chowning anyway would fail on every call
// and turn a working unprivileged install into a broken one.
func TestAnUnprivilegedDaemonChownsNothing(t *testing.T) {
	seen := record(t)
	store := New(t.TempDir(), nil)
	server := testServer()
	ctx := context.Background()

	dir, err := store.EnsureDir(server)
	if err != nil {
		t.Fatal(err)
	}
	if err := store.Write(ctx, server, "server.properties", []byte("x=1")); err != nil {
		t.Fatal(err)
	}
	if err := store.MakeDir(ctx, server, "plugins"); err != nil {
		t.Fatal(err)
	}
	if err := store.OwnTree(dir); err != nil {
		t.Fatal(err)
	}

	if len(seen.paths) != 0 {
		t.Fatalf("an unprivileged daemon chowned %v, want nothing", seen.paths)
	}
}

// OwnTree walks a tree it did not write, which on a restore means a tree built
// from an archive somebody else could have crafted. It must hand over the
// symlink itself and never what the symlink points at: production uses Lchown
// for exactly this reason, and a Walk that followed links would let a backup
// containing "link -> /etc/shadow" hand that file to the game account.
func TestOwnTreeDoesNotFollowASymlinkOutOfTheTree(t *testing.T) {
	record(t)
	root := t.TempDir()
	store := New(root, gameAccount())
	server := testServer()

	dir, err := store.EnsureDir(server)
	if err != nil {
		t.Fatal(err)
	}

	outside := filepath.Join(root, "outside-the-server")
	write(t, filepath.Join(outside, "secret"), "not the game's to own")
	if err := os.Symlink(filepath.Join(outside, "secret"), filepath.Join(dir, "escape")); err != nil {
		t.Skipf("this filesystem will not make symlinks: %v", err)
	}

	seen := record(t)
	if err := store.OwnTree(dir); err != nil {
		t.Fatal(err)
	}

	if !seen.sawPath(dir, "escape") {
		t.Error("the symlink itself was never handed over")
	}
	for _, path := range seen.paths {
		if strings.HasPrefix(path, outside) {
			t.Fatalf("OwnTree followed a symlink out of the server directory and touched %s", path)
		}
	}
}
