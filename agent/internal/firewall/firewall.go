// Package firewall keeps a node's host firewall in step with the servers that
// actually exist on it.
//
// This lives in the daemon rather than the panel because only the node knows
// whether it has a firewall at all. A node may run ufw, may run nothing and sit
// behind a cloud firewall, or may be filtered by something this daemon has
// never heard of. The panel cannot tell, and a panel that guessed would be
// wrong on half the fleet.
//
// The problem it solves is concrete. The installer used to open three fixed
// ranges once, at install time, and nothing ever opened or closed a port for a
// real server: a Valheim server allocated 2456 fell outside every one of those
// ranges and was unreachable while looking perfectly healthy, games nobody ran
// had ports open, and deleting a server left its port open forever.
//
// Two rules govern everything here:
//
//  1. Never lock the operator out. SSH, the daemon's own port, 80 and 443 are
//     refused outright, and nothing is ever deleted unless it carries this
//     daemon's own comment for the server being acted on.
//  2. Never fail a server operation because the firewall failed. Every call
//     returns a Report, never an error. A node with no ufw is a legitimate
//     configuration and an install must not abort because a rule could not be
//     written.
package firewall

import (
	"context"
	"errors"
	"fmt"
	"os"
	"os/exec"
	"regexp"
	"sort"
	"strconv"
	"strings"

	"github.com/scriptgain/gamemgr-node/internal/runtime"
	"github.com/scriptgain/gamemgr-node/internal/runtime/store"
)

// Marker prefixes every comment this daemon writes. It is the only thing that
// makes a rule removable later: ufw has no other handle on a rule, and
// deleting by rule specification is not safe here because ufw will happily
// match and remove a rule an operator wrote by hand that happens to have the
// same ports.
const Marker = "gamemgr"

// backend names the only firewall this package drives. Reported to the panel so
// a future firewalld or nftables backend can be told apart from this one.
const backend = "ufw"

// ErrNotInstalled is what a Runner returns when the ufw binary is not on the
// box. Distinct from every other failure because "no firewall here" is a
// supported configuration and an ordinary ufw error is not.
var ErrNotInstalled = errors.New("ufw is not installed on this node")

// Protocol is what a rule covers. Both is not a ufw protocol: it expands into
// one tcp rule and one udp rule.
type Protocol string

const (
	ProtoTCP  Protocol = "tcp"
	ProtoUDP  Protocol = "udp"
	ProtoBoth Protocol = "both"
)

// Runner executes one command. Behind an interface so the whole rule path can
// be exercised in tests on a box with no ufw, which includes every development
// machine this daemon is written on.
type Runner interface {
	Run(ctx context.Context, args ...string) (string, error)
}

// State is what the panel is told about this node's firewall. Present and
// Active are separate on purpose: "ufw is not installed" and "ufw is installed
// but switched off" call for different answers from an operator, and collapsing
// them into one boolean is how a node ends up silently unreachable.
type State struct {
	Backend string `json:"backend"`
	Present bool   `json:"present"`
	Active  bool   `json:"active"`
	Managed bool   `json:"managed"`
	Detail  string `json:"detail"`
}

// Port is one logical port a server needs, before it is expanded into the
// per-protocol rules ufw actually stores.
type Port struct {
	Number   int
	Protocol Protocol
	// Purpose is game, query or rcon. It only ever appears in log lines and in
	// the install stream, so an operator reading "opened 27015/udp (query)"
	// does not have to work out where that number came from.
	Purpose string
}

// Rule is a single ufw rule this daemon owns.
type Rule struct {
	Port     int
	Protocol Protocol // tcp or udp, never both
	Purpose  string
	Comment  string
}

// Spec is how ufw prints this rule in the To column of "ufw status", which is
// what makes it findable again.
func (r Rule) Spec() string { return strconv.Itoa(r.Port) + "/" + string(r.Protocol) }

