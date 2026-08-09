package sftp

import (
	"context"
	"errors"
	"io"
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"github.com/pkg/sftp"
	"golang.org/x/crypto/ssh"

	"github.com/scriptgain/gamemgr-node/internal/panel"
	gruntime "github.com/scriptgain/gamemgr-node/internal/runtime"
	"github.com/scriptgain/gamemgr-node/internal/runtime/store"
)

const serverUUID = "11111111-2222-3333-4444-555555555555"

// fakePanel stands in for the panel. The daemon holds no accounts of its own,
// so this is the whole of the authority a connection gets.
type fakePanel struct {
	password    string
	permissions []string
	calls       int
	err         error
}

func (f *fakePanel) AuthenticateSFTP(_ context.Context, username, password, _ string) (*panel.SFTPGrant, error) {
	f.calls++
	if f.err != nil {
		return nil, f.err
	}
	if password != f.password {
		return nil, panel.ErrDenied
	}

	return &panel.SFTPGrant{
		Granted:     true,
		ServerUUID:  serverUUID,
		Runtime:     "docker",
		Permissions: f.permissions,
		Username:    username,
	}, nil
}

// dial starts a server on a free port and returns a connected client.
func dial(t *testing.T, fake *fakePanel, password string) (*sftp.Client, string) {
	t.Helper()

	root := t.TempDir()
	st := store.New(root, nil)
	dir, err := st.EnsureDir(gruntime.Server{UUID: serverUUID})
	if err != nil {
		t.Fatal(err)
	}

	server, err := New("127.0.0.1:0", filepath.Join(t.TempDir(), "hostkey"), st, fake)
	if err != nil {
		t.Fatal(err)
	}
	go func() { _ = server.Serve() }()
	// Serve binds inside the goroutine, so wait for the address to exist.
	deadline := time.Now().Add(5 * time.Second)
	for server.Addr() == nil && time.Now().Before(deadline) {
		time.Sleep(5 * time.Millisecond)
	}
	if server.Addr() == nil {
		t.Fatal("the server never bound a port")
	}
	t.Cleanup(func() { _ = server.Close() })

	conn, err := ssh.Dial("tcp", server.Addr().String(), &ssh.ClientConfig{
		User:            "allen." + store.Short(serverUUID),
		Auth:            []ssh.AuthMethod{ssh.Password(password)},
		HostKeyCallback: ssh.InsecureIgnoreHostKey(),
		Timeout:         5 * time.Second,
	})
	if err != nil {
		t.Fatalf("ssh dial: %v", err)
	}
	t.Cleanup(func() { _ = conn.Close() })

	client, err := sftp.NewClient(conn)
	if err != nil {
		t.Fatalf("sftp session: %v", err)
	}
	t.Cleanup(func() { _ = client.Close() })

	return client, dir
}

func allPermissions() []string {
	return []string{permRead, permCreate, permUpdate, permDelete}
}

// The jail is the entire security model here: one unix account runs every
// server on a node, so file permissions cannot keep one customer out of
// another's directory. Only path resolution can.
//
// What is asserted is the property, not the error. A traversal is contained
// rather than refused: pkg/sftp collapses every path against "/" before a
// handler sees it, so "../secret" arrives as "/secret" and then resolves inside
// the server's own directory. store.Resolve is the second line, and containment
// is the same answer the panel's file manager gives. So the test says what
// actually matters, which is that nothing outside is ever read or written,
// rather than pinning which of the two layers said no.
func TestAClientCannotReachOutsideItsServerDirectory(t *testing.T) {
	client, dir := dial(t, &fakePanel{password: "hunter2", permissions: allPermissions()}, "hunter2")

	// Something worth stealing, one level up from the jail: on a real node this
	// is another customer's server directory.
	outside := filepath.Dir(dir)
	if err := os.WriteFile(filepath.Join(outside, "other-customer.txt"), []byte("secret"), 0o644); err != nil {
		t.Fatal(err)
	}

	for _, attempt := range []string{
		"../other-customer.txt",
		"../../etc/passwd",
		"/../../etc/passwd",
		"world/../../other-customer.txt",
		"....//other-customer.txt",
	} {
		file, err := client.Open(attempt)
		if err != nil {
			continue // refused outright, which is also fine
		}
		body, _ := io.ReadAll(file)
		_ = file.Close()
		if strings.Contains(string(body), "secret") {
			t.Errorf("reading %q returned a file from outside the server directory", attempt)
		}
	}

	// Writes are the more dangerous direction. Every one of these must land
	// inside the jail or not at all.
	for _, attempt := range []string{"../escaped.txt", "../../tmp/escaped.txt", "/../escaped.txt"} {
		if file, err := client.Create(attempt); err == nil {
			_, _ = io.WriteString(file, "escaped")
			_ = file.Close()
		}
	}
	if _, err := os.Stat(filepath.Join(outside, "escaped.txt")); err == nil {
		t.Fatal("a file was written outside the server directory")
	}
	if _, err := os.Stat(filepath.Join(outside, "other-customer.txt")); err != nil {
		t.Fatal("the file outside the jail was disturbed")
	}

	// Rename carries a second path, and it is the one people forget to check.
	if err := os.WriteFile(filepath.Join(dir, "inside.txt"), []byte("mine"), 0o644); err != nil {
		t.Fatal(err)
	}
	_ = client.Rename("inside.txt", "../stolen.txt")
	if _, err := os.Stat(filepath.Join(outside, "stolen.txt")); err == nil {
		t.Fatal("a rename moved a file out of the server directory")
	}
}

