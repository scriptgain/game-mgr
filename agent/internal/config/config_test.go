package config

import "testing"

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
