// Package api is the panel-facing HTTP surface of a node daemon.
//
// Transport note: console and live stats stream as Server-Sent Events rather
// than websockets. SSE is one-way, which is all a console feed needs, it works
// through every proxy that already handles HTTP, and it keeps this daemon on
// the Go standard library with no vendored dependencies. Console input is a
// plain POST. A websocket upgrade path can be added alongside when the real
// drivers land without changing any of the routes below.
package api

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"log"
	"net/http"
	"runtime/debug"
	"strconv"
	"strings"
	"sync/atomic"
	"time"

	"github.com/scriptgain/gamemgr-node/internal/config"
	"github.com/scriptgain/gamemgr-node/internal/firewall"
	gruntime "github.com/scriptgain/gamemgr-node/internal/runtime"
	"github.com/scriptgain/gamemgr-node/internal/supervise"
)

type Server struct {
	cfg     config.Config
	drivers gruntime.Registry
	started time.Time
	version string
	sup     *supervise.Supervisor
	fw      *firewall.Manager
	// Held apart from cfg because enrollment can finish after the listener is
	// already up, and a node that just enrolled has to start accepting the
	// panel without waiting for a restart.
	token atomic.Value
}

func New(cfg config.Config, drivers gruntime.Registry, version string, sup *supervise.Supervisor, fw *firewall.Manager) *Server {
	s := &Server{cfg: cfg, drivers: drivers, started: time.Now(), version: version, sup: sup, fw: fw}
	s.token.Store(cfg.Token)

	return s
}

// SetToken swaps in the credential enrollment obtained.
func (s *Server) SetToken(token string) { s.token.Store(token) }

func (s *Server) currentToken() string {
	token, _ := s.token.Load().(string)

	return token
}

func (s *Server) Handler() http.Handler {
	mux := http.NewServeMux()

	// Unauthenticated: liveness only. Never leaks anything about the node.
	mux.HandleFunc("GET /healthz", func(w http.ResponseWriter, r *http.Request) {
		writeJSON(w, http.StatusOK, map[string]any{"ok": true})
	})

	// Everything else needs the panel's bearer token.
	mux.Handle("GET /api/system", s.auth(http.HandlerFunc(s.system)))
	mux.Handle("POST /api/servers/{uuid}/power", s.auth(http.HandlerFunc(s.power)))
	mux.Handle("POST /api/servers/{uuid}/command", s.auth(http.HandlerFunc(s.command)))
	mux.Handle("POST /api/servers/{uuid}/install", s.auth(http.HandlerFunc(s.install)))
	mux.Handle("POST /api/servers/{uuid}/update", s.auth(http.HandlerFunc(s.update)))
	mux.Handle("GET /api/servers/{uuid}/stats", s.auth(http.HandlerFunc(s.stats)))
	mux.Handle("GET /api/servers/{uuid}/stream", s.auth(http.HandlerFunc(s.stream)))
	mux.Handle("GET /api/servers/{uuid}/logs", s.auth(http.HandlerFunc(s.logs)))
	mux.Handle("GET /api/servers/{uuid}/files", s.auth(http.HandlerFunc(s.filesList)))
	mux.Handle("GET /api/servers/{uuid}/files/contents", s.auth(http.HandlerFunc(s.filesRead)))
	mux.Handle("POST /api/servers/{uuid}/files/write", s.auth(http.HandlerFunc(s.filesWrite)))
	mux.Handle("POST /api/servers/{uuid}/files/delete", s.auth(http.HandlerFunc(s.filesDelete)))
	mux.Handle("POST /api/servers/{uuid}/files/rename", s.auth(http.HandlerFunc(s.filesRename)))
	mux.Handle("POST /api/servers/{uuid}/files/mkdir", s.auth(http.HandlerFunc(s.filesMkdir)))
	mux.Handle("POST /api/servers/{uuid}/backup", s.auth(http.HandlerFunc(s.backup)))
	mux.Handle("POST /api/servers/{uuid}/restore", s.auth(http.HandlerFunc(s.restore)))
	mux.Handle("DELETE /api/servers/{uuid}", s.auth(http.HandlerFunc(s.destroy)))

	return recoverer(logger(mux))
}

