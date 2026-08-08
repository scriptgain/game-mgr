package firewall

import (
	"context"
	"errors"
	"io"
	"strings"
	"testing"

	"github.com/scriptgain/gamemgr-node/internal/runtime"
)

// uuid is a real-shaped server uuid. Its short form, which is what a comment
// carries, is 6081dfbb1a2b.
const uuid = "6081dfbb-1a2b-4c3d-8e5f-0a1b2c3d4e5f"

const comment = "gamemgr:6081dfbb1a2b"

// activeStatus is "ufw status numbered" on a node that has the old installer's
// broad Minecraft range, an OpenSSH rule, the daemon's own port, and one server
// this daemon owns. The v6 duplicates are there because ufw really does list
// them separately, and forgetting that is how a delete removes half a rule.
const activeStatus = `Status: active

     To                         Action      From
     --                         ------      ----
[ 1] 22/tcp                     ALLOW IN    Anywhere                   # OpenSSH
[ 2] 8942/tcp                   ALLOW IN    Anywhere                   # the node daemon
[ 3] 8211/udp                   ALLOW IN    Anywhere                   # gamemgr:6081dfbb1a2b
[ 4] 25565:25595/tcp            ALLOW IN    Anywhere
[ 5] 27015/udp                  ALLOW IN    Anywhere                   # somebody else
[ 6] 22/tcp (v6)                ALLOW IN    Anywhere (v6)              # OpenSSH
[ 7] 8211/udp (v6)              ALLOW IN    Anywhere (v6)              # gamemgr:6081dfbb1a2b
`

// fakeUFW stands in for the ufw binary so every path here runs on a machine
// that has no firewall at all, which is every development box.
type fakeUFW struct {
	installed bool
	status    string
	failOn    string
	calls     [][]string
}

func (f *fakeUFW) Run(_ context.Context, args ...string) (string, error) {
	f.calls = append(f.calls, args)
	if !f.installed {
		return "", ErrNotInstalled
	}
	if len(args) > 0 && args[0] == "status" {
		return f.status, nil
	}
	if f.failOn != "" && strings.Contains(strings.Join(args, " "), f.failOn) {
		return "ERROR: Bad port\n", errors.New("exit status 1")
	}

	return "Rule added\n", nil
}

// changes is every call that was not a read of the status.
func (f *fakeUFW) changes() []string {
	var out []string
	for _, c := range f.calls {
		if len(c) > 0 && c[0] == "status" {
			continue
		}
		out = append(out, strings.Join(c, " "))
	}

	return out
}

func TestComment(t *testing.T) {
	tests := []struct {
		name string
		uuid string
		want string
	}{
		{"real uuid", uuid, comment},
		{"already short", "6081dfbb", "gamemgr:6081dfbb"},
		{"empty means no marker and nothing deletable", "", ""},
		{"punctuation is stripped, ufw rejects quotes in comments", "60'81;dfbb-1a2b", "gamemgr:6081dfbb1a2b"},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := Comment(tt.uuid); got != tt.want {
				t.Fatalf("Comment(%q) = %q, want %q", tt.uuid, got, tt.want)
			}
		})
	}
}

