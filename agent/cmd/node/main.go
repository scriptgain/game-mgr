// Command gamemgr-node is the GameMGR node daemon.
//
// One panel, nodes anywhere: this binary is what turns any Linux box into a
// place GameMGR can run game servers. It holds no database and stores no
// authority of its own; the panel sends it everything it needs with each call.
//
// It ships three runtimes rather than one. Docker containers are the default
// and the shape every community template already targets. SteamCMD installs
// natively with no container in the way, which matters for Source and Unreal
// servers that misbehave under a container network namespace. LinuxGSM wraps
// the LinuxGSM control scripts, which brings a catalogue of more than 130 games
// along with it.
//
// NODE_DRIVER=stub answers every runtime with synthetic data. That is what the
// local dev stack runs, so the whole panel can be built and exercised before
// the real drivers exist.
package main

import (
	"context"
	"errors"
	"log"
	"net/http"
	"os"
	"os/signal"
	"sort"
	"strings"
	"syscall"
	"time"

	"github.com/scriptgain/gamemgr-node/internal/api"
	"github.com/scriptgain/gamemgr-node/internal/config"
	dockerapi "github.com/scriptgain/gamemgr-node/internal/docker"
	"github.com/scriptgain/gamemgr-node/internal/firewall"
	"github.com/scriptgain/gamemgr-node/internal/panel"
	gruntime "github.com/scriptgain/gamemgr-node/internal/runtime"
	"github.com/scriptgain/gamemgr-node/internal/runtime/docker"
	"github.com/scriptgain/gamemgr-node/internal/runtime/linuxgsm"
	"github.com/scriptgain/gamemgr-node/internal/runtime/steamcmd"
	"github.com/scriptgain/gamemgr-node/internal/runtime/store"
	"github.com/scriptgain/gamemgr-node/internal/runtime/stub"
	nodesftp "github.com/scriptgain/gamemgr-node/internal/sftp"
	"github.com/scriptgain/gamemgr-node/internal/supervise"
)

const version = "0.1.0"

