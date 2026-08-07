package supervise

import (
	"os/exec"
	"strings"
	"testing"
)

func TestExportLines(t *testing.T) {
	cases := []struct {
		name string
		env  map[string]string
		want string
	}{
		{
			name: "sorted so a restart produces an identical launcher",
			env:  map[string]string{"B": "2", "A": "1"},
			want: "export A='1'\nexport B='2'\n",
		},
		{
			// A server name is free text an operator typed into a form. Without
			// quoting this runs as a command the moment the server starts, as
			// whatever user the game runs as.
			name: "a value containing a subshell is inert",
			env:  map[string]string{"SERVER_NAME": "$(id -u > /tmp/pwned)"},
			want: "export SERVER_NAME='$(id -u > /tmp/pwned)'\n",
		},
		{
			name: "an embedded single quote is escaped, not truncated",
			env:  map[string]string{"SERVER_NAME": "Allen's Box"},
			want: `export SERVER_NAME='Allen'\''s Box'` + "\n",
		},
		{
			// Dropped rather than sanitised: a key that is not an identifier is
			// a mistake worth seeing, not something to quietly rewrite.
			name: "keys that are not shell identifiers are dropped",
			env: map[string]string{
				"GOOD":          "yes",
				"BAD-KEY":       "no",
				"9LEADINGDIGIT": "no",
				"HAS SPACE":     "no",
				"":              "no",
				"X;rm -rf /":    "no",
			},
			want: "export GOOD='yes'\n",
		},
		{
			name: "an empty environment produces nothing",
			env:  map[string]string{},
			want: "",
		},
	}

	for _, c := range cases {
		t.Run(c.name, func(t *testing.T) {
			if got := exportLines(c.env); got != c.want {
				t.Errorf("\n got %q\nwant %q", got, c.want)
			}
		})
	}
}

// The launcher is executed by /bin/sh, so the proof that matters is what a real
// shell does with it rather than what the string looks like.
func TestExportedValuesSurviveARealShell(t *testing.T) {
	if _, err := exec.LookPath("sh"); err != nil {
		t.Skip("no sh on this box")
	}

	env := map[string]string{
		"SERVER_NAME":    "Allen's \"Box\" (Test) $HOME `id`",
		"ADMIN_PASSWORD": "p@ss;word|with&meta",
		"SERVER_PORT":    "8211",
	}

	script := exportLines(env) + "printf '%s\\n' \"$SERVER_NAME\" \"$ADMIN_PASSWORD\" \"$SERVER_PORT\"\n"

	out, err := exec.Command("sh", "-c", script).CombinedOutput()
	if err != nil {
		t.Fatalf("script failed: %v\n%s", err, out)
	}

	got := strings.Split(strings.TrimRight(string(out), "\n"), "\n")
	want := []string{env["SERVER_NAME"], env["ADMIN_PASSWORD"], env["SERVER_PORT"]}

	if len(got) != len(want) {
		t.Fatalf("got %d lines %q, want %d", len(got), got, len(want))
	}
	for i := range want {
		if got[i] != want[i] {
			t.Errorf("line %d\n got %q\nwant %q", i, got[i], want[i])
		}
	}
}
