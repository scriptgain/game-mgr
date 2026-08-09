package store

import (
	"archive/tar"
	"archive/zip"
	"compress/gzip"
	"context"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"strings"

	"github.com/scriptgain/gamemgr-node/internal/runtime"
)

/*
Compressing and extracting files in place.

`file.archive` has been a permission in the panel since the beginning with
nothing anywhere implementing it, so an administrator could tick the box and
grant nothing. This is the half that does the work.

Everything here goes through Resolve, so a path from the panel cannot reach
outside the server's directory, and everything it creates goes through Own, so
the game can read what it made. The tar reading and writing is the same shape
Backup and Restore already use; what is different is that the destination is
inside the server rather than in the backups directory beside it.
*/

// Archive writes the named paths into one gzipped tar inside the server's own
// directory.
//
// Relative names inside the archive, always. An archive holding absolute paths
// unpacks into wherever those paths point on whatever machine opens it, which
// is somebody else's problem the moment they download it.
func (s Store) Archive(ctx context.Context, server runtime.Server, paths []string, target string) error {
	if len(paths) == 0 {
		return fmt.Errorf("nothing to compress")
	}

	full, err := s.ResolveWrite(server, target)
	if err != nil {
		return err
	}
	base := s.Dir(server)

	// Written to a scratch name and renamed into place, so a failure halfway
	// leaves no half an archive sitting where somebody will try to open it.
	scratch, err := os.CreateTemp(filepath.Dir(full), ".gamemgr-archive-*")
	if err != nil {
		return err
	}
	defer func() {
		_ = scratch.Close()
		_ = os.Remove(scratch.Name())
	}()

	gz := gzip.NewWriter(scratch)
	writer := tar.NewWriter(gz)

	for _, path := range paths {
		source, err := s.Resolve(server, path)
		if err != nil {
			return err
		}
		if err := s.addToArchive(ctx, writer, base, source); err != nil {
			return err
		}
	}

	if err := writer.Close(); err != nil {
		return err
	}
	if err := gz.Close(); err != nil {
		return err
	}
	if err := scratch.Close(); err != nil {
		return err
	}
	if err := os.Chmod(scratch.Name(), 0o644); err != nil {
		return err
	}
	if err := s.Own(scratch.Name()); err != nil {
		return err
	}

	return os.Rename(scratch.Name(), full)
}

func (s Store) addToArchive(ctx context.Context, writer *tar.Writer, base, source string) error {
	return filepath.Walk(source, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return err
		}
		if ctx.Err() != nil {
			return ctx.Err()
		}
		// Symlinks are skipped rather than followed. Following one would copy
		// whatever it points at into an archive the customer then downloads.
		if info.Mode()&os.ModeSymlink != 0 {
			return nil
		}

		name, err := filepath.Rel(base, path)
		if err != nil {
			return err
		}

		header, err := tar.FileInfoHeader(info, "")
		if err != nil {
			return err
		}
		header.Name = filepath.ToSlash(name)

		if err := writer.WriteHeader(header); err != nil {
			return err
		}
		if info.IsDir() {
			return nil
		}

		file, err := os.Open(path)
		if err != nil {
			return err
		}
		defer file.Close()

		_, err = io.Copy(writer, file)

		return err
	})
}

// Extract unpacks an archive into the directory it sits in.
//
// Both tar.gz and zip, because the two things a customer actually has are a
// modpack from the internet and a world somebody sent them, and those are a zip
// about as often as they are a tarball.
func (s Store) Extract(ctx context.Context, server runtime.Server, path string) error {
	full, err := s.Resolve(server, path)
	if err != nil {
		return err
	}

	into := filepath.Dir(full)
	lower := strings.ToLower(full)

	switch {
	case strings.HasSuffix(lower, ".zip"):
		return s.extractZip(ctx, server, full, into)
	case strings.HasSuffix(lower, ".tar.gz"), strings.HasSuffix(lower, ".tgz"), strings.HasSuffix(lower, ".tar"):
		return s.extractTar(ctx, server, full, into)
	}

	return fmt.Errorf("%s is not an archive this can open", filepath.Base(full))
}

func (s Store) extractTar(ctx context.Context, server runtime.Server, archive, into string) error {
	file, err := os.Open(archive)
	if err != nil {
		return err
	}
	defer file.Close()

	var reader io.Reader = file
	if !strings.HasSuffix(strings.ToLower(archive), ".tar") {
		gz, err := gzip.NewReader(file)
		if err != nil {
			return err
		}
		defer gz.Close()
		reader = gz
	}

	tr := tar.NewReader(reader)
	for {
		header, err := tr.Next()
		if err == io.EOF {
			return nil
		}
		if err != nil {
			return err
		}
		if ctx.Err() != nil {
			return ctx.Err()
		}

		if err := s.unpackEntry(server, into, header.Name, header.Typeflag == tar.TypeDir, os.FileMode(header.Mode), tr); err != nil {
			return err
		}
	}
}

func (s Store) extractZip(ctx context.Context, server runtime.Server, archive, into string) error {
	reader, err := zip.OpenReader(archive)
	if err != nil {
		return err
	}
	defer reader.Close()

	for _, entry := range reader.File {
		if ctx.Err() != nil {
			return ctx.Err()
		}

		opened, err := entry.Open()
		if err != nil {
			return err
		}
		err = s.unpackEntry(server, into, entry.Name, entry.FileInfo().IsDir(), entry.Mode(), opened)
		_ = opened.Close()
		if err != nil {
			return err
		}
	}

	return nil
}

// unpackEntry writes one entry, refusing anything that would land outside the
// server's directory.
//
// This is the zip-slip guard, and it is the reason extraction is worth writing
// carefully: an archive is a list of paths chosen by whoever built it, and
// "../../etc/cron.d/x" is a perfectly legal name inside one.
func (s Store) unpackEntry(server runtime.Server, into, name string, isDir bool, mode os.FileMode, body io.Reader) error {
	relative := filepath.Join(strings.TrimPrefix(into, s.Dir(server)), filepath.FromSlash(name))

	full, err := s.Resolve(server, relative)
	if err != nil {
		return fmt.Errorf("%s would unpack outside the server directory", name)
	}

	if isDir {
		if err := os.MkdirAll(full, 0o755); err != nil {
			return err
		}

		return s.Own(full)
	}

	if err := os.MkdirAll(filepath.Dir(full), 0o755); err != nil {
		return err
	}

	// Mode is masked: an archive is not allowed to hand out setuid on a machine
	// that runs somebody else's game code.
	out, err := os.OpenFile(full, os.O_CREATE|os.O_TRUNC|os.O_WRONLY, mode.Perm()&0o755)
	if err != nil {
		return err
	}
	if _, err := io.Copy(out, body); err != nil {
		_ = out.Close()

		return err
	}
	if err := out.Close(); err != nil {
		return err
	}

	return s.Own(full)
}