func main() {
	log.SetFlags(log.LstdFlags | log.LUTC)
	cfg := config.Load()

	// One supervisor shared by both native runtimes, so "how is a native server
	// run" has a single answer and the CPU sampling state lives in one place.
	sup := supervise.New()

	// Games do not run as root. Several refuse outright: PalServer.sh exits
	// with "Refusing to run with the root privileges." and LinuxGSM has always
	// declined. The supervisor therefore carries the account, and every tmux
	// call it makes uses the same one, because a session is only visible to the
	// uid that created it.
	//
	// Resolved here and nowhere else. This one value answers both "who runs a
	// game" and "who owns a game's files", and it is passed to every driver
	// rather than looked up by each of them: four independent lookups with
	// three different candidate lists is how the same ownership bug got fixed
	// five times without ever being fixed.
	runAs := supervise.Unprivileged()
	if runAs != nil {
		sup.RunAs(runAs)
		log.Printf("native servers run as %q (uid %d), and every server file is owned by it", runAs.Name, runAs.Uid)
	} else if os.Geteuid() == 0 {
		log.Printf("warning: running as root with no unprivileged account to drop to; games that refuse root will not start")
	}

	drivers := gruntime.Registry{
		"docker":   docker.New(cfg.DockerSocket, cfg.Root, runAs),
		"steamcmd": steamcmd.New("", cfg.Root, sup, runAs),
		"linuxgsm": linuxgsm.New(cfg.Root, sup, runAs),
	}
	// The stub is only registered when asked for by name. Registering it
	// unconditionally would make a misconfigured production node quietly serve
	// fake data instead of failing loudly, which is far worse than an error.
	if cfg.Driver == "stub" {
		drivers["stub"] = stub.New()
		log.Printf("stub driver active: this node reports synthetic data and runs nothing")
	}

	// Every driver is wrapped so a server's ports open and close with the
	// server itself. The daemon's own listen port joins ssh, 80 and 443 in the
	// set this never touches: closing it would leave the panel with no way back
	// in to fix whatever went wrong.
	fw := firewall.New(firewall.ListenPort(cfg.Listen))
	for name, d := range drivers {
		drivers[name] = firewall.Wrap(d, fw)
	}
	log.Printf("firewall: %s", fw.Status(context.Background()).Detail)

	// The data root has to be a path that means the same thing to this process
	// and to the Docker daemon. Containers are created by the host daemon, so a
	// bind mount is resolved against the HOST filesystem: if the daemon runs in
	// a container, that container must see its data root at the same absolute
	// path the host does, or every server gets an empty directory.
	if err := os.MkdirAll(cfg.Root, 0o755); err != nil {
		log.Printf("warning: could not create the data root %s: %v", cfg.Root, err)
	}

	// One panel client, shared. Enrollment adopts the long-lived token on this
	// same value, so the SFTP listener starts working the moment the node is
	// enrolled rather than at the next restart.
	client := panel.New(cfg.PanelURL, cfg.Token)

	sftpServer := startSFTP(cfg, runAs, client)
	if sftpServer != nil {
		defer sftpServer.Close()
	}

	node := api.New(cfg, drivers, version, sup, fw)
	srv := &http.Server{
		Addr:              cfg.Listen,
		Handler:           node.Handler(),
		ReadHeaderTimeout: 10 * time.Second,
		// No WriteTimeout: the console stream is a long-lived response and a
		// write deadline would cut it off mid-session.
		IdleTimeout: 120 * time.Second,
	}

	// Enrollment and heartbeats run alongside the listener rather than before
	// it. The panel is not a dependency of this daemon: a node whose panel is
	// unreachable still has to boot, still has to answer its own API, and above
	// all must keep running the game servers it already has.
	panelCtx, closePanel := context.WithCancel(context.Background())
	go link(panelCtx, cfg, drivers, node, sup, client, sftpServer)

	go func() {
		log.Printf("gamemgr-node %s listening on %s as %q", version, cfg.Listen, cfg.Name)
		for name, d := range drivers {
			ok, detail := d.Available(context.Background())
			state := "unavailable"
			if ok {
				state = "available"
			}
			log.Printf("  runtime %-9s %s (%s)", name, state, detail)
		}
		// Native servers survive the daemon: their tmux sessions were never
		// ours to lose. Saying so at boot makes an otherwise invisible property
		// obvious in the logs.
		if adopted := sup.Sessions(context.Background()); len(adopted) > 0 {
			log.Printf("re-adopted %d running native %s: %s",
				len(adopted), plural("server", len(adopted)), strings.Join(adopted, ", "))
		}

		if err := srv.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
			log.Fatalf("listen: %v", err)
		}
	}()

	stop := make(chan os.Signal, 1)
	signal.Notify(stop, os.Interrupt, syscall.SIGTERM)
	<-stop

	log.Println("shutting down")
	closePanel()
	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()
	_ = srv.Shutdown(ctx)
}

// startSFTP brings up file access, or explains why it did not.
//
// Never fatal. A node whose SFTP listener cannot start still runs every game on
// it and still answers the panel's own file manager, so this reports the problem
// and gets out of the way rather than taking the node down over a feature the
// games do not need.
func startSFTP(cfg config.Config, runAs *supervise.Credential, client *panel.Client) *nodesftp.Server {
	if cfg.SFTPListen == "" {
		log.Printf("sftp: disabled (NODE_SFTP_LISTEN is empty)")

		return nil
	}
	// Every login is checked by the panel, because this daemon holds no
	// accounts. Without a panel there is nobody to ask, and a listener that
	// could never authenticate anybody is worse than no listener: it is an open
	// port that looks like a way in.
	if cfg.PanelURL == "" {
		log.Printf("sftp: not started, because there is no NODE_PANEL_URL to check logins against")

		return nil
	}

	server, err := nodesftp.New(cfg.SFTPListen, cfg.SFTPHostKey, store.New(cfg.Root, runAs), client)
	if err != nil {
		log.Printf("sftp: not started: %v", err)

		return nil
	}

	go func() {
		log.Printf("sftp: listening on %s, host key %s", cfg.SFTPListen, server.Fingerprint())
		if err := server.Serve(); err != nil {
			log.Printf("sftp: stopped: %v", err)
		}
	}()

	return server
}

