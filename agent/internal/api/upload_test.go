package api

import (
	"io"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/scriptgain/gamemgr-node/internal/config"
	gruntime "github.com/scriptgain/gamemgr-node/internal/runtime"
	"github.com/scriptgain/gamemgr-node/internal/runtime/docker"
	"github.com/scriptgain/gamemgr-node/internal/runtime/store"
)

const (
	testToken = "upload-test-token"
	testUUID  = "11112222-3333-4444-5555-666677778888"
)

// harness builds a daemon over a throwaway data root.
//
// The driver is the Docker one purely because it embeds store.Store, which is
// where the upload actually lives. Nothing in these tests reaches the Docker
// socket: the upload path never calls a container method.
func harness(t *testing.T, maxUploadMiB int) (http.Handler, string) {
	t.Helper()

	root := t.TempDir()
	cfg := config.Config{Token: testToken, Root: root, MaxUploadMiB: maxUploadMiB}
	drivers := gruntime.Registry{"docker": docker.New("/nonexistent.sock", root, nil)}

	return New(cfg, drivers, "test", nil, nil).Handler(), root
}

// serverDir is where the daemon puts this test server's files.
func serverDir(root string) string {
	return filepath.Join(root, store.Short(testUUID))
}

// upload posts a body to the upload endpoint. A knownLength of false sends the
// body with no Content-Length, which is what a chunked transfer looks like and
// what proves the size cap is counted rather than trusted from a header.
func upload(t *testing.T, h http.Handler, path, body string, knownLength bool, query string) *httptest.ResponseRecorder {
	t.Helper()

	url := "/api/servers/" + testUUID + "/files/upload?runtime=docker&path=" + path + query
	var reader io.Reader = strings.NewReader(body)
	if !knownLength {
		// A plain wrapper defeats httptest's length sniffing.
		reader = io.MultiReader(strings.NewReader(body))
	}
	req := httptest.NewRequest(http.MethodPost, url, reader)
	req.Header.Set("Authorization", "Bearer "+testToken)
	rec := httptest.NewRecorder()
	h.ServeHTTP(rec, req)

	return rec
}

func TestUploadWritesTheFileAndReportsItsSize(t *testing.T) {
	h, root := harness(t, 16)

	rec := upload(t, h, "/plugins/server.properties", "motd=hello\n", true, "")
	if rec.Code != http.StatusOK {
		t.Fatalf("want 200, got %d: %s", rec.Code, rec.Body.String())
	}
	if !strings.Contains(rec.Body.String(), `"bytes":11`) {
		t.Fatalf("size not reported back: %s", rec.Body.String())
	}

	landed := filepath.Join(serverDir(root), "plugins", "server.properties")
	got, err := os.ReadFile(landed)
	if err != nil {
		t.Fatalf("file did not land: %v", err)
	}
	if string(got) != "motd=hello\n" {
		t.Fatalf("contents differ: %q", got)
	}

	// 0644, not the 0600 CreateTemp hands out. A game that cannot read its own
	// config file is the whole point of the chmod before the rename.
	info, err := os.Stat(landed)
	if err != nil {
		t.Fatal(err)
	}
	if info.Mode().Perm() != 0o644 {
		t.Fatalf("want mode 0644, got %04o", info.Mode().Perm())
	}
}

// A traversal is refused outright rather than quietly rewritten to something
// inside the server directory. The panel is not trusted to have checked.
func TestUploadRefusesRelativeTraversal(t *testing.T) {
	h, root := harness(t, 16)

	rec := upload(t, h, "../../etc/passwd", "root:x:0:0:owned\n", true, "")
	if rec.Code != http.StatusBadRequest {
		t.Fatalf("want 400, got %d: %s", rec.Code, rec.Body.String())
	}
	if !strings.Contains(rec.Body.String(), "escapes the server directory") {
		t.Fatalf("unhelpful refusal: %s", rec.Body.String())
	}

	// Nothing written anywhere, including the sanitised spelling of the path.
	if _, err := os.Stat(filepath.Join(root, "etc", "passwd")); !os.IsNotExist(err) {
		t.Fatalf("something was written outside the server directory")
	}
	if _, err := os.Stat(filepath.Join(serverDir(root), "etc", "passwd")); !os.IsNotExist(err) {
		t.Fatalf("the traversal was sanitised into the server directory instead of refused")
	}
}

