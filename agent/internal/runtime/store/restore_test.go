package store

import (
	"context"
	"os"
	"path/filepath"
	"testing"

	"github.com/scriptgain/gamemgr-node/internal/runtime"
)

func testServer() runtime.Server {
	return runtime.Server{UUID: "11111111-2222-3333-4444-555555555555"}
}

func write(t *testing.T, path, body string) {
	t.Helper()
	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(path, []byte(body), 0o644); err != nil {
		t.Fatal(err)
	}
}

// Restore used to unpack over the top of whatever was there, so anything created
// after the backup survived. The confirm dialog promises the opposite: "Anything
// created since it was taken is gone." The code now matches the promise.
func TestRestoreReplacesRatherThanMerges(t *testing.T) {
	root := t.TempDir()
	store := New(root, nil)
	server := testServer()
	ctx := context.Background()

	dir, err := store.EnsureDir(server)
	if err != nil {
		t.Fatal(err)
	}

	write(t, filepath.Join(dir, "server.properties"), "level-name=world")
	write(t, filepath.Join(dir, "world", "level.dat"), "original world")

	if _, _, err := store.Backup(ctx, server, "backup-one", nil); err != nil {
		t.Fatalf("backup: %v", err)
	}

	// Everything that happens after the backup is taken.
	write(t, filepath.Join(dir, "world", "level.dat"), "world as it is now")
	write(t, filepath.Join(dir, "plugins", "something.jar"), "added later")
	if err := os.Remove(filepath.Join(dir, "server.properties")); err != nil {
		t.Fatal(err)
	}

	if err := store.Restore(ctx, server, "backup-one"); err != nil {
		t.Fatalf("restore: %v", err)
	}

	// The backed-up files are back, including the one that had been deleted.
	if body, err := os.ReadFile(filepath.Join(dir, "server.properties")); err != nil || string(body) != "level-name=world" {
		t.Fatalf("server.properties not restored: %q, %v", body, err)
	}
	if body, err := os.ReadFile(filepath.Join(dir, "world", "level.dat")); err != nil || string(body) != "original world" {
		t.Fatalf("level.dat should be the backed-up version, got %q, %v", body, err)
	}

	// And what was added afterwards is gone, which is the whole point.
	if _, err := os.Stat(filepath.Join(dir, "plugins", "something.jar")); !os.IsNotExist(err) {
		t.Fatal("a file created after the backup survived the restore, so this merged rather than replaced")
	}

	// The safety copy is cleaned up on success, not left filling the disk.
	safety := filepath.Join(root, ".restore-safety", Short(server.UUID))
	if entries, err := os.ReadDir(safety); err == nil && len(entries) > 0 {
		t.Fatalf("%d safety copies left behind after a successful restore", len(entries))
	}
}

// The dangerous half: a restore that fails must leave the server exactly as it
// was, not half wiped. Wiping first and unpacking second would make a corrupt
// archive terminal.
func TestAFailedRestoreLeavesTheServerIntact(t *testing.T) {
	root := t.TempDir()
	store := New(root, nil)
	server := testServer()
	ctx := context.Background()

	dir, err := store.EnsureDir(server)
	if err != nil {
		t.Fatal(err)
	}
	write(t, filepath.Join(dir, "world", "level.dat"), "irreplaceable")
	write(t, filepath.Join(dir, "server.properties"), "level-name=world")

	// A real backup, then corrupted, which is what a truncated upload or a bad
	// disk actually looks like.
	if _, _, err := store.Backup(ctx, server, "backup-one", nil); err != nil {
		t.Fatalf("backup: %v", err)
	}
	archive := filepath.Join(root, ".backups", Short(server.UUID), "backup-one.tar.gz")
	if err := os.WriteFile(archive, []byte("this is not a gzip stream"), 0o644); err != nil {
		t.Fatal(err)
	}

	if err := store.Restore(ctx, server, "backup-one"); err == nil {
		t.Fatal("restoring a corrupt archive should fail loudly")
	}

	// Nothing lost.
	if body, err := os.ReadFile(filepath.Join(dir, "world", "level.dat")); err != nil || string(body) != "irreplaceable" {
		t.Fatalf("the world was destroyed by a failed restore: %q, %v", body, err)
	}
	if body, err := os.ReadFile(filepath.Join(dir, "server.properties")); err != nil || string(body) != "level-name=world" {
		t.Fatalf("server.properties lost by a failed restore: %q, %v", body, err)
	}
}

// A restore against a server with no directory yet must still work.
func TestRestoreOntoNothing(t *testing.T) {
	root := t.TempDir()
	store := New(root, nil)
	server := testServer()
	ctx := context.Background()

	dir, err := store.EnsureDir(server)
	if err != nil {
		t.Fatal(err)
	}
	write(t, filepath.Join(dir, "server.properties"), "level-name=world")
	if _, _, err := store.Backup(ctx, server, "backup-one", nil); err != nil {
		t.Fatal(err)
	}

	if err := os.RemoveAll(dir); err != nil {
		t.Fatal(err)
	}

	if err := store.Restore(ctx, server, "backup-one"); err != nil {
		t.Fatalf("restore onto a missing directory: %v", err)
	}
	if _, err := os.Stat(filepath.Join(dir, "server.properties")); err != nil {
		t.Fatalf("nothing was restored: %v", err)
	}
}
