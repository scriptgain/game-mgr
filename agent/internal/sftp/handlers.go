package sftp

import (
	"io"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"time"

	"github.com/pkg/sftp"

	"github.com/scriptgain/gamemgr-node/internal/panel"
	gruntime "github.com/scriptgain/gamemgr-node/internal/runtime"
	"github.com/scriptgain/gamemgr-node/internal/runtime/store"
)

// Permission strings, the same ones the panel uses for the web file manager.
// Named here so the mapping from an SFTP request to a permission is one table
// rather than a string literal at each call site.
const (
	permRead   = "file.read"
	permCreate = "file.create"
	permUpdate = "file.update"
	permDelete = "file.delete"
)

// handlers serves one connection. It holds the server it is jailed to and the
// permissions the panel granted, and neither changes for the life of the
// connection.
type handlers struct {
	store  store.Store
	server gruntime.Server
	grant  *panel.SFTPGrant
	quota  *quota
}

func (s *Server) handlers(server gruntime.Server, grant *panel.SFTPGrant) sftp.Handlers {
	// Measured once, here, rather than per write: a walk of a large world is
	// cheap but not free, and putting it in the path of every WriteAt would make
	// a big upload quadratic in the size of the directory.
	used := s.store.DiskUsageMiB(server)
	h := &handlers{
		store:  s.store,
		server: server,
		grant:  grant,
		quota:  newQuota(grant.DiskMiB, used),
	}

	return sftp.Handlers{FileGet: h, FilePut: h, FileCmd: h, FileList: h}
}

// resolve turns a path from the client into a real one inside this server's
// directory, or refuses.
//
// The whole jail is this function. store.Resolve is the same guard the panel's
// file manager goes through, so there is one implementation of "inside the
// server directory" rather than a second one written for SFTP that would drift
// from the first.
func (h *handlers) resolve(path string) (string, error) {
	full, err := h.store.Resolve(h.server, path)
	if err != nil {
		return "", os.ErrPermission
	}

	return full, nil
}

// resolveWrite is resolve for a path about to be written to. It refuses a
// traversal outright rather than quietly containing it, so an upload never
// lands somewhere other than where the client asked.
func (h *handlers) resolveWrite(path string) (string, error) {
	full, err := h.store.ResolveWrite(h.server, path)
	if err != nil {
		return "", os.ErrPermission
	}

	return full, nil
}

func (h *handlers) can(permission string) bool { return h.grant.Can(permission) }

// ------------------------------------------------------------------ reading

// Fileread answers a download.
func (h *handlers) Fileread(r *sftp.Request) (io.ReaderAt, error) {
	if !h.can(permRead) {
		return nil, os.ErrPermission
	}
	full, err := h.resolve(r.Filepath)
	if err != nil {
		return nil, err
	}

	return os.Open(full)
}

// Filelist answers directory listings and stat calls.
func (h *handlers) Filelist(r *sftp.Request) (sftp.ListerAt, error) {
	if !h.can(permRead) {
		return nil, os.ErrPermission
	}
	full, err := h.resolve(r.Filepath)
	if err != nil {
		return nil, err
	}

	switch r.Method {
	case "List":
		entries, err := os.ReadDir(full)
		if err != nil {
			return nil, err
		}
		out := make([]os.FileInfo, 0, len(entries))
		for _, entry := range entries {
			info, err := entry.Info()
			if err != nil {
				continue
			}
			out = append(out, info)
		}
		sort.Slice(out, func(i, j int) bool { return out[i].Name() < out[j].Name() })

		return lister(out), nil

	case "Stat":
		info, err := os.Stat(full)
		if err != nil {
			return nil, err
		}

		return lister{info}, nil

	case "Readlink":
		// Deliberately unsupported. Answering it would let a client discover
		// where a link points outside the jail, and every path is resolved
		// through the jail anyway, so a link out of it is not followable.
		return nil, os.ErrPermission
	}

	return nil, sftp.ErrSSHFxOpUnsupported
}

// ------------------------------------------------------------------ writing

// Filewrite answers an upload, and is where ownership has to be right.
func (h *handlers) Filewrite(r *sftp.Request) (io.WriterAt, error) {
	full, err := h.resolveWrite(r.Filepath)
	if err != nil {
		return nil, err
	}

	// Creating a file and changing one are different permissions, so which of
	// them this is depends on whether it already exists.
	_, statErr := os.Stat(full)
	switch {
	case os.IsNotExist(statErr):
		if !h.can(permCreate) {
			return nil, os.ErrPermission
		}
	case statErr == nil:
		if !h.can(permUpdate) {
			return nil, os.ErrPermission
		}
	default:
		return nil, statErr
	}

	if err := os.MkdirAll(filepath.Dir(full), 0o755); err != nil {
		return nil, err
	}
	h.ownUpTo(filepath.Dir(full))

	flags := os.O_WRONLY | os.O_CREATE
	if r.Pflags().Trunc {
		flags |= os.O_TRUNC
	}
	file, err := os.OpenFile(full, flags, 0o644)
	if err != nil {
		return nil, err
	}

	// Two reasons for this wrapper.
	//
	// pkg/sftp hands back a raw file handle, so without it every uploaded file
	// would belong to whoever the daemon runs as, which is root: the bug the
	// whole ownership pass was about, and an upload is one more way to make a
	// file.
	//
	// And every byte is charged against the server's disk limit as it arrives,
	// because a limit nobody measures is a number on a page.
	return &ownedFile{File: file, store: h.store, quota: h.quota}, nil
}

