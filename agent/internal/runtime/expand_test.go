package runtime

import (
	"reflect"
	"testing"
)

func TestExpand(t *testing.T) {
	env := map[string]string{
		"SERVER_PORT": "28015",
		"MAX_PLAYERS": "50",
		"SERVER_NAME": "Allen's Box",
		"EMPTY":       "",
	}

	cases := []struct {
		name    string
		in      string
		want    string
		missing []string
	}{
		{
			// The exact shape shipped in the Rust and CS2 templates, which was
			// reaching the game as literal text.
			name: "a pterodactyl style startup resolves",
			in:   "./RustDedicated -batchmode +server.port {{SERVER_PORT}} +server.maxplayers {{MAX_PLAYERS}}",
			want: "./RustDedicated -batchmode +server.port 28015 +server.maxplayers 50",
		},
		{
			name: "whitespace inside the braces is tolerated",
			in:   "start --port {{ SERVER_PORT }}",
			want: "start --port 28015",
		},
		{
			// Leaving it visible is the whole point: a blanked placeholder
			// produces a server quietly listening on the wrong port.
			name:    "an unknown placeholder is left alone and reported",
			in:      "start --port {{SERVER_PORT}} --world {{WORLD_NAME}}",
			want:    "start --port 28015 --world {{WORLD_NAME}}",
			missing: []string{"WORLD_NAME"},
		},
		{
			name:    "every unresolved name is reported once, sorted",
			in:      "{{B_VAR}} {{A_VAR}} {{B_VAR}}",
			want:    "{{B_VAR}} {{A_VAR}} {{B_VAR}}",
			missing: []string{"A_VAR", "B_VAR"},
		},
		{
			// A set-but-empty value is a deliberate choice by the operator and
			// is not the same thing as an absent one.
			name: "a set but empty value resolves to empty",
			in:   "start {{EMPTY}}done",
			want: "start done",
		},
		{
			name: "shell syntax is left untouched",
			in:   "start --name \"$SERVER_NAME\" --port ${SERVER_PORT:-8211}",
			want: "start --name \"$SERVER_NAME\" --port ${SERVER_PORT:-8211}",
		},
		{
			name: "a value carrying quotes is substituted verbatim, not escaped",
			in:   "start --name {{SERVER_NAME}}",
			want: "start --name Allen's Box",
		},
		{
			name: "a command with no placeholders is unchanged",
			in:   "./PalServer.sh -port=8211",
			want: "./PalServer.sh -port=8211",
		},
	}

	for _, c := range cases {
		t.Run(c.name, func(t *testing.T) {
			got, missing := Expand(c.in, env)
			if got != c.want {
				t.Errorf("command\n got %q\nwant %q", got, c.want)
			}
			if len(missing) == 0 && len(c.missing) == 0 {
				return
			}
			if !reflect.DeepEqual(missing, c.missing) {
				t.Errorf("missing got %v, want %v", missing, c.missing)
			}
		})
	}
}

func TestExpandHandlesANilEnvironment(t *testing.T) {
	got, missing := Expand("start --port {{SERVER_PORT}}", nil)
	if got != "start --port {{SERVER_PORT}}" {
		t.Errorf("got %q", got)
	}
	if !reflect.DeepEqual(missing, []string{"SERVER_PORT"}) {
		t.Errorf("missing got %v", missing)
	}
}
