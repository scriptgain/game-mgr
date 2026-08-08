package panel

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

// What this protects: an enroll token is single use. If the long-lived token
// does not reach the env file, the next restart replays a spent enroll token,
// the panel refuses it, and the node comes back with its servers running and
// nothing able to reach them.
func TestSaveToken(t *testing.T) {
	const token = "long-lived-secret"

	cases := []struct {
		name    string
		before  string
		create  bool
		want    []string
		notWant []string
	}{
		{
			name:   "the enroll token is replaced by the real one",
			create: true,
			before: "NODE_PANEL_URL=https://panel.example\nNODE_ENROLL_TOKEN=single-use\nNODE_ROOT=/var/lib/gamemgr/volumes\n",
			want: []string{
				"NODE_TOKEN=" + token,
				"NODE_PANEL_URL=https://panel.example",
				"NODE_ROOT=/var/lib/gamemgr/volumes",
			},
			notWant: []string{"NODE_ENROLL_TOKEN", "single-use"},
		},
		{
			name:    "an existing token is overwritten in place, not duplicated",
			create:  true,
			before:  "NODE_TOKEN=stale-token\nNODE_LISTEN=:8942\n",
			want:    []string{"NODE_TOKEN=" + token, "NODE_LISTEN=:8942"},
			notWant: []string{"stale-token"},
		},
		{
			// Hand-edited files pick up export and stray whitespace, and a
			// surviving export NODE_ENROLL_TOKEN would still be in the daemon's
			// environment on the next boot.
			name:    "export and indentation are still recognised",
			create:  true,
			before:  "  export NODE_ENROLL_TOKEN=single-use\n# NODE_TOKEN is written by enrollment\n",
			want:    []string{"NODE_TOKEN=" + token, "# NODE_TOKEN is written by enrollment"},
			notWant: []string{"NODE_ENROLL_TOKEN", "single-use"},
		},
		{
			// A node installed before the enrol/enroll rename has the old key
			// in its env file. Leaving it there would hand the next restart a
			// token the panel has already spent.
			name:    "the pre-rename NODE_ENROL_TOKEN is dropped too",
			create:  true,
			before:  "NODE_PANEL_URL=https://panel.example\nNODE_ENROL_TOKEN=single-use\n",
			want:    []string{"NODE_TOKEN=" + token, "NODE_PANEL_URL=https://panel.example"},
			notWant: []string{"NODE_ENROL_TOKEN", "single-use"},
		},
		{
			name:   "a file that does not exist yet is created",
			create: false,
			want:   []string{"NODE_TOKEN=" + token},
		},
	}

	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			path := filepath.Join(t.TempDir(), "sub", "node.env")
			if tc.create {
				if err := os.MkdirAll(filepath.Dir(path), 0o700); err != nil {
					t.Fatal(err)
				}
				if err := os.WriteFile(path, []byte(tc.before), 0o600); err != nil {
					t.Fatal(err)
				}
			}

			if err := SaveToken(path, token); err != nil {
				t.Fatalf("SaveToken: %v", err)
			}

			raw, err := os.ReadFile(path)
			if err != nil {
				t.Fatalf("read back: %v", err)
			}
			body := string(raw)

			for _, want := range tc.want {
				if !strings.Contains(body, want) {
					t.Errorf("file is missing %q:\n%s", want, body)
				}
			}
			for _, notWant := range tc.notWant {
				if strings.Contains(body, notWant) {
					t.Errorf("file still contains %q:\n%s", notWant, body)
				}
			}
			if n := strings.Count(body, "NODE_TOKEN="); n != 1 {
				t.Errorf("NODE_TOKEN appears %d times, want once:\n%s", n, body)
			}

			info, err := os.Stat(path)
			if err != nil {
				t.Fatal(err)
			}
			// The token is a live credential for this node. Anything readable
			// by the rest of the box hands it to every user on it.
			if perm := info.Mode().Perm(); perm != 0o600 {
				t.Errorf("mode is %04o, want 0600", perm)
			}

			// The temporary file must not survive: it holds the same token and
			// would be left behind in a directory operators do not look in.
			entries, err := os.ReadDir(filepath.Dir(path))
			if err != nil {
				t.Fatal(err)
			}
			for _, e := range entries {
				if strings.HasPrefix(e.Name(), ".node.env-") {
					t.Errorf("left a temporary file behind: %s", e.Name())
				}
			}
		})
	}
}

// A node that cannot write its token still has to run: the caller keeps the
// token in memory and logs loudly. What must not happen is a silent success.
func TestSaveTokenReportsAnUnwritableFile(t *testing.T) {
	if os.Geteuid() == 0 {
		t.Skip("root ignores the directory permissions this test relies on")
	}

	dir := t.TempDir()
	path := filepath.Join(dir, "node.env")
	if err := os.WriteFile(path, []byte("NODE_ENROLL_TOKEN=single-use\n"), 0o600); err != nil {
		t.Fatal(err)
	}
	// Read and execute only: the file can be read but nothing can be created
	// or renamed alongside it, which is what a rename-based write needs.
	if err := os.Chmod(dir, 0o500); err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { _ = os.Chmod(dir, 0o700) })

	if err := SaveToken(path, "long-lived-secret"); err == nil {
		t.Fatal("SaveToken succeeded on an unwritable directory, want an error")
	}

	// And the original is untouched rather than half rewritten.
	raw, err := os.ReadFile(path)
	if err != nil {
		t.Fatal(err)
	}
	if string(raw) != "NODE_ENROLL_TOKEN=single-use\n" {
		t.Fatalf("the original file was modified: %q", raw)
	}
}