// ownedFile charges each write against the quota and hands the finished file to
// the game account.
type ownedFile struct {
	*os.File
	store store.Store
	quota *quota
}

func (f *ownedFile) WriteAt(p []byte, off int64) (int, error) {
	// Reserved before the bytes land, not counted after: counting afterwards
	// means the disk is already full by the time anybody objects.
	if err := f.quota.reserve(int64(len(p))); err != nil {
		return 0, err
	}

	n, err := f.File.WriteAt(p, off)
	if n < len(p) {
		// Give back what never made it, so a broken transfer does not
		// permanently consume an allowance it did not use.
		f.quota.release(int64(len(p) - n))
	}

	return n, err
}

func (f *ownedFile) Close() error {
	name := f.File.Name()
	if err := f.File.Close(); err != nil {
		return err
	}

	return f.store.Own(name)
}

// ----------------------------------------------------------------- commands

// Filecmd answers everything that changes the shape of the tree.
func (h *handlers) Filecmd(r *sftp.Request) error {
	switch r.Method {
	case "Mkdir":
		if !h.can(permCreate) {
			return os.ErrPermission
		}
		full, err := h.resolveWrite(r.Filepath)
		if err != nil {
			return err
		}
		if err := os.MkdirAll(full, 0o755); err != nil {
			return err
		}
		h.ownUpTo(full)

		return nil

	case "Rename":
		if !h.can(permUpdate) {
			return os.ErrPermission
		}
		from, err := h.resolve(r.Filepath)
		if err != nil {
			return err
		}
		to, err := h.resolveWrite(r.Target)
		if err != nil {
			return err
		}
		if err := os.MkdirAll(filepath.Dir(to), 0o755); err != nil {
			return err
		}
		h.ownUpTo(filepath.Dir(to))

		return os.Rename(from, to)

	case "Remove", "Rmdir":
		if !h.can(permDelete) {
			return os.ErrPermission
		}
		full, err := h.resolve(r.Filepath)
		if err != nil {
			return err
		}
		// The server's own directory is not a file in it. Removing it would
		// take the whole server with it and leave the panel pointing at nothing.
		if full == h.store.Dir(h.server) {
			return os.ErrPermission
		}

		return os.Remove(full)

	case "Setstat":
		// Permissions and timestamps only, and only inside the jail. Ownership
		// is not negotiable from out here: the game account owns everything, and
		// a client being able to chown its own files would undo the guarantee
		// the rest of this daemon works to keep.
		if !h.can(permUpdate) {
			return os.ErrPermission
		}

		return h.setstat(r)

	case "Symlink", "Link":
		// A symlink is the one way a client could name a path this package
		// cannot check: the link is created inside the jail and points wherever
		// it likes, and every later open follows it out. Refused outright.
		return sftp.ErrSSHFxOpUnsupported
	}

	return sftp.ErrSSHFxOpUnsupported
}

func (h *handlers) setstat(r *sftp.Request) error {
	full, err := h.resolve(r.Filepath)
	if err != nil {
		return err
	}
	attrs := r.Attributes()

	if r.AttrFlags().Permissions {
		// Masked to the permission bits. Setuid and setgid arriving over the
		// wire on a node that runs game code is not something to pass through.
		if err := os.Chmod(full, attrs.FileMode().Perm()); err != nil {
			return err
		}
	}
	if r.AttrFlags().Acmodtime {
		if err := os.Chtimes(full,
			time.Unix(int64(attrs.Atime), 0), time.Unix(int64(attrs.Mtime), 0)); err != nil {
			return err
		}
	}
	if r.AttrFlags().Size {
		if err := os.Truncate(full, int64(attrs.Size)); err != nil {
			return err
		}
	}
	// UID/GID are accepted and ignored rather than refused: clients send them
	// routinely as part of a normal put, and failing the whole transfer over a
	// field we were never going to honour is worse than quietly not honouring
	// it.

	return nil
}

// ownUpTo hands a directory and its parents, up to the server's own directory,
// to the game account. Same reasoning as the store: a directory created for an
// upload that the game cannot write into is a directory the next upload fails in.
func (h *handlers) ownUpTo(dir string) {
	base := h.store.Dir(h.server)
	for current := dir; strings.HasPrefix(current, base); current = filepath.Dir(current) {
		_ = h.store.Own(current)
		if current == base {
			return
		}
	}
}

// ------------------------------------------------------------------- lister

// lister is the slice of results pkg/sftp pages through.
type lister []os.FileInfo

func (l lister) ListAt(out []os.FileInfo, offset int64) (int, error) {
	if offset >= int64(len(l)) {
		return 0, io.EOF
	}
	n := copy(out, l[offset:])
	if n < len(out) {
		return n, io.EOF
	}

	return n, nil
}