// link keeps this node's side of the panel relationship: enroll once if it has
// never been enrolled, then heartbeat for as long as the daemon runs.
//
// Nothing in here is fatal and nothing in here touches a server. Every failure
// is logged and retried, because the alternative - a daemon that exits when the
// panel is down - would take a node full of running games with it.
func link(ctx context.Context, cfg config.Config, drivers gruntime.Registry, node *api.Server, sup *supervise.Supervisor, client *panel.Client, sftpServer *nodesftp.Server) {
	if cfg.PanelURL == "" {
		log.Printf("no NODE_PANEL_URL: this node will not enroll or heartbeat, and only answers calls the panel makes to it")

		return
	}

	interval := time.Duration(cfg.HeartbeatInterval) * time.Second

	if cfg.Token == "" {
		if cfg.EnrollToken == "" {
			log.Printf("this node is not enrolled and has no enroll token: set NODE_ENROLL_TOKEN from the panel's Add Node screen, or NODE_TOKEN if you have the credential already")

			return
		}
		result := enroll(ctx, client, cfg, drivers)
		if result == nil {
			return
		}
		// The listener is already up and still holding the empty boot token, so
		// it would refuse the panel until a restart without this.
		node.SetToken(result.Token)
		if result.HeartbeatInterval > 0 {
			interval = time.Duration(result.HeartbeatInterval) * time.Second
		}
	}

	dockerClient := dockerapi.New(cfg.DockerSocket)
	sampler := panel.NewSampler(cfg.Root, version, func(ctx context.Context) int {
		return running(ctx, dockerClient, sup)
	})
	// Only claimed once the listener is really up. The panel shows a customer
	// their SFTP host and username off the back of this, so a node that could
	// not start it must not advertise it.
	if sftpServer != nil {
		sampler.ReportSFTP(true, sftpServer.Fingerprint())
	}

	panel.Heartbeat(ctx, client, interval, sampler.Sample)
}

// enroll exchanges the single-use token for the long-lived one, retrying until
// it works or the token turns out to be spent. Returns nil if the node is not
// going to become enrolled this run.
func enroll(ctx context.Context, client *panel.Client, cfg config.Config, drivers gruntime.Registry) *panel.Enrollment {
	facts := panel.Gather(ctx, cfg.DockerSocket, cfg.Root, version, availableRuntimes(ctx, drivers))

	for failures := 0; ; failures++ {
		result, err := client.Enroll(ctx, cfg.EnrollToken, facts)
		if err == nil {
			log.Printf("enrolled with %s as node %q (%s)", cfg.PanelURL, result.Node.Name, result.Node.UUID)
			persist(cfg.ConfigFile, result.Token)

			return result
		}
		if ctx.Err() != nil {
			return nil
		}
		// A single-use token that the panel refuses will be refused forever, so
		// retrying is just noise. Say clearly what has to happen instead.
		if errors.Is(err, panel.ErrUnauthorized) {
			log.Printf("ERROR: enrollment refused: %v", err)
			log.Printf("ERROR: enroll tokens are single use and expire. Issue a fresh one from the panel and restart this daemon. Servers already on this node keep running.")

			return nil
		}

		wait := panel.Jitter(panel.Backoff(10*time.Second, failures+1))
		log.Printf("enrollment attempt %d failed: %v; retrying in %s", failures+1, err, wait.Round(time.Second))
		select {
		case <-ctx.Done():
			return nil
		case <-time.After(wait):
		}
	}
}

// persist writes the new credential to the env file, and is loud when it
// cannot: an in-memory token works perfectly until the first restart, at which
// point the node quietly comes back unenrolled with a spent enroll token and no
// way to reach the panel.
func persist(path, token string) {
	if err := panel.SaveToken(path, token); err != nil {
		log.Printf("WARNING: enrolled, but the node token could not be written to %s: %v", path, err)
		log.Printf("WARNING: this node is running on an in-memory token. It will come back unenrolled after a restart, because the enroll token it still has is now spent.")
		// The token goes in the log deliberately. The panel returns it exactly
		// once and stores only its hash, so this line is the operator's single
		// remaining copy; without it the only way out is a new enroll token.
		log.Printf("WARNING: fix the permissions on %s and put this line in it: NODE_TOKEN=%s", path, token)

		return
	}
	log.Printf("node token written to %s", path)
}

func availableRuntimes(ctx context.Context, drivers gruntime.Registry) []string {
	var names []string
	for name, d := range drivers {
		if ok, _ := d.Available(ctx); ok {
			names = append(names, name)
		}
	}
	sort.Strings(names)

	return names
}

// running counts what is actually up: containers this node owns plus the
// adopted tmux sessions the native runtimes use. The two sets never overlap.
func running(ctx context.Context, client *dockerapi.Client, sup *supervise.Supervisor) int {
	count := len(sup.Sessions(ctx))
	if n, err := client.RunningServers(ctx); err == nil {
		count += n
	}

	return count
}

func plural(word string, n int) string {
	if n == 1 {
		return word
	}

	return word + "s"
}