// Args is the ufw command line for adding this rule.
//
// The extended "proto ... to any port ..." form is used rather than the short
// "allow 8211/udp" form because the short form does not accept a comment on
// every ufw version, and the comment is the whole mechanism for removing the
// rule later. Verified against ufw's own parser: this parses to
// "allow 8211/udp comment 'gamemgr:...'".
func (r Rule) Args() []string {
	return []string{
		"allow", "proto", string(r.Protocol),
		"to", "any", "port", strconv.Itoa(r.Port),
		// No shell is involved, so the comment is one argv element and needs no
		// quoting. ufw rejects a comment containing a single quote outright,
		// which is why Comment sanitises it.
		"comment", r.Comment,
	}
}

// Report is the outcome of one call. There is no error return anywhere in this
// package: a firewall that could not be updated is reported and logged, never
// propagated into a server operation.
type Report struct {
	State State
	// Changed is every rule actually added or removed.
	Changed []string
	// Skipped is everything deliberately not touched: rules already correct,
	// ports covered by somebody else's rule, and anything the reserved-port
	// guard refused.
	Skipped []string
	Errors  []string
}

// Summary is the one line worth putting in a log or an install stream.
func (r Report) Summary() string {
	switch {
	case !r.State.Present:
		return "ufw is not installed on this node, so no ports were changed. If something else filters this node, open the server's ports there."
	case !r.State.Active:
		return "ufw is installed but inactive, so no ports were changed."
	}

	var parts []string
	if len(r.Changed) > 0 {
		parts = append(parts, strings.Join(r.Changed, ", "))
	}
	if len(r.Skipped) > 0 {
		parts = append(parts, "unchanged: "+strings.Join(r.Skipped, ", "))
	}
	if len(r.Errors) > 0 {
		parts = append(parts, "errors: "+strings.Join(r.Errors, "; "))
	}
	if len(parts) == 0 {
		return "no firewall rules needed"
	}

	return strings.Join(parts, "; ")
}

// Manager owns this node's ufw rules.
type Manager struct {
	run Runner
	// reserved ports are never opened and never removed, whatever a payload
	// says and whatever comment a rule carries. Losing SSH on a remote box is
	// unrecoverable without console access, so this guard is unconditional
	// rather than a warning.
	reserved map[int]bool
}

// New builds a Manager driving the real ufw binary. extra is usually the port
// the daemon itself listens on, which is as fatal to lose as SSH: without it
// the panel cannot reach the node to fix anything.
func New(extra ...int) *Manager { return NewWith(&execRunner{}, extra...) }

// NewWith is New with the command execution replaced, which is what the tests
// use.
func NewWith(run Runner, extra ...int) *Manager {
	m := &Manager{run: run, reserved: map[int]bool{22: true, 80: true, 443: true}}
	for _, p := range extra {
		if p > 0 {
			m.reserved[p] = true
		}
	}

	return m
}

// Reserved reports whether a port is one this daemon refuses to manage.
func (m *Manager) Reserved(port int) bool { return m.reserved[port] }

// Comment is the marker written on every rule belonging to a server, and the
// only thing that makes that rule removable later.
//
// The short form of the uuid is used rather than the whole thing because it is
// what the rest of the daemon already names things with (the container is
// gamemgr-<short> and so is the data directory), so one identifier ties a ufw
// rule, a container and a directory together in "ufw status".
func Comment(uuid string) string {
	// Sanitised before shortening, not after, so a uuid carrying anything
	// unexpected still yields the same number of identifying characters. ufw
	// refuses a comment containing a single quote outright, and a rejected
	// comment would mean a rule that can never be found again.
	clean := strings.Map(func(r rune) rune {
		switch {
		case r >= '0' && r <= '9', r >= 'a' && r <= 'z', r >= 'A' && r <= 'Z':
			return r
		}

		return -1
	}, uuid)
	if clean == "" {
		return ""
	}

	return Marker + ":" + store.Short(clean)
}