func TestRules(t *testing.T) {
	tests := []struct {
		name   string
		server runtime.Server
		want   []string
	}{
		{
			// The bug that started this: an allocation nowhere near the ranges
			// the installer used to open.
			name:   "valheim on the port it was actually allocated",
			server: runtime.Server{UUID: uuid, Port: 2456, DefaultProtocol: "udp"},
			want:   []string{"2456/udp"},
		},
		{
			name: "palworld game plus steam query",
			server: runtime.Server{
				UUID: uuid, Port: 8211, DefaultProtocol: "udp", QueryPortOffset: 18804,
			},
			want: []string{"8211/udp", "27015/tcp", "27015/udp"},
		},
		{
			name: "minecraft game plus rcon",
			server: runtime.Server{
				UUID: uuid, Port: 25565, DefaultProtocol: "tcp", RconPortOffset: 10,
			},
			want: []string{"25565/tcp", "25575/tcp", "25575/udp"},
		},
		{
			name:   "no protocol from the panel means both",
			server: runtime.Server{UUID: uuid, Port: 7777},
			want:   []string{"7777/tcp", "7777/udp"},
		},
		{
			name:   "an unrecognised protocol means both, never nothing",
			server: runtime.Server{UUID: uuid, Port: 7777, DefaultProtocol: "sctp"},
			want:   []string{"7777/tcp", "7777/udp"},
		},
		{
			name:   "zero offsets are not separate ports",
			server: runtime.Server{UUID: uuid, Port: 27015, DefaultProtocol: "udp", RconPortOffset: 0, QueryPortOffset: 0},
			want:   []string{"27015/udp"},
		},
		{
			name: "a derived port that collides with the game port is not opened twice",
			server: runtime.Server{
				UUID: uuid, Port: 27015, DefaultProtocol: "udp", QueryPortOffset: 0, RconPortOffset: 1,
			},
			want: []string{"27015/udp", "27016/tcp", "27016/udp"},
		},
		{
			name:   "an offset off the end of the port range is dropped, not wrapped",
			server: runtime.Server{UUID: uuid, Port: 65530, DefaultProtocol: "udp", RconPortOffset: 100},
			want:   []string{"65530/udp"},
		},
		{
			name:   "no allocation means no rules",
			server: runtime.Server{UUID: uuid, Port: 0},
			want:   nil,
		},
		{
			name:   "no uuid means no comment, so nothing that could be removed later",
			server: runtime.Server{Port: 8211},
			want:   nil,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got := Rules(tt.server)
			var specs []string
			for _, r := range got {
				specs = append(specs, r.Spec())
				if r.Comment != Comment(tt.server.UUID) {
					t.Fatalf("rule %s carries comment %q, want %q", r.Spec(), r.Comment, Comment(tt.server.UUID))
				}
			}
			if !equalStrings(specs, tt.want) {
				t.Fatalf("Rules() = %v, want %v", specs, tt.want)
			}
		})
	}
}

func TestRuleArgs(t *testing.T) {
	// The exact command line, because this is the string an operator will be
	// asked to compare against "ufw status" when something looks wrong.
	got := strings.Join(Rule{Port: 8211, Protocol: ProtoUDP, Comment: comment}.Args(), " ")
	want := "allow proto udp to any port 8211 comment gamemgr:6081dfbb1a2b"
	if got != want {
		t.Fatalf("Args() = %q, want %q", got, want)
	}
}

func TestParseStatus(t *testing.T) {
	rules := parseStatus(activeStatus)
	if len(rules) != 7 {
		t.Fatalf("parsed %d rules, want 7: %+v", len(rules), rules)
	}
	tests := []struct {
		index   int
		to      string
		comment string
	}{
		{1, "22/tcp", "OpenSSH"},
		{4, "25565:25595/tcp", ""},
		{5, "27015/udp", "somebody else"},
		{7, "8211/udp", comment}, // the v6 row, whose To column still parses
	}
	for _, tt := range tests {
		r := rules[tt.index-1]
		if r.Index != tt.index || r.To != tt.to || r.Comment != tt.comment {
			t.Fatalf("rule %d = %+v, want index %d to %q comment %q", tt.index, r, tt.index, tt.to, tt.comment)
		}
	}
}

func TestSinglePort(t *testing.T) {
	tests := []struct {
		to   string
		port int
		ok   bool
	}{
		{"8211/udp", 8211, true},
		{"8211/tcp", 8211, true},
		{"8211", 8211, true},
		{"25565:25595/tcp", 0, false},
		{"OpenSSH", 0, false},
		{"8211/sctp", 0, false},
		{"70000/udp", 0, false},
		{"0/udp", 0, false},
		{"", 0, false},
	}
	for _, tt := range tests {
		t.Run(tt.to, func(t *testing.T) {
			port, ok := singlePort(tt.to)
			if port != tt.port || ok != tt.ok {
				t.Fatalf("singlePort(%q) = %d, %v, want %d, %v", tt.to, port, ok, tt.port, tt.ok)
			}
		})
	}
}