// The same attempt spelled with a leading slash, which is the form the panel's
// own paths take and therefore the one most likely to be tried.
func TestUploadRefusesTraversalFromAnAbsolutePath(t *testing.T) {
	h, root := harness(t, 16)

	for _, path := range []string{"/../../etc/passwd", "/plugins/../../../escape.txt", "/.."} {
		rec := upload(t, h, path, "owned\n", true, "")
		if rec.Code != http.StatusBadRequest {
			t.Fatalf("%s: want 400, got %d: %s", path, rec.Code, rec.Body.String())
		}
	}

	if _, err := os.Stat(filepath.Join(root, "escape.txt")); !os.IsNotExist(err) {
		t.Fatalf("something was written outside the server directory")
	}
}

// An absolute host path with no traversal in it is not an escape attempt: the
// panel's paths are absolute from the server's own root, so /etc/passwd means
// "etc/passwd inside my server", and that is where it goes. What must never
// happen is the real one being touched.
func TestUploadTreatsAnAbsolutePathAsServerRelative(t *testing.T) {
	h, root := harness(t, 16)

	before, hostReadErr := os.ReadFile("/etc/passwd")

	rec := upload(t, h, "/etc/passwd", "not the real one\n", true, "")
	if rec.Code != http.StatusOK {
		t.Fatalf("want 200, got %d: %s", rec.Code, rec.Body.String())
	}

	landed := filepath.Join(serverDir(root), "etc", "passwd")
	if _, err := os.Stat(landed); err != nil {
		t.Fatalf("file did not land inside the server directory: %v", err)
	}
	if hostReadErr == nil {
		after, err := os.ReadFile("/etc/passwd")
		if err != nil || string(after) != string(before) {
			t.Fatalf("the host's /etc/passwd was touched")
		}
	}
}

// The cap is the node's, and it is applied to what arrives rather than to what
// the sender claimed. No Content-Length here, so the header shortcut cannot be
// what refuses it.
func TestUploadRefusesAFileOverTheCapWithNoContentLength(t *testing.T) {
	h, root := harness(t, 16)

	rec := upload(t, h, "/big.bin", strings.Repeat("x", 64), false, "&max_bytes=16")
	if rec.Code != http.StatusRequestEntityTooLarge {
		t.Fatalf("want 413, got %d: %s", rec.Code, rec.Body.String())
	}

	if _, err := os.Stat(filepath.Join(serverDir(root), "big.bin")); !os.IsNotExist(err) {
		t.Fatalf("an oversized upload left a file behind")
	}
	// And no scratch file either: a refused upload must not litter.
	entries, err := os.ReadDir(serverDir(root))
	if err == nil {
		for _, e := range entries {
			if strings.HasPrefix(e.Name(), ".gamemgr-upload-") {
				t.Fatalf("temporary file left behind: %s", e.Name())
			}
		}
	}
}

// A declared length over the cap is refused before the body is streamed at all.
func TestUploadRefusesAnOversizedContentLength(t *testing.T) {
	h, _ := harness(t, 16)

	rec := upload(t, h, "/big.bin", strings.Repeat("x", 64), true, "&max_bytes=16")
	if rec.Code != http.StatusRequestEntityTooLarge {
		t.Fatalf("want 413, got %d: %s", rec.Code, rec.Body.String())
	}
}