// Rules is the exact set of ufw rules a server needs.
//
// The primary port comes from the allocation the panel sends, never from the
// template's idea of a default: a Valheim server allocated 2456 needs 2456 open
// whatever the catalogue says the game usually uses. The query and RCON ports
// are the template's offsets applied to that same allocation.
func Rules(s runtime.Server) []Rule {
	comment := Comment(s.UUID)
	if comment == "" || s.Port <= 0 {
		return nil
	}

	ports := []Port{{Number: s.Port, Protocol: protocolOf(s.DefaultProtocol), Purpose: "game"}}
	// Offset zero means the game reuses the primary port, which is already
	// covered, so it is not a second rule.
	//
	// Query and RCON deliberately open both protocols. The panel sends no
	// protocol for them (the template's rcon_protocol and query_protocol do not
	// reach the daemon), and guessing wrong is exactly the failure this package
	// exists to stop: Source query is udp while Source RCON is tcp, on ports
	// derived from the same allocation. Nothing listens on the unused half.
	if s.QueryPortOffset != 0 {
		ports = append(ports, Port{Number: s.Port + s.QueryPortOffset, Protocol: ProtoBoth, Purpose: "query"})
	}
	if s.RconPortOffset != 0 {
		ports = append(ports, Port{Number: s.Port + s.RconPortOffset, Protocol: ProtoBoth, Purpose: "rcon"})
	}

	var out []Rule
	seen := map[string]bool{}
	for _, p := range ports {
		if p.Number < 1 || p.Number > 65535 {
			continue
		}
		for _, proto := range expand(p.Protocol) {
			r := Rule{Port: p.Number, Protocol: proto, Purpose: p.Purpose, Comment: comment}
			if seen[r.Spec()] {
				continue
			}
			seen[r.Spec()] = true
			out = append(out, r)
		}
	}

	return out
}

// protocolOf reads the template's default_protocol. Anything unrecognised, and
// that includes the empty string a panel too old to send the field produces,
// means both: an unreachable server is a worse outcome than one extra rule.
func protocolOf(v string) Protocol {
	switch strings.ToLower(strings.TrimSpace(v)) {
	case "tcp":
		return ProtoTCP
	case "udp":
		return ProtoUDP
	}

	return ProtoBoth
}

func expand(p Protocol) []Protocol {
	if p == ProtoBoth {
		return []Protocol{ProtoTCP, ProtoUDP}
	}

	return []Protocol{p}
}

// ------------------------------------------------------------------ actions

// Status reports what this node's firewall looks like, for the panel.
func (m *Manager) Status(ctx context.Context) State {
	state, _, _ := m.inspect(ctx)

	return state
}

// Open makes sure every port a server needs is allowed. Safe to call on every
// start: a rule that is already present is left alone rather than added twice.
func (m *Manager) Open(ctx context.Context, s runtime.Server) Report {
	rules := Rules(s)
	state, existing, err := m.inspect(ctx)
	rep := Report{State: state}
	if err != nil {
		rep.Errors = append(rep.Errors, err.Error())

		return rep
	}
	if !state.Present || !state.Active {
		return rep
	}
	if len(rules) == 0 {
		rep.Skipped = append(rep.Skipped, "this server has no allocated port, so nothing was opened")

		return rep
	}

	for _, r := range rules {
		if m.reserved[r.Port] {
			// A server allocated one of these is a panel-side mistake, but the
			// daemon still must not write a rule it would later delete.
			rep.Skipped = append(rep.Skipped, fmt.Sprintf("refused %s: that port is reserved for ssh, the panel or this daemon", r.Spec()))
			continue
		}
		if found, ok := findSpec(existing, r.Spec()); ok {
			// Somebody else's rule already covers this exact port. Left alone
			// on purpose, including its comment: it is open, which is what the
			// server needs, and it is not ours to remove on Destroy.
			if found.Comment != r.Comment {
				rep.Skipped = append(rep.Skipped, fmt.Sprintf("%s already allowed by a rule this daemon does not own", r.Spec()))
			} else {
				rep.Skipped = append(rep.Skipped, r.Spec()+" already open")
			}
			continue
		}
		if out, err := m.run.Run(ctx, r.Args()...); err != nil {
			rep.Errors = append(rep.Errors, fmt.Sprintf("could not open %s: %v: %s", r.Spec(), err, firstLine(out)))
			continue
		}
		rep.Changed = append(rep.Changed, fmt.Sprintf("opened %s (%s)", r.Spec(), r.Purpose))
	}

	return rep
}