// TestRemovableRefusesWhatIsNotOurs is the guard that stops this package
// closing ssh on a remote box, which is unrecoverable without console access.
func TestRemovableRefusesWhatIsNotOurs(t *testing.T) {
	m := NewWith(&fakeUFW{}, 8942)

	tests := []struct {
		name    string
		rule    statusRule
		comment string
		want    bool
	}{
		{"our own rule", statusRule{Index: 3, To: "8211/udp", Comment: comment}, comment, true},
		{"the operator's ssh rule", statusRule{Index: 1, To: "22/tcp", Comment: "OpenSSH"}, comment, false},
		{"an unrelated rule with no comment at all", statusRule{Index: 4, To: "8211/udp"}, comment, false},
		{"another server's rule", statusRule{Index: 5, To: "2456/udp", Comment: "gamemgr:aaaabbbbcccc"}, comment, false},
		{"a comment that merely starts like ours", statusRule{Index: 6, To: "2456/udp", Comment: comment + "-old"}, comment, false},
		{"ssh wearing our comment", statusRule{Index: 7, To: "22/tcp", Comment: comment}, comment, false},
		{"the daemon's own port wearing our comment", statusRule{Index: 8, To: "8942/tcp", Comment: comment}, comment, false},
		{"http wearing our comment", statusRule{Index: 9, To: "80/tcp", Comment: comment}, comment, false},
		{"https wearing our comment", statusRule{Index: 10, To: "443/tcp", Comment: comment}, comment, false},
		{"a range wearing our comment, which could cover ssh", statusRule{Index: 11, To: "20:30/tcp", Comment: comment}, comment, false},
		{"an app profile wearing our comment", statusRule{Index: 12, To: "OpenSSH", Comment: comment}, comment, false},
		{"an empty comment can never match", statusRule{Index: 13, To: "8211/udp"}, "", false},
		{"a comment without the marker can never match", statusRule{Index: 14, To: "8211/udp", Comment: "6081dfbb1a2b"}, "6081dfbb1a2b", false},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got := m.removable([]statusRule{tt.rule}, tt.comment)
			if (len(got) == 1) != tt.want {
				t.Fatalf("removable(%+v, %q) returned %d rules, want removable=%v", tt.rule, tt.comment, len(got), tt.want)
			}
		})
	}
}