// ---------------------------------------------------------------- middleware

func (s *Server) auth(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		token := s.currentToken()
		if token == "" {
			// An unconfigured node refuses everything rather than running
			// open. A node with no token has not been enrolled yet.
			writeErr(w, http.StatusServiceUnavailable, "node not enrolled")
			return
		}
		got := strings.TrimPrefix(r.Header.Get("Authorization"), "Bearer ")
		// Constant-time-ish: length check first, then a full compare that does
		// not short circuit on the first differing byte.
		if len(got) != len(token) || !equal(got, token) {
			writeErr(w, http.StatusUnauthorized, "invalid node token")
			return
		}
		next.ServeHTTP(w, r)
	})
}

func equal(a, b string) bool {
	var v byte
	for i := 0; i < len(a); i++ {
		v |= a[i] ^ b[i]
	}
	return v == 0
}

func logger(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		start := time.Now()
		next.ServeHTTP(w, r)
		if r.URL.Path != "/healthz" {
			log.Printf("%s %s %s", r.Method, r.URL.Path, time.Since(start).Round(time.Millisecond))
		}
	})
}

func recoverer(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		defer func() {
			if rec := recover(); rec != nil {
				log.Printf("panic on %s %s: %v\n%s", r.Method, r.URL.Path, rec, debug.Stack())
				writeErr(w, http.StatusInternalServerError, "internal daemon error")
			}
		}()
		next.ServeHTTP(w, r)
	})
}

// ------------------------------------------------------------------ handlers

func (s *Server) system(w http.ResponseWriter, r *http.Request) {
	drivers := map[string]any{}
	for name, d := range s.drivers {
		ok, detail := d.Available(r.Context())
		drivers[name] = map[string]any{"available": ok, "detail": detail}
	}
	// Whether this node's ports are managed at all. Present and Active are
	// reported separately: "no ufw here" is a legitimate node behind a cloud
	// firewall, "ufw installed but switched off" usually is not, and the panel
	// cannot tell an operator which to fix from one boolean.
	var fwState any
	if s.fw != nil {
		fwState = s.fw.Status(r.Context())
	}

	writeJSON(w, http.StatusOK, map[string]any{
		"name":          s.cfg.Name,
		"version":       s.version,
		"uptime_sec":    int64(time.Since(s.started).Seconds()),
		"root":          s.cfg.Root,
		"forced_driver": s.cfg.Driver,
		"drivers":       drivers,
		"firewall":      fwState,
		// Whether a memory or CPU limit on a native server is real. The panel
		// shows the limit on every screen, and one that nothing enforces reads
		// as a promise, so it is stated rather than assumed.
		"limits_enforced": s.sup != nil && s.sup.LimitsEnforced(),
	})
}

type serverBody struct {
	Server gruntime.Server `json:"server"`
}

// decodeServer pulls the server definition off the request. The panel is the
// source of truth and sends everything the daemon needs, so a node holds no
// state it could disagree with.
func (s *Server) decodeServer(r *http.Request) (gruntime.Server, gruntime.Driver, error) {
	var body serverBody
	if r.Body != nil {
		_ = json.NewDecoder(r.Body).Decode(&body)
	}
	srv := body.Server
	if srv.UUID == "" {
		srv.UUID = r.PathValue("uuid")
	}
	if srv.UUID == "" {
		return srv, nil, errors.New("missing server uuid")
	}
	if srv.Runtime == "" {
		srv.Runtime = "docker"
	}
	name := srv.Runtime
	if s.cfg.Driver != "" {
		name = s.cfg.Driver
	}
	d, ok := s.drivers.Get(name)
	if !ok {
		return srv, nil, fmt.Errorf("no driver for runtime %q on this node", srv.Runtime)
	}
	return srv, d, nil
}