// The ordinary case: a file goes up, lands where it was asked to, and comes
// back with the same bytes.
func TestUploadAndDownload(t *testing.T) {
	client, dir := dial(t, &fakePanel{password: "hunter2", permissions: allPermissions()}, "hunter2")

	body := strings.Repeat("modpack bytes ", 5000)

	file, err := client.Create("mods/big.jar")
	if err != nil {
		t.Fatalf("create: %v", err)
	}
	if _, err := io.WriteString(file, body); err != nil {
		t.Fatalf("write: %v", err)
	}
	if err := file.Close(); err != nil {
		t.Fatalf("close: %v", err)
	}

	// On disk, inside the jail, with the right contents.
	landed := filepath.Join(dir, "mods", "big.jar")
	on, err := os.ReadFile(landed)
	if err != nil {
		t.Fatalf("the upload is not where it should be: %v", err)
	}
	if string(on) != body {
		t.Fatalf("uploaded %d bytes, found %d on disk", len(body), len(on))
	}

	back, err := client.Open("mods/big.jar")
	if err != nil {
		t.Fatalf("open for read: %v", err)
	}
	defer back.Close()
	got, err := io.ReadAll(back)
	if err != nil {
		t.Fatalf("read back: %v", err)
	}
	if string(got) != body {
		t.Fatal("what came back is not what went up")
	}
}

// Permissions come from the panel and are the same strings the web file manager
// uses, so a subuser who cannot delete in the panel cannot delete over SFTP.
func TestPermissionsAreEnforcedPerOperation(t *testing.T) {
	// Read and create only: no update, no delete.
	client, dir := dial(t, &fakePanel{
		password:    "hunter2",
		permissions: []string{permRead, permCreate},
	}, "hunter2")

	if err := os.WriteFile(filepath.Join(dir, "server.properties"), []byte("level-name=world"), 0o644); err != nil {
		t.Fatal(err)
	}

	// Allowed: listing, reading, creating something new.
	if _, err := client.ReadDir("."); err != nil {
		t.Errorf("file.read should allow a listing: %v", err)
	}
	file, err := client.Create("new-file.txt")
	if err != nil {
		t.Errorf("file.create should allow a new file: %v", err)
	} else {
		_ = file.Close()
	}

	// Refused: overwriting an existing file is file.update, not file.create.
	if _, err := client.OpenFile("server.properties", os.O_WRONLY|os.O_TRUNC); err == nil {
		t.Error("overwriting an existing file needs file.update and should have been refused")
	}
	if err := client.Remove("server.properties"); err == nil {
		t.Error("deleting needs file.delete and should have been refused")
	}
	if err := client.Rename("server.properties", "renamed.properties"); err == nil {
		t.Error("renaming needs file.update and should have been refused")
	}

	// The file is still there and still says what it said.
	if body, err := os.ReadFile(filepath.Join(dir, "server.properties")); err != nil || string(body) != "level-name=world" {
		t.Fatalf("a refused operation changed the file anyway: %q, %v", body, err)
	}
}

// A symlink is the one path this package cannot check, because it is followed
// later by something else. Refused rather than resolved.
func TestSymlinksAreRefused(t *testing.T) {
	client, _ := dial(t, &fakePanel{password: "hunter2", permissions: allPermissions()}, "hunter2")

	if err := client.Symlink("/etc/passwd", "escape"); err == nil {
		t.Fatal("a client created a symlink; it could then be followed out of the jail")
	}
}