func TestOpen(t *testing.T) {
	tests := []struct {
		name    string
		ufw     *fakeUFW
		server  runtime.Server
		want    []string
		present bool
		active  bool
	}{
		{
			name:   "opens exactly the ports the server needs",
			ufw:    &fakeUFW{installed: true, status: activeStatus},
			server: runtime.Server{UUID: uuid, Port: 2456, DefaultProtocol: "udp", QueryPortOffset: 1},
			want: []string{
				"allow proto udp to any port 2456 comment " + comment,
				"allow proto tcp to any port 2457 comment " + comment,
				"allow proto udp to any port 2457 comment " + comment,
			},
			present: true, active: true,
		},
		{
			// Idempotence: 8211/udp is already in activeStatus with our comment.
			name:    "a rule that already exists is not added again",
			ufw:     &fakeUFW{installed: true, status: activeStatus},
			server:  runtime.Server{UUID: uuid, Port: 8211, DefaultProtocol: "udp"},
			want:    nil,
			present: true, active: true,
		},
		{
			// 27015/udp belongs to somebody else in activeStatus. Adding ours
			// would make ufw replace their rule with ours, and a later Destroy
			// would then close a port we never opened.
			name:    "a port already allowed by a rule we do not own is left alone",
			ufw:     &fakeUFW{installed: true, status: activeStatus},
			server:  runtime.Server{UUID: uuid, Port: 27015, DefaultProtocol: "udp"},
			want:    nil,
			present: true, active: true,
		},
		{
			name:    "ssh is refused even when a server is allocated it",
			ufw:     &fakeUFW{installed: true, status: activeStatus},
			server:  runtime.Server{UUID: uuid, Port: 22, DefaultProtocol: "tcp"},
			want:    nil,
			present: true, active: true,
		},
		{
			name:    "the daemon's own port is refused",
			ufw:     &fakeUFW{installed: true, status: activeStatus},
			server:  runtime.Server{UUID: uuid, Port: 8942, DefaultProtocol: "tcp"},
			want:    nil,
			present: true, active: true,
		},
		{
			name:    "no ufw on the box changes nothing and is not an error",
			ufw:     &fakeUFW{installed: false},
			server:  runtime.Server{UUID: uuid, Port: 2456, DefaultProtocol: "udp"},
			want:    nil,
			present: false, active: false,
		},
		{
			name:    "an inactive ufw is left alone",
			ufw:     &fakeUFW{installed: true, status: "Status: inactive\n"},
			server:  runtime.Server{UUID: uuid, Port: 2456, DefaultProtocol: "udp"},
			want:    nil,
			present: true, active: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			m := NewWith(tt.ufw, 8942)
			rep := m.Open(context.Background(), tt.server)
			if !equalStrings(tt.ufw.changes(), tt.want) {
				t.Fatalf("ran %v, want %v", tt.ufw.changes(), tt.want)
			}
			if rep.State.Present != tt.present || rep.State.Active != tt.active {
				t.Fatalf("state present=%v active=%v, want present=%v active=%v",
					rep.State.Present, rep.State.Active, tt.present, tt.active)
			}
			if len(rep.Errors) > 0 {
				t.Fatalf("unexpected errors: %v", rep.Errors)
			}
		})
	}
}

func TestOpenSurvivesAFailedRule(t *testing.T) {
	ufw := &fakeUFW{installed: true, status: activeStatus, failOn: "port 2457"}
	m := NewWith(ufw, 8942)
	rep := m.Open(context.Background(), runtime.Server{UUID: uuid, Port: 2456, DefaultProtocol: "udp", QueryPortOffset: 1})

	if len(rep.Changed) != 1 {
		t.Fatalf("changed %v, want the one rule that worked", rep.Changed)
	}
	if len(rep.Errors) != 2 {
		t.Fatalf("errors %v, want one per failed rule", rep.Errors)
	}
	// The point of the whole package: this is a Report, not an error, so the
	// caller has nothing to abort a server operation with.
	if !strings.Contains(rep.Summary(), "2456/udp") {
		t.Fatalf("summary %q does not mention the rule that was written", rep.Summary())
	}
}

func TestClose(t *testing.T) {
	tests := []struct {
		name string
		ufw  *fakeUFW
		uuid string
		want []string
	}{
		{
			// Descending, because ufw renumbers after every delete and removing
			// index 3 first would shift 7 down onto an unrelated rule.
			name: "removes our rules highest index first",
			ufw:  &fakeUFW{installed: true, status: activeStatus},
			uuid: uuid,
			want: []string{"--force delete 7", "--force delete 3"},
		},
		{
			name: "a server with nothing open deletes nothing",
			ufw:  &fakeUFW{installed: true, status: activeStatus},
			uuid: "aaaabbbb-cccc-dddd-eeee-ffff00001111",
			want: nil,
		},
		{
			name: "no ufw on the box deletes nothing",
			ufw:  &fakeUFW{installed: false},
			uuid: uuid,
			want: nil,
		},
		{
			name: "an inactive ufw deletes nothing",
			ufw:  &fakeUFW{installed: true, status: "Status: inactive\n"},
			uuid: uuid,
			want: nil,
		},
		{
			name: "no uuid deletes nothing at all",
			ufw:  &fakeUFW{installed: true, status: activeStatus},
			uuid: "",
			want: nil,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			m := NewWith(tt.ufw, 8942)
			rep := m.Close(context.Background(), tt.uuid)
			if !equalStrings(tt.ufw.changes(), tt.want) {
				t.Fatalf("ran %v, want %v", tt.ufw.changes(), tt.want)
			}
			if len(rep.Errors) > 0 {
				t.Fatalf("unexpected errors: %v", rep.Errors)
			}
		})
	}
}

