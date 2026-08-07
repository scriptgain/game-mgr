package panel

import (
	"os"
	"path/filepath"
	"strings"
)

// SaveToken rewrites the daemon's env file with the long-lived token and drops
// the enrol token.
//
// This is what makes enrolment survive a restart. Enrol tokens are single use,
// so a node that came back with NODE_ENROL_TOKEN still set and no NODE_TOKEN
// would try to spend a token the panel has already burned, be refused, and sit
// there unenrolled with its servers running and nothing able to reach them.
//
// The new contents go to a temporary file in the same directory and are renamed
// over the original, so a node that loses power mid-write comes back with
// either the old file or the new one, never a truncated one holding half a
// token. Same directory because rename is only atomic within a filesystem.
func SaveToken(path, token string) error {
	dir := filepath.Dir(path)
	if err := os.MkdirAll(dir, 0o700); err != nil {
		return err
	}

	body, err := rewrite(path, token)
	if err != nil {
		return err
	}

	tmp, err := os.CreateTemp(dir, ".node.env-")
	if err != nil {
		return err
	}
	defer os.Remove(tmp.Name())

	// Before the write, not after: the token must never exist on disk in a file
	// the rest of the box can read, not even for the moment in between.
	if err := tmp.Chmod(0o600); err != nil {
		tmp.Close()

		return err
	}
	if _, err := tmp.WriteString(body); err != nil {
		tmp.Close()

		return err
	}
	if err := tmp.Sync(); err != nil {
		tmp.Close()

		return err
	}
	if err := tmp.Close(); err != nil {
		return err
	}

	return os.Rename(tmp.Name(), path)
}

// rewrite returns the file's new contents: NODE_TOKEN set, NODE_ENROL_TOKEN
// gone, and every other line left exactly as the operator wrote it, comments
// included. A missing file is not an error; the enrol one-liner may never have
// written one.
func rewrite(path, token string) (string, error) {
	raw, err := os.ReadFile(path)
	if err != nil && !os.IsNotExist(err) {
		return "", err
	}

	var out []string
	replaced := false

	for _, line := range strings.Split(string(raw), "\n") {
		switch key(line) {
		case "NODE_TOKEN":
			if replaced {
				continue
			}
			out = append(out, "NODE_TOKEN="+token)
			replaced = true
		case "NODE_ENROL_TOKEN":
			continue
		default:
			out = append(out, line)
		}
	}
	if !replaced {
		out = append(out, "NODE_TOKEN="+token)
	}

	body := strings.TrimRight(strings.Join(out, "\n"), "\n")

	return body + "\n", nil
}

// key is the variable a line assigns, or "" for blanks, comments and anything
// else. "export FOO=bar" counts, since a hand-edited file often has it.
func key(line string) string {
	trimmed := strings.TrimSpace(line)
	trimmed = strings.TrimPrefix(trimmed, "export ")
	name, _, ok := strings.Cut(trimmed, "=")
	if !ok {
		return ""
	}

	return strings.TrimSpace(name)
}
