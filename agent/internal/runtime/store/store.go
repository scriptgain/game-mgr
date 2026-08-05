// Package store holds everything a runtime does to a server's directory on
// disk: browsing, editing, archiving and restoring.
//
// None of it is runtime-specific. A Docker server, a SteamCMD server and a
// LinuxGSM server all keep their files in a directory on the node and all need
// byte-identical behaviour when the panel asks to list or delete something.
// Written three times it would mean three copies of the path traversal guard,
// and the third one would be the one with the bug in it.
//
// Drivers embed Store, so these methods satisfy most of runtime.Driver for free.
package store

import (
	"archive/tar"
	"compress/gzip"
	"context"
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"sort"
	"strconv"
	"strings"
	"time"

	"github.com/scriptgain/gamemgr-node/internal/runtime"
)

// Store owns the node's data root and derives every server directory from it.
type Store struct {
	Root string
}

func New(root string) Store {
	if root == "" {
		root = "/var/lib/gamemgr/volumes"
	}

	return Store{Root: root}
}

// Short is the directory name a server gets. Derived from the uuid rather than
// the name, because a name can be changed and a directory cannot follow it.
func Short(uuid string) string {
	clean := strings.ReplaceAll(uuid, "-", "")
	if len(clean) >= 12 {
		return clean[:12]
	}

	return clean
}

// Dir is the server's own directory on the node.
func (s Store) Dir(server runtime.Server) string {
	return filepath.Join(s.Root, Short(server.UUID))
}

func (s Store) EnsureDir(server runtime.Server) (string, error) {
	dir := s.Dir(server)
	if err := os.MkdirAll(dir, 0o755); err != nil {
		return "", fmt.Errorf("create data directory: %w", err)
	}

	return dir, nil
}

// Resolve turns a path from the panel into an absolute path inside the server's
// own directory, or refuses.
//
// This guard lives in the daemon as well as in the panel on purpose: the daemon
// must not depend on its caller having sanitised anything, or a bug up there
// becomes arbitrary filesystem access down here.
func (s Store) Resolve(server runtime.Server, path string) (string, error) {
	base := s.Dir(server)
	full := filepath.Clean(filepath.Join(base, filepath.Clean("/"+path)))

	rel, err := filepath.Rel(base, full)
	if err != nil || rel == ".." || strings.HasPrefix(rel, ".."+string(os.PathSeparator)) {
		return "", fmt.Errorf("path escapes the server directory")
	}

	return full, nil
}

// --------------------------------------------------------------------- files

func (s Store) List(_ context.Context, server runtime.Server, path string) ([]runtime.FileEntry, error) {
	full, err := s.Resolve(server, path)
	if err != nil {
		return nil, err
	}

	items, err := os.ReadDir(full)
	if err != nil {
		if os.IsNotExist(err) {
			return []runtime.FileEntry{}, nil
		}

		return nil, err
	}

	out := make([]runtime.FileEntry, 0, len(items))
	for _, item := range items {
		info, err := item.Info()
		if err != nil {
			continue
		}
		out = append(out, runtime.FileEntry{
			Name:       item.Name(),
			Directory:  item.IsDir(),
			Symlink:    info.Mode()&os.ModeSymlink != 0,
			Size:       info.Size(),
			Mode:       fmt.Sprintf("%04o", info.Mode().Perm()),
			MimeType:   MimeOf(item.Name(), item.IsDir()),
			ModifiedAt: info.ModTime(),
		})
	}

	sort.Slice(out, func(i, j int) bool {
		if out[i].Directory != out[j].Directory {
			return out[i].Directory
		}

		return strings.ToLower(out[i].Name) < strings.ToLower(out[j].Name)
	})

	return out, nil
}

// Read is capped: the file manager is for configuration, not for streaming a
// 40 GiB world file into a browser.
func (s Store) Read(_ context.Context, server runtime.Server, path string) ([]byte, error) {
	full, err := s.Resolve(server, path)
	if err != nil {
		return nil, err
	}

	info, err := os.Stat(full)
	if err != nil {
		return nil, err
	}
	const maxRead = 8 << 20
	if info.Size() > maxRead {
		return nil, fmt.Errorf("that file is %d MiB, too large to open in the editor", info.Size()/(1024*1024))
	}

	return os.ReadFile(full)
}

func (s Store) Write(_ context.Context, server runtime.Server, path string, body []byte) error {
	full, err := s.Resolve(server, path)
	if err != nil {
		return err
	}
	if err := os.MkdirAll(filepath.Dir(full), 0o755); err != nil {
		return err
	}

	return os.WriteFile(full, body, 0o644)
}