// Close removes every rule this daemon owns for a server.
//
// It works from the comment alone, so it does not need the allocation: a
// delete request that carries nothing but a uuid still closes the right ports,
// which matters because that is exactly the payload a server deletion sends
// after its allocation has already gone.
func (m *Manager) Close(ctx context.Context, uuid string) Report {
	comment := Comment(uuid)
	state, existing, err := m.inspect(ctx)
	rep := Report{State: state}
	if err != nil {
		rep.Errors = append(rep.Errors, err.Error())

		return rep
	}
	if !state.Present || !state.Active || comment == "" {
		return rep
	}

	victims := m.removable(existing, comment)
	// Highest index first. ufw renumbers on every delete, so removing a low
	// index would shift every rule above it and the next delete would hit the
	// wrong one, which on a box whose rule list starts with ssh is the exact
	// disaster this package is written to avoid.
	sort.Slice(victims, func(i, j int) bool { return victims[i].Index > victims[j].Index })

	for _, v := range victims {
		// Re-checked here rather than trusted from removable, because this is
		// the last line before a delete actually happens.
		if v.Comment != comment {
			rep.Errors = append(rep.Errors, fmt.Sprintf("refused to delete rule %d: it does not carry this daemon's comment", v.Index))
			continue
		}
		if out, err := m.run.Run(ctx, "--force", "delete", strconv.Itoa(v.Index)); err != nil {
			rep.Errors = append(rep.Errors, fmt.Sprintf("could not close %s: %v: %s", v.To, err, firstLine(out)))
			continue
		}
		rep.Changed = append(rep.Changed, "closed "+v.To)
	}
	if len(victims) == 0 {
		rep.Skipped = append(rep.Skipped, "no rules belonging to this server were open")
	}

	return rep
}

// ------------------------------------------------------------- status parsing

// statusRule is one row of "ufw status numbered".
type statusRule struct {
	Index   int
	To      string
	Comment string
	Raw     string
}

var numbered = regexp.MustCompile(`^\[\s*(\d+)\]\s+(.*)$`)

// inspect runs "ufw status numbered" once and returns both the node's state and
// its current rules. One call, because both callers need both halves and ufw is
// a Python program: shelling out to it twice per server operation is measurable.
func (m *Manager) inspect(ctx context.Context) (State, []statusRule, error) {
	state := State{Backend: backend}

	out, err := m.run.Run(ctx, "status", "numbered")
	if errors.Is(err, ErrNotInstalled) {
		state.Detail = "ufw is not installed; this node's ports are not managed by GameMGR"

		return state, nil, nil
	}
	if err != nil {
		state.Present = true
		state.Detail = "ufw is installed but did not answer: " + firstLine(out)
		if os.Geteuid() != 0 {
			// The systemd unit sets User=root precisely so this cannot happen.
			// If it does, the daemon has been started some other way and every
			// rule change will fail the same way.
			state.Detail += " (this daemon is not running as root, and ufw requires root)"
		}

		return state, nil, errors.New(state.Detail)
	}

	state.Present = true
	if !strings.Contains(out, "Status: active") {
		state.Detail = "ufw is installed but inactive; GameMGR is not managing ports on this node"

		return state, nil, nil
	}

	state.Active = true
	state.Managed = true
	rules := parseStatus(out)
	state.Detail = fmt.Sprintf("ufw active, %d rules, %d owned by GameMGR", len(rules), countOwned(rules))

	return state, rules, nil
}

