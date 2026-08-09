// Package sftp gives a server's owner real file access to their own directory.
//
// The web file manager is fine for editing a config file and useless for
// uploading a four gigabyte modpack or syncing a world, which is why this
// exists at all.
//
// Three things are worth knowing before reading further.
//
// The jail is in this package, not in the operating system. Every server on a
// node runs as the same unix account, so file permissions cannot separate one
// customer from another; what separates them is that every path a client names
// is resolved through the same store.Resolve the panel's file manager uses, and
// anything landing outside the server's own directory is refused. There is no
// chroot and there are no per-server unix users.
//
// This daemon holds no accounts. A login is one call to the panel, and the
// panel's answer, which is produced by the same policy that guards the web file
// manager, is the whole of this connection's authority.
//
// And nothing here is a shell. Only the sftp subsystem is answered; a client
// asking for a pty, a command or a port forward is refused. Somebody with a
// valid password gets their own files and nothing else on the box.
package sftp

import (
	"context"
	"crypto/ed25519"
	"crypto/rand"
	"encoding/pem"
	"errors"
	"fmt"
	"log"
	"net"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"sync"
	"time"

	"github.com/pkg/sftp"
	"golang.org/x/crypto/ssh"

	"github.com/scriptgain/gamemgr-node/internal/panel"
	gruntime "github.com/scriptgain/gamemgr-node/internal/runtime"
	"github.com/scriptgain/gamemgr-node/internal/runtime/store"
)

// Authenticator is the panel, or a fake in tests.
type Authenticator interface {
	AuthenticateSFTP(ctx context.Context, username, password, ip string) (*panel.SFTPGrant, error)
}

// Server answers SFTP for every game server on this node.
type Server struct {
	listen string
	store  store.Store
	auth   Authenticator

	config   *ssh.ServerConfig
	signer   ssh.Signer
	limiter  *limiter
	listener net.Listener

	wg     sync.WaitGroup
	closed chan struct{}
	once   sync.Once
}

// New prepares the SFTP server. It does not listen until Serve is called.
//
// hostKeyPath is where the node's identity lives. It is generated on first run
// and then kept: a host key that changed on every restart would give every
// client the loud warning that normally means someone is intercepting the
// connection, and teach people to click through it.
func New(listen, hostKeyPath string, st store.Store, auth Authenticator) (*Server, error) {
	key, err := hostKey(hostKeyPath)
	if err != nil {
		return nil, err
	}

	s := &Server{
		listen:  listen,
		store:   st,
		auth:    auth,
		signer:  key,
		limiter: newLimiter(),
		closed:  make(chan struct{}),
	}

	s.config = &ssh.ServerConfig{
		PasswordCallback: s.password,
		// No public key callback. Keys would have to be managed in the panel and
		// distributed to every node, and nothing here is worth the key
		// distribution problem yet. Passwords over an encrypted transport, with
		// the panel doing the checking, is the honest starting point.
		AuthLogCallback: s.logAttempt,
		ServerVersion:   "SSH-2.0-GameMGR",
	}
	s.config.AddHostKey(key)

	return s, nil
}

// Fingerprint is the host key fingerprint, so the panel can show people what
// they should expect to see the first time they connect.
func (s *Server) Fingerprint() string {
	if s.signer == nil {
		return ""
	}

	return ssh.FingerprintSHA256(s.signer.PublicKey())
}

// Serve listens and answers connections until Close.
func (s *Server) Serve() error {
	listener, err := net.Listen("tcp", s.listen)
	if err != nil {
		return fmt.Errorf("sftp listen on %s: %w", s.listen, err)
	}
	s.listener = listener

	for {
		conn, err := listener.Accept()
		if err != nil {
			select {
			case <-s.closed:
				return nil
			default:
			}
			// One bad accept is not a reason to stop answering everybody else.
			log.Printf("sftp: accept: %v", err)

			continue
		}

		s.wg.Add(1)
		go func() {
			defer s.wg.Done()
			s.handle(conn)
		}()
	}
}