func (s Store) Delete(_ context.Context, server runtime.Server, paths []string) error {
	for _, path := range paths {
		full, err := s.Resolve(server, path)
		if err != nil {
			return err
		}
		// Never the server's own root, whatever was asked for.
		if full == s.Dir(server) {
			return fmt.Errorf("refusing to delete the server directory itself")
		}
		if err := os.RemoveAll(full); err != nil {
			return err
		}
	}

	return nil
}

func (s Store) Rename(_ context.Context, server runtime.Server, from, to string) error {
	source, err := s.Resolve(server, from)
	if err != nil {
		return err
	}
	target, err := s.Resolve(server, to)
	if err != nil {
		return err
	}
	if err := os.MkdirAll(filepath.Dir(target), 0o755); err != nil {
		return err
	}

	return os.Rename(source, target)
}

func (s Store) MakeDir(_ context.Context, server runtime.Server, path string) error {
	full, err := s.Resolve(server, path)
	if err != nil {
		return err
	}

	return os.MkdirAll(full, 0o755)
}

// ------------------------------------------------------------------- backups

// Backup writes a gzipped tar of the server directory and returns its size and
// checksum. The checksum is computed while writing rather than by reading the
// archive back, so a multi-gigabyte backup is not walked twice.
func (s Store) Backup(ctx context.Context, server runtime.Server, backupUUID string, ignore []string) (int64, string, error) {
	source := s.Dir(server)
	backupDir := filepath.Join(s.Root, ".backups", Short(server.UUID))
	if err := os.MkdirAll(backupDir, 0o755); err != nil {
		return 0, "", err
	}

	target := filepath.Join(backupDir, backupUUID+".tar.gz")
	file, err := os.Create(target)
	if err != nil {
		return 0, "", err
	}
	defer file.Close()

	hash := sha256.New()
	counter := &countingWriter{}
	gz := gzip.NewWriter(io.MultiWriter(file, hash, counter))
	archive := tar.NewWriter(gz)

	walkErr := filepath.Walk(source, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return err
		}
		if ctx.Err() != nil {
			return ctx.Err()
		}

		rel, err := filepath.Rel(source, path)
		if err != nil || rel == "." {
			return nil
		}
		if skip(rel, ignore) {
			if info.IsDir() {
				return filepath.SkipDir
			}

			return nil
		}
		// A socket or device node cannot be archived and is not worth failing
		// the whole backup over. A game server's directory routinely has one.
		if !info.Mode().IsRegular() && !info.IsDir() {
			return nil
		}

		header, err := tar.FileInfoHeader(info, "")
		if err != nil {
			return err
		}
		header.Name = rel
		if err := archive.WriteHeader(header); err != nil {
			return err
		}
		if info.IsDir() {
			return nil
		}

		handle, err := os.Open(path)
		if err != nil {
			return err
		}
		defer handle.Close()
		_, err = io.Copy(archive, handle)

		return err
	})

	if walkErr != nil {
		_ = archive.Close()
		_ = gz.Close()
		_ = os.Remove(target)

		return 0, "", walkErr
	}

	if err := archive.Close(); err != nil {
		return 0, "", err
	}
	if err := gz.Close(); err != nil {
		return 0, "", err
	}

	return counter.n, hex.EncodeToString(hash.Sum(nil)), nil
}

// Restore replaces the server's contents with the backup's.
//
// Replaces, not merges. The panel's confirm dialog promises "anything created
// since it was taken is gone", and unpacking over the top quietly did the
// opposite: a corrupted file absent from the archive would survive the very
// restore meant to get rid of it.
//
// The current contents are moved aside first rather than deleted, and only
// dropped once the unpack has fully succeeded. A restore that fails halfway
// therefore leaves the server exactly as it was instead of half wiped, and
// restoring the wrong backup is recoverable rather than terminal.
func (s Store) Restore(ctx context.Context, server runtime.Server, backupUUID string) error {
	archivePath := filepath.Join(s.Root, ".backups", Short(server.UUID), backupUUID+".tar.gz")
	file, err := os.Open(archivePath)
	if err != nil {
		return err
	}
	defer file.Close()

	gz, err := gzip.NewReader(file)
	if err != nil {
		return err
	}
	defer gz.Close()

	dir := s.Dir(server)
	safety, err := s.setAside(server, dir)
	if err != nil {
		return err
	}

	if err := os.MkdirAll(dir, 0o755); err != nil {
		s.putBack(safety, dir)

		return err
	}

	if err := s.unpack(ctx, server, gz); err != nil {
		s.putBack(safety, dir)

		return err
	}

	// Only now is the old state expendable.
	if safety != "" {
		_ = os.RemoveAll(safety)
	}

	return nil
}