// The node's own ceiling is not something the caller can raise.
func TestUploadIgnoresACapLargerThanTheNodes(t *testing.T) {
	h, root := harness(t, 1)

	// 2 MiB of body against a 1 MiB node, asking for 64 MiB.
	rec := upload(t, h, "/huge.bin", strings.Repeat("x", 2<<20), false, "&max_bytes=67108864")
	if rec.Code != http.StatusRequestEntityTooLarge {
		t.Fatalf("want 413, got %d: %s", rec.Code, rec.Body.String())
	}
	if _, err := os.Stat(filepath.Join(serverDir(root), "huge.bin")); !os.IsNotExist(err) {
		t.Fatalf("the node's own cap was bypassed by the caller's max_bytes")
	}
}

// Exactly at the cap is a success, and one byte past it is a refusal rather
// than a truncated file called a success.
func TestUploadIsExactAtTheBoundary(t *testing.T) {
	h, root := harness(t, 16)

	if rec := upload(t, h, "/exact.bin", strings.Repeat("x", 32), false, "&max_bytes=32"); rec.Code != http.StatusOK {
		t.Fatalf("want 200 at exactly the cap, got %d: %s", rec.Code, rec.Body.String())
	}
	if rec := upload(t, h, "/over.bin", strings.Repeat("x", 33), false, "&max_bytes=32"); rec.Code != http.StatusRequestEntityTooLarge {
		t.Fatalf("want 413 one byte over the cap, got %d: %s", rec.Code, rec.Body.String())
	}
	if _, err := os.Stat(filepath.Join(serverDir(root), "over.bin")); !os.IsNotExist(err) {
		t.Fatalf("a file one byte too large was truncated and kept")
	}
}

// An upload over an existing file leaves the old contents alone when it fails,
// because the body goes to a scratch file and is renamed into place.
func TestUploadDoesNotDamageAnExistingFileWhenRefused(t *testing.T) {
	h, root := harness(t, 16)

	if rec := upload(t, h, "/keep.txt", "original\n", true, ""); rec.Code != http.StatusOK {
		t.Fatalf("setup upload failed: %s", rec.Body.String())
	}
	if rec := upload(t, h, "/keep.txt", strings.Repeat("x", 64), false, "&max_bytes=8"); rec.Code != http.StatusRequestEntityTooLarge {
		t.Fatalf("want 413, got %d", rec.Code)
	}

	got, err := os.ReadFile(filepath.Join(serverDir(root), "keep.txt"))
	if err != nil || string(got) != "original\n" {
		t.Fatalf("the existing file was damaged by a refused upload: %q %v", got, err)
	}
}

// A folder is not somewhere a file can be written, and saying so beats an
// opaque "is a directory" from the kernel.
func TestUploadRefusesAFolderAsTheDestination(t *testing.T) {
	h, root := harness(t, 16)

	if err := os.MkdirAll(filepath.Join(serverDir(root), "plugins"), 0o755); err != nil {
		t.Fatal(err)
	}

	rec := upload(t, h, "/plugins", "nope\n", true, "")
	if rec.Code != http.StatusBadRequest {
		t.Fatalf("want 400, got %d: %s", rec.Code, rec.Body.String())
	}
}

// Without the panel's bearer token the endpoint does not exist as far as the
// caller is concerned.
func TestUploadNeedsTheNodeToken(t *testing.T) {
	h, root := harness(t, 16)

	req := httptest.NewRequest(http.MethodPost,
		"/api/servers/"+testUUID+"/files/upload?runtime=docker&path=/anon.txt",
		strings.NewReader("anonymous\n"))
	rec := httptest.NewRecorder()
	h.ServeHTTP(rec, req)

	if rec.Code != http.StatusUnauthorized {
		t.Fatalf("want 401, got %d", rec.Code)
	}
	if _, err := os.Stat(filepath.Join(serverDir(root), "anon.txt")); !os.IsNotExist(err) {
		t.Fatalf("an unauthenticated upload landed")
	}
}
