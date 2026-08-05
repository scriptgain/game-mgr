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
	"strings"
	"syscall"
	"time"

	"github.com/scriptgain/gamemgr-node/internal/api"
	"github.com/scriptgain/gamemgr-node/internal/config"
	gruntime "github.com/scriptgain/gamemgr-node/internal/runtime"
	"github.com/scriptgain/gamemgr-node/internal/runtime/docker"
	"github.com/scriptgain/gamemgr-node/internal/runtime/linuxgsm"
	"github.com/scriptgain/gamemgr-node/internal/runtime/steamcmd"
	"github.com/scriptgain/gamemgr-node/internal/runtime/stub"
	"github.com/scriptgain/gamemgr-node/internal/supervise"
)

const version = "0.1.0"

func main() {
	log.SetFlags(log.LstdFlags | log.LUTC)
	cfg := config.Load()

	// One supervisor shared by both native runtimes, so "how is a native server
	// run" has a single answer and the CPU sampling state lives in one place.
	sup := supervise.New()

	drivers := gruntime.Registry{
		"docker":   docker.New(cfg.DockerSocket, cfg.Root),
		"steamcmd": steamcmd.New("", cfg.Root, sup),
		"linuxgsm": linuxgsm.New(cfg.Root, sup),
	}
	// The stub is only registered when asked for by name. Registering it
	// unconditionally would make a misconfigured production node quietly serve
	// fake data instead of failing loudly, which is far worse than an error.
	if cfg.Driver == "stub" {
		drivers["stub"] = stub.New()
		log.Printf("stub driver active: this node reports synthetic data and runs nothing")
	}

	// The data root has to be a path that means the same thing to this process
	// and to the Docker daemon. Containers are created by the host daemon, so a
	// bind mount is resolved against the HOST filesystem: if the daemon runs in
	// a container, that container must see its data root at the same absolute
	// path the host does, or every server gets an empty directory.
	if err := os.MkdirAll(cfg.Root, 0o755); err != nil {
		log.Printf("warning: could not create the data root %s: %v", cfg.Root, err)
	}

	srv := &http.Server{
		Addr:              cfg.Listen,
		Handler:           api.New(cfg, drivers, version, sup).Handler(),
		ReadHeaderTimeout: 10 * time.Second,
		// No WriteTimeout: the console stream is a long-lived response and a
		// write deadline would cut it off mid-session.
		IdleTimeout: 120 * time.Second,
	}

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
		if adopted := supervise.Sessions(context.Background()); len(adopted) > 0 {
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
	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()
	_ = srv.Shutdown(ctx)
}

func plural(word string, n int) string {
	if n == 1 {
		return word
	}

	return word + "s"
}
