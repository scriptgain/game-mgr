package docker

import (
	"strings"
	"testing"

	"github.com/scriptgain/gamemgr-node/internal/runtime"
)

// The environment a script sees.
//
// Community install scripts read SERVER_MEMORY and SERVER_PORT to write a
// config file during installation, and the template's own variables to decide
// what to download. A missing one is a script that silently installs the wrong
// thing rather than one that fails.
func TestInstallEnvCarriesWhatScriptsRead(t *testing.T) {
	env := installEnv(runtime.Server{
		UUID:      "55f68d3f-8606-4fc2-9a08-bca889ae7dc1",
		MemoryMiB: 2048,
		Port:      7777,
		Environment: map[string]string{
			"VERSION":   "0.3.7",
			"RCON_PASS": "secret",
		},
	})

	joined := strings.Join(env, "\n")
	for _, want := range []string{
		"VERSION=0.3.7",
		"RCON_PASS=secret",
		"SERVER_MEMORY=2048",
		"SERVER_PORT=7777",
		"SERVER_IP=0.0.0.0",
		"P_SERVER_UUID=55f68d3f-8606-4fc2-9a08-bca889ae7dc1",
	} {
		if !strings.Contains(joined, want) {
			t.Errorf("the install environment is missing %q:\n%s", want, joined)
		}
	}
}

// Sorted, so two runs of the same install produce the same container config
// and Docker does not see a change where there is none.
func TestInstallEnvIsStable(t *testing.T) {
	s := runtime.Server{Environment: map[string]string{"B": "2", "A": "1", "C": "3"}}

	first := strings.Join(installEnv(s), ",")
	for i := 0; i < 8; i++ {
		if got := strings.Join(installEnv(s), ","); got != first {
			t.Fatalf("install environment is not stable:\n%s\n%s", first, got)
		}
	}
}

// The container name has to be derived from the server and fit Docker's rules.
func TestShortIDIsUsableAsAContainerName(t *testing.T) {
	got := shortID("55f68d3f-8606-4fc2-9a08-bca889ae7dc1")

	if got != "55f68d3f8606" {
		t.Fatalf("shortID = %q", got)
	}
	if strings.Contains(got, "-") {
		t.Error("hyphens were meant to be stripped")
	}

	// A UUID shorter than the cut must not panic; the stub driver and tests
	// both hand out short ids.
	if s := shortID("abc"); s != "abc" {
		t.Fatalf("short input mangled: %q", s)
	}
}

// CRLF is the normal case, not the edge case.
//
// 226 of the 249 install scripts in the vendored catalogue end their lines with
// CRLF, because they were written on Windows. bash reads bytes, not intentions:
// with \r on the end, "curl" becomes "curl\r", the shell reports a command not
// found for a command that plainly exists, and the script exits 2 having done
// nothing. SA-MP failed exactly this way and the output gave no hint why.
func TestScriptsWithWindowsLineEndingsAreRunnable(t *testing.T) {
	got := normaliseScript("#!/bin/bash\r\ncd /tmp || exit\r\ncurl -sSL -o s.tar.gz http://example/\r\n")

	if strings.Contains(got, "\r") {
		t.Fatalf("a carriage return survived:\n%q", got)
	}
	if !strings.Contains(got, "cd /tmp || exit\ncurl") {
		t.Fatalf("the script was mangled:\n%q", got)
	}
}

// A lone CR is rarer and worse: it leaves the entire script on one line rather
// than failing a line at a time.
func TestLoneCarriageReturnsBecomeNewlines(t *testing.T) {
	got := normaliseScript("echo one\recho two\recho three")

	if strings.Count(got, "\n") != 2 {
		t.Fatalf("expected three lines, got %q", got)
	}
}

// A byte order mark breaks the first line only, which is the shebang, which is
// the hardest one to notice.
func TestByteOrderMarkIsStripped(t *testing.T) {
	got := normaliseScript("\ufeff#!/bin/bash\necho hi\n")

	if !strings.HasPrefix(got, "#!/bin/bash") {
		t.Fatalf("BOM survived: %q", got[:20])
	}
}

// An empty script must stay empty so the caller skips the container entirely.
func TestAnEmptyScriptStaysEmpty(t *testing.T) {
	for _, in := range []string{"", "   ", "\r\n\r\n", "\ufeff"} {
		if got := normaliseScript(in); got != "" {
			t.Errorf("normaliseScript(%q) = %q, want empty", in, got)
		}
	}
}