// queryServer is decodeServer for GET requests, where the definition arrives as
// a base64-free query string instead of a body.
func (s *Server) queryServer(r *http.Request) (gruntime.Server, gruntime.Driver, error) {
	srv := gruntime.Server{
		UUID:          r.PathValue("uuid"),
		Name:          r.URL.Query().Get("name"),
		Runtime:       r.URL.Query().Get("runtime"),
		Image:         r.URL.Query().Get("image"),
		IP:            r.URL.Query().Get("ip"),
		LGSMShortname: r.URL.Query().Get("lgsm"),
		DataPath:      r.URL.Query().Get("data_path"),
	}
	srv.Port, _ = strconv.Atoi(r.URL.Query().Get("port"))
	srv.MemoryMiB, _ = strconv.ParseInt(r.URL.Query().Get("memory"), 10, 64)
	srv.DiskMiB, _ = strconv.ParseInt(r.URL.Query().Get("disk"), 10, 64)
	srv.CPUPercent, _ = strconv.Atoi(r.URL.Query().Get("cpu"))
	srv.SteamAppID, _ = strconv.Atoi(r.URL.Query().Get("steam_app_id"))
	if srv.Runtime == "" {
		srv.Runtime = "docker"
	}
	if srv.Name == "" {
		srv.Name = "server-" + srv.UUID
	}
	name := srv.Runtime
	if s.cfg.Driver != "" {
		name = s.cfg.Driver
	}
	d, ok := s.drivers.Get(name)
	if !ok {
		return srv, nil, fmt.Errorf("no driver for runtime %q on this node", srv.Runtime)
	}
	return srv, d, nil
}

func (s *Server) power(w http.ResponseWriter, r *http.Request) {
	var body struct {
		serverBody
		Action string `json:"action"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)
	srv := body.Server
	if srv.UUID == "" {
		srv.UUID = r.PathValue("uuid")
	}
	if srv.Runtime == "" {
		srv.Runtime = "docker"
	}
	name := srv.Runtime
	if s.cfg.Driver != "" {
		name = s.cfg.Driver
	}
	d, ok := s.drivers.Get(name)
	if !ok {
		writeErr(w, http.StatusBadRequest, "no driver for runtime "+srv.Runtime)
		return
	}

	var err error
	switch body.Action {
	case "start":
		err = d.Start(r.Context(), srv)
	case "stop":
		err = d.Stop(r.Context(), srv)
	case "restart":
		err = d.Restart(r.Context(), srv)
	case "kill":
		err = d.Kill(r.Context(), srv)
	default:
		writeErr(w, http.StatusBadRequest, "unknown power action")
		return
	}
	if err != nil {
		writeErr(w, http.StatusBadGateway, err.Error())
		return
	}
	st, _ := d.Stats(r.Context(), srv)
	writeJSON(w, http.StatusOK, map[string]any{"ok": true, "state": st.State})
}

func (s *Server) command(w http.ResponseWriter, r *http.Request) {
	var body struct {
		serverBody
		Command string `json:"command"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)
	srv := body.Server
	if srv.UUID == "" {
		srv.UUID = r.PathValue("uuid")
	}
	if srv.Runtime == "" {
		srv.Runtime = "docker"
	}
	name := srv.Runtime
	if s.cfg.Driver != "" {
		name = s.cfg.Driver
	}
	d, ok := s.drivers.Get(name)
	if !ok {
		writeErr(w, http.StatusBadRequest, "no driver for runtime "+srv.Runtime)
		return
	}
	if err := d.Command(r.Context(), srv, body.Command); err != nil {
		writeErr(w, http.StatusBadGateway, err.Error())
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"ok": true})
}

func (s *Server) install(w http.ResponseWriter, r *http.Request) {
	srv, d, err := s.decodeServer(r)
	if err != nil {
		writeErr(w, http.StatusBadRequest, err.Error())
		return
	}
	sse := newSSE(w)
	if sse == nil {
		writeErr(w, http.StatusInternalServerError, "streaming unsupported")
		return
	}
	if err := d.Install(r.Context(), srv, sse); err != nil {
		sse.Event("error", err.Error())
		return
	}
	sse.Event("done", "install complete")
}