// Close stops accepting and waits for open sessions to finish.
func (s *Server) Close() error {
	s.once.Do(func() { close(s.closed) })
	if s.listener != nil {
		_ = s.listener.Close()
	}
	s.wg.Wait()

	return nil
}

// Addr is the address actually bound, which is how a test finds the port when
// it asked for :0.
func (s *Server) Addr() net.Addr {
	if s.listener == nil {
		return nil
	}

	return s.listener.Addr()
}

// ------------------------------------------------------------------ auth

func (s *Server) password(meta ssh.ConnMetadata, password []byte) (*ssh.Permissions, error) {
	ip := host(meta.RemoteAddr())

	// Somebody working through a password list must not get unlimited attempts
	// at the panel's expense, and the panel must not be the thing that notices.
	if wait, blocked := s.limiter.blocked(ip); blocked {
		log.Printf("sftp: %s is rate limited for another %s", ip, wait.Round(time.Second))

		return nil, errDenied
	}

	ctx, cancel := context.WithTimeout(context.Background(), 15*time.Second)
	defer cancel()

	grant, err := s.auth.AuthenticateSFTP(ctx, meta.User(), string(password), ip)
	if err != nil {
		s.limiter.failed(ip)
		if errors.Is(err, panel.ErrDenied) {
			return nil, errDenied
		}
		// The panel being down is not a wrong password, and saying so in the log
		// is the difference between "a customer mistyped" and "nobody on this
		// node can reach their files".
		log.Printf("sftp: could not ask the panel about %q from %s: %v", meta.User(), ip, err)

		return nil, errUnavailable
	}
	s.limiter.succeeded(ip)

	// Carried on the connection rather than looked up again per request. The
	// grant is the answer to one question asked once, at login.
	return &ssh.Permissions{
		Extensions: map[string]string{
			"server":      grant.ServerUUID,
			"runtime":     grant.Runtime,
			"permissions": strings.Join(grant.Permissions, ","),
			"username":    grant.Username,
			// Carried through the SSH connection rather than looked up again:
			// this map is the whole of what survives from login to session, and
			// a limit left out of it is a limit that silently does not apply.
			"disk_mib": strconv.FormatInt(grant.DiskMiB, 10),
		},
	}, nil
}

var (
	// One message for every refusal. Distinguishing "no such user" from "wrong
	// password" tells an unauthenticated stranger which accounts exist.
	errDenied      = errors.New("permission denied")
	errUnavailable = errors.New("file access is temporarily unavailable")
)

func (s *Server) logAttempt(meta ssh.ConnMetadata, method string, err error) {
	if method == "none" {
		// Every client offers "none" first to find out what is supported. It is
		// not a failed login and logging it buries the ones that are.
		return
	}
	if err != nil {
		log.Printf("sftp: rejected %q from %s", meta.User(), host(meta.RemoteAddr()))

		return
	}
	log.Printf("sftp: %q connected from %s", meta.User(), host(meta.RemoteAddr()))
}

// ------------------------------------------------------------- connection

func (s *Server) handle(raw net.Conn) {
	defer raw.Close()

	// A handshake that never completes must not hold a connection open forever.
	_ = raw.SetDeadline(time.Now().Add(30 * time.Second))

	conn, channels, requests, err := ssh.NewServerConn(raw, s.config)
	if err != nil {
		// Includes every failed password, already logged by AuthLogCallback.
		return
	}
	defer conn.Close()

	// Cleared once authenticated: a legitimate transfer of a large file takes
	// far longer than any handshake should.
	_ = raw.SetDeadline(time.Time{})

	go ssh.DiscardRequests(requests)

	grant := grantFrom(conn.Permissions)
	server := gruntime.Server{
		UUID:    grant.ServerUUID,
		Runtime: grant.Runtime,
	}

	for channel := range channels {
		if channel.ChannelType() != "session" {
			_ = channel.Reject(ssh.UnknownChannelType, "only sftp is available on this port")

			continue
		}

		stream, requests, err := channel.Accept()
		if err != nil {
			return
		}

		go s.session(stream, requests, server, grant)
	}
}

