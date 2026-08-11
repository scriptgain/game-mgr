package steamcmd

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/scriptgain/gamemgr-node/internal/runtime"
	"github.com/scriptgain/gamemgr-node/internal/supervise"
)

// Credentials used to be built as "+login <user> <password>" command arguments,
// which every other user on the node can read out of /proc/<pid>/cmdline for as
// long as the download runs. They now go into a 0600 runscript instead. This
// pins that down so it cannot quietly regress the next time the install command
// is touched.
func TestCredentialsNeverReachTheCommandLine(t *testing.T) {
	const password = "hunter2-should-never-be-visible"
	const betaPassword = "beta-secret-should-never-be-visible"

	dir := t.TempDir()
	driver := &Driver{}
	server := runtime.Server{
		UUID:        "11111111-2222-3333-4444-555555555555",
		SteamAppID:  232250,
		SteamBranch: "publicbeta",
		Environment: map[string]string{
			"STEAM_USER":          "someuser",
			"STEAM_PASS":          password,
			"STEAM_BETA_PASSWORD": betaPassword,
		},
	}

	script, err := driver.writeRunscript(server, dir)
	if err != nil {
		t.Fatalf("writeRunscript: %v", err)
	}

	// The script path is the only thing that lands on the command line, so it
	// must carry nothing sensitive.
	for _, secret := range []string{password, betaPassword} {
		if strings.Contains(script, secret) {
			t.Fatalf("the runscript path leaks a secret: %s", script)
		}
	}

	// It lives in the daemon's runtime directory rather than the customer's
	// server directory, so the file manager never lists it.
	if want := supervise.RuntimeDir(dir); filepath.Dir(script) != want {
		t.Fatalf("runscript is at %s, want it under %s", script, want)
	}

	info, err := os.Stat(script)
	if err != nil {
		t.Fatalf("stat: %v", err)
	}
	if perm := info.Mode().Perm(); perm != 0o600 {
		t.Fatalf("runscript mode is %04o, want 0600", perm)
	}

	// The credentials really are in the file, so this is a relocation rather
	// than a silent drop that would leave every private install failing to log
	// in with no obvious cause.
	body, err := os.ReadFile(script)
	if err != nil {
		t.Fatalf("read: %v", err)
	}
	for _, secret := range []string{password, betaPassword} {
		if !strings.Contains(string(body), secret) {
			t.Fatalf("the runscript is missing %q, so the login would fail", secret)
		}
	}
}

// A Steam Guard code has to be primed before the login that consumes it.
// Emitted after, it answers nothing: @NoPromptForPassword has already turned
// the challenge into a failure rather than an interactive prompt, and the
// install dies with "Two-factor code mismatch" while the code sits in the file
// looking perfectly correct.
func TestSteamGuardCodeIsSetBeforeLogin(t *testing.T) {
	const code = "K9J4M"

	dir := t.TempDir()
	driver := &Driver{}

	script, err := driver.writeRunscript(runtime.Server{
		UUID:       "11111111-2222-3333-4444-555555555555",
		SteamAppID: 232250,
		Environment: map[string]string{
			"STEAM_USER":       "someuser",
			"STEAM_PASS":       "hunter2",
			"STEAM_GUARD_CODE": code,
		},
	}, dir)
	if err != nil {
		t.Fatalf("writeRunscript: %v", err)
	}

	body, err := os.ReadFile(script)
	if err != nil {
		t.Fatalf("read: %v", err)
	}

	guard := strings.Index(string(body), "set_steam_guard_code "+code)
	if guard < 0 {
		t.Fatalf("the guard code is missing, so a protected account cannot log in:\n%s", body)
	}
	login := strings.Index(string(body), "login someuser")
	if login < 0 {
		t.Fatalf("the login line is missing:\n%s", body)
	}
	if guard > login {
		t.Fatalf("set_steam_guard_code comes after login, which answers nothing:\n%s", body)
	}

	// The code is as sensitive as the password for the thirty seconds it lives,
	// and travels the same way: in the file, never on argv.
	if strings.Contains(script, code) {
		t.Fatalf("the runscript path leaks the guard code: %s", script)
	}
}

// An account with no Steam Guard must not get an empty command. "set_steam_guard_code"
// with nothing after it is not a no-op: steamcmd takes the next line as its
// argument, eats the login, and the install fails somewhere unrelated.
func TestNoGuardCodeEmitsNoGuardLine(t *testing.T) {
	dir := t.TempDir()
	driver := &Driver{}

	for _, env := range []map[string]string{
		{"STEAM_USER": "someuser", "STEAM_PASS": "hunter2"},
		{"STEAM_USER": "someuser", "STEAM_PASS": "hunter2", "STEAM_GUARD_CODE": ""},
		{"STEAM_USER": "someuser", "STEAM_PASS": "hunter2", "STEAM_GUARD_CODE": "   "},
	} {
		script, err := driver.writeRunscript(runtime.Server{
			UUID:        "11111111-2222-3333-4444-555555555555",
			SteamAppID:  232250,
			Environment: env,
		}, dir)
		if err != nil {
			t.Fatalf("writeRunscript: %v", err)
		}

		body, err := os.ReadFile(script)
		if err != nil {
			t.Fatalf("read: %v", err)
		}
		if strings.Contains(string(body), "set_steam_guard_code") {
			t.Fatalf("emitted a guard line for %v:\n%s", env, body)
		}
	}
}

// An anonymous install must not invent a login line.
func TestAnonymousInstallUsesAnonymousLogin(t *testing.T) {
	dir := t.TempDir()
	driver := &Driver{}

	script, err := driver.writeRunscript(runtime.Server{
		UUID:           "11111111-2222-3333-4444-555555555555",
		SteamAppID:     232250,
		SteamAnonymous: true,
	}, dir)
	if err != nil {
		t.Fatalf("writeRunscript: %v", err)
	}

	body, err := os.ReadFile(script)
	if err != nil {
		t.Fatalf("read: %v", err)
	}
	if !strings.Contains(string(body), "login anonymous") {
		t.Fatalf("expected an anonymous login, got:\n%s", body)
	}
}
