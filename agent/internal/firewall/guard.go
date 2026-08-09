package firewall

import (
	"context"
	"fmt"
	"io"
	"log"
	"net"
	"strconv"

	"github.com/scriptgain/gamemgr-node/internal/runtime"
)

// Guard wraps a runtime driver so a server's ports follow the server rather
// than being opened once at install time and forgotten.
//
// It wraps the driver rather than living inside each of them because a firewall
// is a property of the node, not of Docker or SteamCMD, and three copies of
// this logic would drift.
//
// When a port is opened and closed:
//
//	Install  open, after the install succeeds
//	Start    open, before the game binds, so there is no window where the
//	         server is up and the port is shut
//	Restart  open, and never close
//	Stop     close, after the stop succeeds
//	Kill     close, after the kill succeeds
//	Destroy  close, always
//
// Restart is the reason this wraps the driver at the top rather than hooking
// Start and Stop deep inside it. Every driver implements Restart by calling its
// own Stop and Start directly, which do not pass through this wrapper, so a
// restart never drops the rule: no close-then-open churn, and no window
// mid-restart where players get connection refused. The panel's watchdog uses
// the single restart action for a crashed server, so a crash loop reopens an
// already-open rule (a no-op) instead of rewriting the ruleset every minute.
//
// A stop, in contrast, means the operator wants the server off, and a port with
// nothing behind it has no business being open: "what is exposed on this box"
// should have the same answer as "what is running on this box".
type Guard struct {
	// Embedded so every method this file does not care about (files, backups,
	// stats, logs) passes straight through and stays correct when the Driver
	// interface grows.
	runtime.Driver

	fw *Manager
}

// Wrap returns d with firewall handling attached. A nil Manager returns d
// untouched, so a node that has opted out costs nothing.
func Wrap(d runtime.Driver, fw *Manager) runtime.Driver {
	if fw == nil {
		return d
	}

	return &Guard{Driver: d, fw: fw}
}

// Unwrap exposes the driver underneath, so a caller looking for a capability
// only one driver has (fetching a Workshop item, say) can still find it through
// the wrapper. Without this the firewall Guard silently hides every interface
// it does not itself implement.
func (g *Guard) Unwrap() runtime.Driver { return g.Driver }

func (g *Guard) Install(ctx context.Context, s runtime.Server, w io.Writer) error {
	if err := g.Driver.Install(ctx, s, w); err != nil {
		return err
	}
	// After the install, not before: a failed install should not leave a rule
	// behind for a server whose files never landed.
	rep := g.fw.Open(ctx, s)
	report(rep, s, "install")
	fmt.Fprintf(w, "[gamemgr] firewall: %s\n", rep.Summary())
	for _, e := range rep.Errors {
		fmt.Fprintf(w, "[gamemgr] firewall: %s\n", e)
	}

	return nil
}

func (g *Guard) Start(ctx context.Context, s runtime.Server) error {
	report(g.fw.Open(ctx, s), s, "start")

	return g.Driver.Start(ctx, s)
}

func (g *Guard) Restart(ctx context.Context, s runtime.Server) error {
	// Opened, never closed. A restart of a stopped server would otherwise come
	// back up with its port shut, because Stop closed it.
	report(g.fw.Open(ctx, s), s, "restart")

	return g.Driver.Restart(ctx, s)
}

func (g *Guard) Stop(ctx context.Context, s runtime.Server) error {
	if err := g.Driver.Stop(ctx, s); err != nil {
		// The server may well still be running, so the port stays open.
		return err
	}
	report(g.fw.Close(ctx, s.UUID), s, "stop")

	return nil
}

func (g *Guard) Kill(ctx context.Context, s runtime.Server) error {
	if err := g.Driver.Kill(ctx, s); err != nil {
		return err
	}
	// Same intent as a stop. Treating kill differently would mean pressing Stop
	// closed the port and pressing Kill did not, which is the sort of
	// inconsistency nobody can hold in their head.
	report(g.fw.Close(ctx, s.UUID), s, "kill")

	return nil
}

func (g *Guard) Destroy(ctx context.Context, s runtime.Server) error {
	err := g.Driver.Destroy(ctx, s)
	// Closed whatever the driver said. A destroy that failed because the
	// container was already gone must still take the rule with it, otherwise
	// the port stays open forever and that is the bug this exists to fix.
	report(g.fw.Close(ctx, s.UUID), s, "destroy")

	return err
}

// report puts every firewall outcome in the journal. Errors are logged and
// dropped rather than returned: no server operation fails because a rule could
// not be written.
func report(rep Report, s runtime.Server, action string) {
	if len(rep.Changed) == 0 && len(rep.Errors) == 0 {
		return
	}
	log.Printf("firewall (%s %s): %s", action, s.UUID, rep.Summary())
}

// ListenPort pulls the port out of a listen address such as ":8942" or
// "0.0.0.0:8942", so the daemon's own port can be added to the reserved set.
// Closing it would leave the panel unable to reach the node to fix anything.
func ListenPort(listen string) int {
	_, port, err := net.SplitHostPort(listen)
	if err != nil {
		return 0
	}
	n, err := strconv.Atoi(port)
	if err != nil {
		return 0
	}

	return n
}