// TestCloseNeverTouchesTheOperatorsRules walks the whole status fixture and
// asserts that the only rules ever deleted are the two carrying our comment.
func TestCloseNeverTouchesTheOperatorsRules(t *testing.T) {
	ufw := &fakeUFW{installed: true, status: activeStatus}
	m := NewWith(ufw, 8942)
	m.Close(context.Background(), uuid)

	for _, call := range ufw.changes() {
		switch call {
		case "--force delete 7", "--force delete 3":
		default:
			t.Fatalf("deleted something that is not ours: %q", call)
		}
	}
}

// ---------------------------------------------------------------- the guard

// fakeDriver records the lifecycle calls the Guard passes through. The
// embedded interface is nil on purpose: any method this test does not expect
// the Guard to touch panics rather than passing quietly.
type fakeDriver struct {
	runtime.Driver

	calls []string
	fail  error
}

func (f *fakeDriver) note(name string) error {
	f.calls = append(f.calls, name)

	return f.fail
}

func (f *fakeDriver) Install(_ context.Context, _ runtime.Server, _ io.Writer) error {
	return f.note("install")
}
func (f *fakeDriver) Start(context.Context, runtime.Server) error   { return f.note("start") }
func (f *fakeDriver) Stop(context.Context, runtime.Server) error    { return f.note("stop") }
func (f *fakeDriver) Restart(context.Context, runtime.Server) error { return f.note("restart") }
func (f *fakeDriver) Kill(context.Context, runtime.Server) error    { return f.note("kill") }
func (f *fakeDriver) Destroy(context.Context, runtime.Server) error { return f.note("destroy") }

// guardStatus is a node whose ruleset optionally already carries this server's
// rule, on 2456 rather than the 8211 the shared fixture uses.
func guardStatus(open bool) string {
	s := `Status: active

     To                         Action      From
     --                         ------      ----
[ 1] 22/tcp                     ALLOW IN    Anywhere                   # OpenSSH
[ 2] 8942/tcp                   ALLOW IN    Anywhere
`
	if open {
		s += "[ 3] 2456/udp                   ALLOW IN    Anywhere                   # " + comment + "\n"
	}

	return s
}