// parseStatus reads "ufw status numbered". The format is stable across ufw 0.35
// onwards: an index in square brackets, a To column, the action, the From
// column, and an optional " # comment" tacked on the end.
func parseStatus(out string) []statusRule {
	var rules []statusRule
	for _, line := range strings.Split(out, "\n") {
		m := numbered.FindStringSubmatch(strings.TrimRight(line, "\r"))
		if m == nil {
			continue
		}
		idx, err := strconv.Atoi(m[1])
		if err != nil {
			continue
		}
		rest := m[2]
		comment := ""
		if at := strings.Index(rest, " # "); at >= 0 {
			comment = strings.TrimSpace(rest[at+3:])
			rest = rest[:at]
		}
		fields := strings.Fields(rest)
		if len(fields) == 0 {
			continue
		}
		rules = append(rules, statusRule{Index: idx, To: fields[0], Comment: comment, Raw: strings.TrimSpace(m[2])})
	}

	return rules
}

// removable is the single place that decides what may be deleted, and it is
// deliberately strict:
//
//   - the comment must be exactly this server's marker, so an operator's own
//     rules and another server's rules are both untouchable;
//   - the To column must be a plain "port" or "port/proto", so a range rule
//     that somehow carried the marker can never be removed. Nothing here ever
//     creates a range, and a range could cover ssh;
//   - the port must not be reserved.
func (m *Manager) removable(rules []statusRule, comment string) []statusRule {
	if comment == "" || !strings.HasPrefix(comment, Marker+":") {
		return nil
	}

	var out []statusRule
	for _, r := range rules {
		if r.Comment != comment {
			continue
		}
		port, ok := singlePort(r.To)
		if !ok || m.reserved[port] {
			continue
		}
		out = append(out, r)
	}

	return out
}

// singlePort parses the To column of a status row, accepting only the shapes
// this daemon creates: "8211" or "8211/udp". A range, an app profile name or an
// address form all return false, which makes them undeletable.
func singlePort(to string) (int, bool) {
	spec := to
	if slash := strings.IndexByte(spec, '/'); slash >= 0 {
		proto := spec[slash+1:]
		if proto != string(ProtoTCP) && proto != string(ProtoUDP) {
			return 0, false
		}
		spec = spec[:slash]
	}
	port, err := strconv.Atoi(spec)
	if err != nil || port < 1 || port > 65535 {
		return 0, false
	}

	return port, true
}

func findSpec(rules []statusRule, spec string) (statusRule, bool) {
	for _, r := range rules {
		if r.To == spec {
			return r, true
		}
	}

	return statusRule{}, false
}

func countOwned(rules []statusRule) int {
	n := 0
	for _, r := range rules {
		if strings.HasPrefix(r.Comment, Marker+":") {
			n++
		}
	}

	return n
}

func firstLine(s string) string {
	s = strings.TrimSpace(s)
	if i := strings.IndexByte(s, '\n'); i >= 0 {
		s = s[:i]
	}

	return strings.TrimSpace(s)
}

// ---------------------------------------------------------------- execution

type execRunner struct{}

// Run shells out to ufw. Nothing goes through a shell, so a comment or a port
// can never be interpreted as anything but one argument.
func (e *execRunner) Run(ctx context.Context, args ...string) (string, error) {
	path, err := ufwPath()
	if err != nil {
		return "", ErrNotInstalled
	}
	out, err := exec.CommandContext(ctx, path, args...).CombinedOutput()

	return string(out), err
}

// ufwPath finds ufw. LookPath alone is not enough: ufw lives in /usr/sbin,
// which is not on PATH for every way a service can be started, and this daemon
// would then decide the box has no firewall when it plainly does.
func ufwPath() (string, error) {
	if p, err := exec.LookPath("ufw"); err == nil {
		return p, nil
	}
	for _, p := range []string{"/usr/sbin/ufw", "/sbin/ufw", "/usr/bin/ufw"} {
		if st, err := os.Stat(p); err == nil && !st.IsDir() {
			return p, nil
		}
	}

	return "", ErrNotInstalled
}