func TestAWrongPasswordIsRefused(t *testing.T) {
	fake := &fakePanel{password: "hunter2", permissions: allPermissions()}
	root := t.TempDir()
	server, err := New("127.0.0.1:0", filepath.Join(t.TempDir(), "hostkey"), store.New(root, nil), fake)
	if err != nil {
		t.Fatal(err)
	}
	go func() { _ = server.Serve() }()
	deadline := time.Now().Add(5 * time.Second)
	for server.Addr() == nil && time.Now().Before(deadline) {
		time.Sleep(5 * time.Millisecond)
	}
	t.Cleanup(func() { _ = server.Close() })

	_, err = ssh.Dial("tcp", server.Addr().String(), &ssh.ClientConfig{
		User:            "allen.111111112222",
		Auth:            []ssh.AuthMethod{ssh.Password("not the password")},
		HostKeyCallback: ssh.InsecureIgnoreHostKey(),
		Timeout:         5 * time.Second,
	})
	if err == nil {
		t.Fatal("a wrong password was accepted")
	}
	if fake.calls != 1 {
		t.Fatalf("the panel was asked %d times, want exactly 1", fake.calls)
	}
}

// A node whose panel is unreachable must not silently let people in, and must
// not report it as a wrong password either.
func TestAnUnreachablePanelRefusesRatherThanAdmits(t *testing.T) {
	fake := &fakePanel{password: "hunter2", err: errors.New("connection refused")}
	root := t.TempDir()
	server, err := New("127.0.0.1:0", filepath.Join(t.TempDir(), "hostkey"), store.New(root, nil), fake)
	if err != nil {
		t.Fatal(err)
	}
	go func() { _ = server.Serve() }()
	deadline := time.Now().Add(5 * time.Second)
	for server.Addr() == nil && time.Now().Before(deadline) {
		time.Sleep(5 * time.Millisecond)
	}
	t.Cleanup(func() { _ = server.Close() })

	if _, err := ssh.Dial("tcp", server.Addr().String(), &ssh.ClientConfig{
		User:            "allen.111111112222",
		Auth:            []ssh.AuthMethod{ssh.Password("hunter2")},
		HostKeyCallback: ssh.InsecureIgnoreHostKey(),
		Timeout:         5 * time.Second,
	}); err == nil {
		t.Fatal("a login was accepted while the panel was unreachable")
	}
}

// The host key is the node's identity. Regenerating it on every restart would
// give every client the warning that normally means the connection is being
// intercepted, and teach people to click through it.
func TestTheHostKeySurvivesARestart(t *testing.T) {
	path := filepath.Join(t.TempDir(), "hostkey")
	st := store.New(t.TempDir(), nil)

	first, err := New("127.0.0.1:0", path, st, &fakePanel{})
	if err != nil {
		t.Fatal(err)
	}
	second, err := New("127.0.0.1:0", path, st, &fakePanel{})
	if err != nil {
		t.Fatal(err)
	}

	if first.Fingerprint() == "" {
		t.Fatal("no fingerprint")
	}
	if first.Fingerprint() != second.Fingerprint() {
		t.Fatalf("the host key changed across a restart: %s then %s", first.Fingerprint(), second.Fingerprint())
	}

	info, err := os.Stat(path)
	if err != nil {
		t.Fatal(err)
	}
	if info.Mode().Perm() != 0o600 {
		t.Fatalf("the host key is mode %04o, want 0600: anyone who can read it can impersonate this node", info.Mode().Perm())
	}
}

// Guessing runs find a password port within a day of it opening. The panel
// checks every password, so without a limiter here a guessing run becomes an
// unbounded stream of calls into the panel's database.
func TestRepeatedFailuresBlockAnAddress(t *testing.T) {
	l := newLimiter()
	now := time.Now()
	l.now = func() time.Time { return now }

	for i := 0; i < maxFailures-1; i++ {
		l.failed("10.0.0.1")
	}
	if _, blocked := l.blocked("10.0.0.1"); blocked {
		t.Fatal("blocked before the limit was reached")
	}

	l.failed("10.0.0.1")
	if _, blocked := l.blocked("10.0.0.1"); !blocked {
		t.Fatal("not blocked after reaching the limit")
	}

	// Another address is unaffected: a run trying thousands of usernames from
	// one place must not lock out everybody else.
	if _, blocked := l.blocked("10.0.0.2"); blocked {
		t.Fatal("an unrelated address was blocked")
	}

	// And the block lifts.
	now = now.Add(blockFor + time.Second)
	if _, blocked := l.blocked("10.0.0.1"); blocked {
		t.Fatal("still blocked after the block expired")
	}
}

// A correct password proves the address is not working through a list, so the
// couple of typos before it must not count towards a block for the rest of the day.
func TestASuccessClearsTheRecord(t *testing.T) {
	l := newLimiter()
	l.failed("10.0.0.1")
	l.failed("10.0.0.1")
	l.succeeded("10.0.0.1")

	for i := 0; i < maxFailures-1; i++ {
		l.failed("10.0.0.1")
	}
	if _, blocked := l.blocked("10.0.0.1"); blocked {
		t.Fatal("earlier failures were still being counted after a successful login")
	}
}