func TestGuardLifecycle(t *testing.T) {
	srv := runtime.Server{UUID: uuid, Port: 2456, DefaultProtocol: "udp"}

	tests := []struct {
		name string
		act  func(runtime.Driver) error
		// open reflects whether this server's rule is already in the ruleset
		// when the action runs, which is what makes the open cases and the
		// close cases meaningful rather than both being no-ops.
		open      bool
		driverErr error
		want      []string
	}{
		{
			name: "install opens after the install succeeds",
			act:  func(d runtime.Driver) error { return d.Install(context.Background(), srv, io.Discard) },
			want: []string{"allow proto udp to any port 2456 comment " + comment},
		},
		{
			name:      "a failed install writes no rule",
			act:       func(d runtime.Driver) error { return d.Install(context.Background(), srv, io.Discard) },
			driverErr: errors.New("image pull failed"),
			want:      nil,
		},
		{
			name: "start opens",
			act:  func(d runtime.Driver) error { return d.Start(context.Background(), srv) },
			want: []string{"allow proto udp to any port 2456 comment " + comment},
		},
		{
			// The reason the Guard wraps the driver rather than living inside
			// it: a restart must not close and reopen, or a crash loop rewrites
			// the ruleset every minute and players see a shut port mid-restart.
			name: "restart opens and never closes",
			act:  func(d runtime.Driver) error { return d.Restart(context.Background(), srv) },
			want: []string{"allow proto udp to any port 2456 comment " + comment},
		},
		{
			name: "restart of a server whose rule is already there changes nothing",
			act:  func(d runtime.Driver) error { return d.Restart(context.Background(), srv) },
			open: true,
			want: nil,
		},
		{
			name: "stop closes",
			act:  func(d runtime.Driver) error { return d.Stop(context.Background(), srv) },
			open: true,
			want: []string{"--force delete 3"},
		},
		{
			name:      "a failed stop leaves the port open, because the server may still be up",
			act:       func(d runtime.Driver) error { return d.Stop(context.Background(), srv) },
			open:      true,
			driverErr: errors.New("container will not stop"),
			want:      nil,
		},
		{
			name: "kill closes, same as stop",
			act:  func(d runtime.Driver) error { return d.Kill(context.Background(), srv) },
			open: true,
			want: []string{"--force delete 3"},
		},
		{
			name: "destroy closes",
			act:  func(d runtime.Driver) error { return d.Destroy(context.Background(), srv) },
			open: true,
			want: []string{"--force delete 3"},
		},
		{
			// A destroy that failed because the container had already gone must
			// still take the rule with it, or the port stays open forever.
			name:      "destroy closes even when the driver fails",
			act:       func(d runtime.Driver) error { return d.Destroy(context.Background(), srv) },
			open:      true,
			driverErr: errors.New("no such container"),
			want:      []string{"--force delete 3"},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			ufw := &fakeUFW{installed: true, status: guardStatus(tt.open)}
			inner := &fakeDriver{fail: tt.driverErr}
			d := Wrap(inner, NewWith(ufw, 8942))

			err := tt.act(d)
			if !errors.Is(err, tt.driverErr) {
				t.Fatalf("returned %v, want %v", err, tt.driverErr)
			}
			if len(inner.calls) != 1 {
				t.Fatalf("driver calls %v, want exactly one", inner.calls)
			}
			if !equalStrings(ufw.changes(), tt.want) {
				t.Fatalf("ran %v, want %v", ufw.changes(), tt.want)
			}
		})
	}
}

// TestGuardNeverFailsAnOperation is the second hard rule: a broken firewall
// must not stop a server starting.
func TestGuardNeverFailsAnOperation(t *testing.T) {
	ufw := &fakeUFW{installed: true, status: activeStatus, failOn: "allow"}
	inner := &fakeDriver{}
	d := Wrap(inner, NewWith(ufw, 8942))

	if err := d.Start(context.Background(), runtime.Server{UUID: uuid, Port: 2456, DefaultProtocol: "udp"}); err != nil {
		t.Fatalf("start failed because of the firewall: %v", err)
	}
	if len(inner.calls) != 1 || inner.calls[0] != "start" {
		t.Fatalf("driver calls %v, want the server to have been started anyway", inner.calls)
	}
}

func TestListenPort(t *testing.T) {
	tests := []struct {
		listen string
		want   int
	}{
		{":8942", 8942},
		{"0.0.0.0:8942", 8942},
		{"[::]:8942", 8942},
		{"127.0.0.1:9000", 9000},
		{"8942", 0},
		{"", 0},
	}
	for _, tt := range tests {
		t.Run(tt.listen, func(t *testing.T) {
			if got := ListenPort(tt.listen); got != tt.want {
				t.Fatalf("ListenPort(%q) = %d, want %d", tt.listen, got, tt.want)
			}
		})
	}
}

func equalStrings(a, b []string) bool {
	if len(a) != len(b) {
		return false
	}
	for i := range a {
		if a[i] != b[i] {
			return false
		}
	}

	return true
}
