package config

import (
	"os"
	"testing"
)

// What this protects: the enroll token env var was NODE_ENROL_TOKEN before the
// rename to US English. A node.env written by an older installer still spells it
// that way, and a daemon that cannot see the token cannot enroll, which means a
// node the panel can never control.
func TestLoadEnrollTokenFallsBackToTheOldName(t *testing.T) {
	cases := []struct {
		name string
		env  map[string]string
		want string
	}{
		{"new name", map[string]string{"NODE_ENROLL_TOKEN": "new"}, "new"},
		{"legacy name only", map[string]string{"NODE_ENROL_TOKEN": "old"}, "old"},
		{"new name wins", map[string]string{"NODE_ENROLL_TOKEN": "new", "NODE_ENROL_TOKEN": "old"}, "new"},
		{"neither", nil, ""},
	}

	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			t.Setenv("NODE_ENROLL_TOKEN", "")
			t.Setenv("NODE_ENROL_TOKEN", "")
			for k, v := range tc.env {
				t.Setenv(k, v)
			}

			if got := Load().EnrollToken; got != tc.want {
				t.Fatalf("EnrollToken = %q, want %q", got, tc.want)
			}
		})
	}
}

// An operator turns file access off by setting the variable to nothing. env()
// treats empty as unset and hands back the default, so this one deliberately
// does not use it: the difference is a node that was meant to have no SFTP
// listener quietly opening one on 2022.
func TestAnEmptySFTPListenMeansOffNotDefault(t *testing.T) {
	t.Setenv("NODE_SFTP_LISTEN", "")
	if got := Load().SFTPListen; got != "" {
		t.Fatalf("SFTPListen = %q, want empty: an explicitly empty value means off", got)
	}

	t.Setenv("NODE_SFTP_LISTEN", ":2222")
	if got := Load().SFTPListen; got != ":2222" {
		t.Fatalf("SFTPListen = %q, want :2222", got)
	}

	os.Unsetenv("NODE_SFTP_LISTEN")
	if got := Load().SFTPListen; got != ":2022" {
		t.Fatalf("SFTPListen = %q, want the :2022 default when unset", got)
	}
}