func (s *Server) update(w http.ResponseWriter, r *http.Request) {
	srv, d, err := s.decodeServer(r)
	if err != nil {
		writeErr(w, http.StatusBadRequest, err.Error())
		return
	}
	sse := newSSE(w)
	if sse == nil {
		writeErr(w, http.StatusInternalServerError, "streaming unsupported")
		return
	}
	if err := d.Update(r.Context(), srv, sse); err != nil {
		sse.Event("error", err.Error())
		return
	}
	sse.Event("done", "update complete")
}

func (s *Server) stats(w http.ResponseWriter, r *http.Request) {
	srv, d, err := s.queryServer(r)
	if err != nil {
		writeErr(w, http.StatusBadRequest, err.Error())
		return
	}
	st, err := d.Stats(r.Context(), srv)
	if err != nil {
		writeErr(w, http.StatusBadGateway, err.Error())
		return
	}
	writeJSON(w, http.StatusOK, st)
}

// stream is the live console plus a stats frame every few seconds, as SSE.
// Event names are "console" and "stats" so one connection feeds both the
// terminal and the graphs.
func (s *Server) stream(w http.ResponseWriter, r *http.Request) {
	srv, d, err := s.queryServer(r)
	if err != nil {
		writeErr(w, http.StatusBadRequest, err.Error())
		return
	}
	sse := newSSE(w)
	if sse == nil {
		writeErr(w, http.StatusInternalServerError, "streaming unsupported")
		return
	}

	ctx, cancel := context.WithCancel(r.Context())
	defer cancel()

	// Stats frames on their own cadence, alongside the console feed.
	go func() {
		t := time.NewTicker(2 * time.Second)
		defer t.Stop()
		for {
			select {
			case <-ctx.Done():
				return
			case <-t.C:
				st, err := d.Stats(ctx, srv)
				if err != nil {
					continue
				}
				b, _ := json.Marshal(st)
				sse.Event("stats", string(b))
			}
		}
	}()

	// Logs blocks until the client disconnects, which is what holds the
	// connection open.
	_ = d.Logs(ctx, srv, 200, sse.ConsoleWriter())
}

func (s *Server) logs(w http.ResponseWriter, r *http.Request) {
	srv, d, err := s.queryServer(r)
	if err != nil {
		writeErr(w, http.StatusBadRequest, err.Error())
		return
	}
	tail, _ := strconv.Atoi(r.URL.Query().Get("tail"))
	if tail <= 0 {
		tail = 100
	}
	// Backlog, not Logs. Logs follows, so it only came back when this timeout
	// expired: the console page paid a flat two seconds on every load. The
	// timeout stays as a ceiling on a wedged driver, not as the normal path.
	ctx, cancel := context.WithTimeout(r.Context(), 2*time.Second)
	defer cancel()
	lines, err := d.Backlog(ctx, srv, tail)
	if err != nil {
		writeErr(w, http.StatusBadGateway, err.Error())

		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"lines": lines})
}

func (s *Server) filesList(w http.ResponseWriter, r *http.Request) {
	srv, d, err := s.queryServer(r)
	if err != nil {
		writeErr(w, http.StatusBadRequest, err.Error())
		return
	}
	path := r.URL.Query().Get("path")
	if path == "" {
		path = "/"
	}
	entries, err := d.List(r.Context(), srv, path)
	if err != nil {
		writeErr(w, http.StatusBadGateway, err.Error())
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"path": path, "entries": entries})
}

func (s *Server) filesRead(w http.ResponseWriter, r *http.Request) {
	srv, d, err := s.queryServer(r)
	if err != nil {
		writeErr(w, http.StatusBadRequest, err.Error())
		return
	}
	body, err := d.Read(r.Context(), srv, r.URL.Query().Get("path"))
	if err != nil {
		writeErr(w, http.StatusNotFound, err.Error())
		return
	}
	w.Header().Set("Content-Type", "text/plain; charset=utf-8")
	_, _ = w.Write(body)
}