// session answers exactly one subsystem request, sftp, and refuses the rest.
func (s *Server) session(stream ssh.Channel, requests <-chan *ssh.Request, server gruntime.Server, grant *panel.SFTPGrant) {
	defer stream.Close()

	for request := range requests {
		// "exec" is a command, "shell" is a login, "pty-req" is a terminal.
		// None of them are file access and none of them are offered here.
		if request.Type != "subsystem" || !isSFTP(request.Payload) {
			_ = request.Reply(false, nil)

			continue
		}
		_ = request.Reply(true, nil)

		handlers := s.handlers(server, grant)
		srv := sftp.NewRequestServer(stream, handlers)
		if err := srv.Serve(); err != nil && !errors.Is(err, os.ErrClosed) {
			// io.EOF is a client disconnecting, which is how every session ends.
			if err.Error() != "EOF" {
				log.Printf("sftp: session for %s ended: %v", grant.Username, err)
			}
		}
		_ = srv.Close()

		return
	}
}

// isSFTP reads the subsystem name out of the request payload, which is a
// four byte length followed by the name.
func isSFTP(payload []byte) bool {
	if len(payload) < 4 {
		return false
	}
	length := int(payload[0])<<24 | int(payload[1])<<16 | int(payload[2])<<8 | int(payload[3])
	if len(payload) < 4+length {
		return false
	}

	return string(payload[4:4+length]) == "sftp"
}

func grantFrom(permissions *ssh.Permissions) *panel.SFTPGrant {
	if permissions == nil {
		return &panel.SFTPGrant{}
	}
	held := permissions.Extensions["permissions"]

	grant := &panel.SFTPGrant{
		Granted:    true,
		ServerUUID: permissions.Extensions["server"],
		Runtime:    permissions.Extensions["runtime"],
		Username:   permissions.Extensions["username"],
	}
	if disk, err := strconv.ParseInt(permissions.Extensions["disk_mib"], 10, 64); err == nil {
		grant.DiskMiB = disk
	}
	if held != "" {
		grant.Permissions = strings.Split(held, ",")
	}

	return grant
}

func host(addr net.Addr) string {
	if addr == nil {
		return "unknown"
	}
	if h, _, err := net.SplitHostPort(addr.String()); err == nil {
		return h
	}

	return addr.String()
}

// ---------------------------------------------------------------- host key

// hostKey loads the node's SSH identity, generating it on first run.
func hostKey(path string) (ssh.Signer, error) {
	if raw, err := os.ReadFile(path); err == nil {
		signer, err := ssh.ParsePrivateKey(raw)
		if err != nil {
			return nil, fmt.Errorf("the SFTP host key at %s could not be read: %w", path, err)
		}

		return signer, nil
	} else if !os.IsNotExist(err) {
		return nil, err
	}

	// ed25519: small, fast, and supported by every client that matters. No key
	// size to choose and no way to choose it badly.
	_, private, err := ed25519.GenerateKey(rand.Reader)
	if err != nil {
		return nil, err
	}

	block, err := ssh.MarshalPrivateKey(private, "")
	if err != nil {
		return nil, err
	}

	if err := os.MkdirAll(filepath.Dir(path), 0o700); err != nil {
		return nil, err
	}
	// 0600 and written before it is used: this is the node's identity, and a
	// readable copy of it lets anyone impersonate the node to its own customers.
	if err := os.WriteFile(path, pem.EncodeToMemory(block), 0o600); err != nil {
		return nil, fmt.Errorf("could not save the SFTP host key to %s: %w", path, err)
	}

	signer, err := ssh.NewSignerFromKey(private)
	if err != nil {
		return nil, err
	}
	log.Printf("sftp: generated a host key at %s (%s)", path, ssh.FingerprintSHA256(signer.PublicKey()))

	return signer, nil
}