func (s Store) unpack(ctx context.Context, server runtime.Server, gz io.Reader) error {
	reader := tar.NewReader(gz)
	for {
		header, err := reader.Next()
		if err == io.EOF {
			return nil
		}
		if err != nil {
			return err
		}
		if ctx.Err() != nil {
			return ctx.Err()
		}

		// The archive is not trusted just because this daemon wrote it: a
		// tampered or hand-built one must not be able to write outside the
		// server directory.
		full, err := s.Resolve(server, header.Name)
		if err != nil {
			return err
		}

		switch header.Typeflag {
		case tar.TypeDir:
			if err := os.MkdirAll(full, os.FileMode(header.Mode)); err != nil {
				return err
			}
		case tar.TypeReg:
			if err := os.MkdirAll(filepath.Dir(full), 0o755); err != nil {
				return err
			}
			out, err := os.OpenFile(full, os.O_CREATE|os.O_TRUNC|os.O_WRONLY, os.FileMode(header.Mode))
			if err != nil {
				return err
			}
			if _, err := io.Copy(out, reader); err != nil {
				_ = out.Close()

				return err
			}
			_ = out.Close()
		}
	}
}

// setAside renames the server directory out of the way. A rename rather than a
// copy, so a 40 GiB world costs nothing and cannot half-fail.
func (s Store) setAside(server runtime.Server, dir string) (string, error) {
	if _, err := os.Stat(dir); err != nil {
		if os.IsNotExist(err) {
			return "", nil
		}

		return "", err
	}

	holding := filepath.Join(s.Root, ".restore-safety", Short(server.UUID))
	if err := os.MkdirAll(holding, 0o755); err != nil {
		return "", err
	}

	safety := filepath.Join(holding, strconv.FormatInt(time.Now().UnixNano(), 10))
	if err := os.Rename(dir, safety); err != nil {
		return "", fmt.Errorf("could not set the current files aside, so nothing was changed: %w", err)
	}

	return safety, nil
}

// putBack undoes setAside after a failed restore.
func (s Store) putBack(safety, dir string) {
	if safety == "" {
		return
	}
	_ = os.RemoveAll(dir)
	_ = os.Rename(safety, dir)
}

// DestroyDir removes the server's data entirely. Separate from the driver's
// Destroy, which also has a container or a process to deal with first.
func (s Store) DestroyDir(server runtime.Server) error {
	// The daemon's own working files live in a sibling directory so they stay
	// out of the file manager and out of backups, which also means deleting the
	// server directory no longer takes them with it.
	for _, sibling := range []string{".runtime", ".backups", ".restore-safety"} {
		_ = os.RemoveAll(filepath.Join(s.Root, sibling, Short(server.UUID)))
	}

	return os.RemoveAll(s.Dir(server))
}

// DiskUsageMiB is what the panel charges against the server's disk limit.
func (s Store) DiskUsageMiB(server runtime.Server) int64 {
	var total int64
	_ = filepath.Walk(s.Dir(server), func(_ string, info os.FileInfo, err error) error {
		if err == nil && info.Mode().IsRegular() {
			total += info.Size()
		}

		return nil
	})

	return total / (1024 * 1024)
}

// ------------------------------------------------------------------- helpers

func skip(rel string, ignore []string) bool {
	for _, pattern := range ignore {
		pattern = strings.TrimSpace(strings.TrimSuffix(pattern, "/"))
		if pattern == "" {
			continue
		}
		if rel == pattern || strings.HasPrefix(rel, pattern+"/") {
			return true
		}
		if matched, _ := filepath.Match(pattern, filepath.Base(rel)); matched {
			return true
		}
	}

	return false
}

type countingWriter struct{ n int64 }

func (c *countingWriter) Write(p []byte) (int, error) {
	c.n += int64(len(p))

	return len(p), nil
}

func MimeOf(name string, dir bool) string {
	if dir {
		return "inode/directory"
	}
	switch strings.ToLower(filepath.Ext(name)) {
	case ".json":
		return "application/json"
	case ".yml", ".yaml":
		return "text/yaml"
	case ".properties", ".cfg", ".ini", ".txt", ".log", ".sh", ".conf", ".toml", ".md":
		return "text/plain"
	case ".jar":
		return "application/java-archive"
	}

	return "application/octet-stream"
}