func (s *Server) filesWrite(w http.ResponseWriter, r *http.Request) {
	var body struct {
		serverBody
		Path    string `json:"path"`
		Content string `json:"content"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)
	srv, d, err := s.hydrate(r, body.Server)
	if err != nil {
		writeErr(w, http.StatusBadRequest, err.Error())
		return
	}
	if err := d.Write(r.Context(), srv, body.Path, []byte(body.Content)); err != nil {
		writeErr(w, http.StatusBadGateway, err.Error())
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"ok": true})
}

func (s *Server) filesDelete(w http.ResponseWriter, r *http.Request) {
	var body struct {
		serverBody
		Paths []string `json:"paths"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)
	srv, d, err := s.hydrate(r, body.Server)
	if err != nil {
		writeErr(w, http.StatusBadRequest, err.Error())
		return
	}
	if err := d.Delete(r.Context(), srv, body.Paths); err != nil {
		writeErr(w, http.StatusBadGateway, err.Error())
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"ok": true})
}

func (s *Server) filesRename(w http.ResponseWriter, r *http.Request) {
	var body struct {
		serverBody
		From string `json:"from"`
		To   string `json:"to"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)
	srv, d, err := s.hydrate(r, body.Server)
	if err != nil {
		writeErr(w, http.StatusBadRequest, err.Error())
		return
	}
	if err := d.Rename(r.Context(), srv, body.From, body.To); err != nil {
		writeErr(w, http.StatusBadGateway, err.Error())
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"ok": true})
}

func (s *Server) filesMkdir(w http.ResponseWriter, r *http.Request) {
	var body struct {
		serverBody
		Path string `json:"path"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)
	srv, d, err := s.hydrate(r, body.Server)
	if err != nil {
		writeErr(w, http.StatusBadRequest, err.Error())
		return
	}
	if err := d.MakeDir(r.Context(), srv, body.Path); err != nil {
		writeErr(w, http.StatusBadGateway, err.Error())
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"ok": true})
}

func (s *Server) backup(w http.ResponseWriter, r *http.Request) {
	var body struct {
		serverBody
		BackupUUID string   `json:"backup_uuid"`
		Ignore     []string `json:"ignore"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)
	srv, d, err := s.hydrate(r, body.Server)
	if err != nil {
		writeErr(w, http.StatusBadRequest, err.Error())
		return
	}
	bytes, sum, err := d.Backup(r.Context(), srv, body.BackupUUID, body.Ignore)
	if err != nil {
		writeErr(w, http.StatusBadGateway, err.Error())
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"ok": true, "bytes": bytes, "checksum": sum})
}

func (s *Server) restore(w http.ResponseWriter, r *http.Request) {
	var body struct {
		serverBody
		BackupUUID string `json:"backup_uuid"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)
	srv, d, err := s.hydrate(r, body.Server)
	if err != nil {
		writeErr(w, http.StatusBadRequest, err.Error())
		return
	}
	if err := d.Restore(r.Context(), srv, body.BackupUUID); err != nil {
		writeErr(w, http.StatusBadGateway, err.Error())
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"ok": true})
}

func (s *Server) destroy(w http.ResponseWriter, r *http.Request) {
	srv, d, err := s.decodeServer(r)
	if err != nil {
		writeErr(w, http.StatusBadRequest, err.Error())
		return
	}
	if err := d.Destroy(r.Context(), srv); err != nil {
		writeErr(w, http.StatusBadGateway, err.Error())
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"ok": true})
}

// hydrate fills in the uuid and runtime defaults for a body-supplied server and
// resolves its driver.
func (s *Server) hydrate(r *http.Request, srv gruntime.Server) (gruntime.Server, gruntime.Driver, error) {
	if srv.UUID == "" {
		srv.UUID = r.PathValue("uuid")
	}
	if srv.Runtime == "" {
		srv.Runtime = "docker"
	}
	name := srv.Runtime
	if s.cfg.Driver != "" {
		name = s.cfg.Driver
	}
	d, ok := s.drivers.Get(name)
	if !ok {
		return srv, nil, fmt.Errorf("no driver for runtime %q on this node", srv.Runtime)
	}
	return srv, d, nil
}

// --------------------------------------------------------------------- utils

func writeJSON(w http.ResponseWriter, code int, v any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(code)
	_ = json.NewEncoder(w).Encode(v)
}

func writeErr(w http.ResponseWriter, code int, msg string) {
	writeJSON(w, code, map[string]any{"error": msg})
}
