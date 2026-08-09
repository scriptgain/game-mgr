package runtime

import "testing"

// The bug this file exists for: the panel always sent `ports` and the daemon
// only ever read `port`, so every allocation past the first was reserved, shown
// in the UI, opened in the firewall, and then not published. TeamSpeak came up
// with voice working and ServerQuery and file transfer unreachable.

func TestPublishedPortsCarriesEveryAllocation(t *testing.T) {
	s := Server{
		Port: 9987,
		Ports: []AllocatedPort{
			{Port: 9987, Protocol: "udp", Roles: []string{"game"}, Primary: true},
			{Port: 10011, Protocol: "tcp", Roles: []string{"query"}},
			{Port: 30033, Protocol: "tcp", Roles: []string{"filetransfer"}},
		},
	}

	got := s.PublishedPorts()

	if len(got) != 3 {
		t.Fatalf("expected all 3 allocations to be published, got %d", len(got))
	}

	want := map[int]string{9987: "udp", 10011: "tcp", 30033: "tcp"}
	for _, p := range got {
		if want[p.Port] == "" {
			t.Errorf("published a port nobody allocated: %d", p.Port)
		}
		if protos := p.Protocols(); len(protos) != 1 || protos[0] != want[p.Port] {
			t.Errorf("port %d published as %v, want only %s", p.Port, protos, want[p.Port])
		}
	}
}

// An older panel sends no list at all. The primary is still the whole truth,
// and with no protocol stated it has to be both or a UDP game breaks.
func TestPublishedPortsFallsBackToThePrimary(t *testing.T) {
	got := Server{Port: 25565}.PublishedPorts()

	if len(got) != 1 || got[0].Port != 25565 {
		t.Fatalf("expected just the primary, got %+v", got)
	}

	if protos := got[0].Protocols(); len(protos) != 2 {
		t.Errorf("an unstated protocol must open both, got %v", protos)
	}
}

// Mumble genuinely needs one number on each protocol: TCP carries control and
// text, UDP carries voice. Opening one connects a server nobody can hear.
func TestBothMeansBoth(t *testing.T) {
	s := Server{Port: 64738, Ports: []AllocatedPort{{Port: 64738, Protocol: "both", Primary: true}}}

	protos := s.PublishedPorts()[0].Protocols()

	if len(protos) != 2 {
		t.Fatalf("both must expand to tcp and udp, got %v", protos)
	}
}

// The primary is never lost, even if it is missing from the list, because it is
// what SERVER_PORT and every address in the panel say.
func TestThePrimaryIsNeverDropped(t *testing.T) {
	s := Server{Port: 8211, Ports: []AllocatedPort{{Port: 27015, Protocol: "udp"}}}

	got := s.PublishedPorts()

	if len(got) != 2 || got[0].Port != 8211 {
		t.Fatalf("the primary must lead the list, got %+v", got)
	}
}

func TestNonsensePortsAreDropped(t *testing.T) {
	s := Server{Port: 25565, Ports: []AllocatedPort{
		{Port: 25565, Primary: true},
		{Port: 0},
		{Port: 70000},
		{Port: 25565}, // a duplicate would publish the same binding twice
	}}

	if got := s.PublishedPorts(); len(got) != 1 {
		t.Fatalf("expected only the one real port, got %+v", got)
	}
}
