package panel

import (
	"context"
	"encoding/json"
	"errors"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
)

// The whole of enrollment is one request whose answer the node can never ask for
// again: enroll tokens are single use, so a client that mishandles the response
// leaves a node that can never be enrolled without an operator issuing a new
// token. These pin down what it sends and how it reads each answer back.
func TestEnroll(t *testing.T) {
	facts := Facts{
		OS: "Ubuntu 24.04.1 LTS", Kernel: "6.8.0-45-generic", Arch: "x86_64",
		Docker: "27.3.1", AgentVersion: "0.1.0", CPUCores: 8,
		Memory: 33_285_996_544, Disk: 1_006_632_960_000,
		Runtimes: []string{"docker", "linuxgsm"},
	}

	cases := []struct {
		name       string
		status     int
		body       string
		wantErr    bool
		wantAuthzd bool
		wantToken  string
	}{
		{
			name:       "the panel issues the long lived token",
			status:     http.StatusOK,
			body:       `{"node":{"uuid":"9d1f-uuid","name":"lon-01"},"token":"long-lived-secret","panel":"https://panel.example","heartbeat_interval":45}`,
			wantAuthzd: true,
			wantToken:  "long-lived-secret",
		},
		{
			// A token that was already spent or has expired. This must be
			// distinguishable, because the caller retries everything else
			// forever and retrying this one would be pure noise.
			name:    "a spent or expired token is unauthorized",
			status:  http.StatusUnauthorized,
			body:    `{"message":"That enrollment token is not valid or has expired."}`,
			wantErr: true,
		},
		{
			name:    "a panel that is up but broken is not an auth failure",
			status:  http.StatusInternalServerError,
			body:    `{"message":"Server Error"}`,
			wantErr: true,
		},
		{
			name:    "a 200 with no token is refused rather than adopted",
			status:  http.StatusOK,
			body:    `{"node":{"uuid":"9d1f-uuid","name":"lon-01"}}`,
			wantErr: true,
		},
	}

	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			var got map[string]any
			var gotPath, gotAuth string

			srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
				gotPath = r.URL.Path
				gotAuth = r.Header.Get("Authorization")
				_ = json.NewDecoder(r.Body).Decode(&got)
				w.Header().Set("Content-Type", "application/json")
				w.WriteHeader(tc.status)
				_, _ = w.Write([]byte(tc.body))
			}))
			defer srv.Close()

			client := New(srv.URL+"/", "")
			result, err := client.Enroll(context.Background(), "single-use-token", facts)

			if tc.wantErr {
				if err == nil {
					t.Fatalf("Enroll succeeded, want an error")
				}
				if want := tc.status == http.StatusUnauthorized; errors.Is(err, ErrUnauthorized) != want {
					t.Fatalf("errors.Is(err, ErrUnauthorized) = %v, want %v (err: %v)", !want, want, err)
				}
				// A failed enrollment must not leave the client holding
				// something it will then send as a bearer token.
				if client.Token() != "" {
					t.Fatalf("client adopted %q from a failed enrollment", client.Token())
				}

				return
			}

			if err != nil {
				t.Fatalf("Enroll: %v", err)
			}
			if gotPath != "/api/node/enroll" {
				t.Fatalf("posted to %s, want /api/node/enroll", gotPath)
			}
			// Enrollment is the one call with no credential to present: the
			// token goes in the body precisely because there is no bearer yet.
			if gotAuth != "" {
				t.Fatalf("sent Authorization %q on enroll, want none", gotAuth)
			}
			if result.Token != tc.wantToken || client.Token() != tc.wantToken {
				t.Fatalf("token = %q / client %q, want %q", result.Token, client.Token(), tc.wantToken)
			}
			if result.Node.UUID != "9d1f-uuid" || result.HeartbeatInterval != 45 {
				t.Fatalf("decoded %+v, want the uuid and interval from the body", result)
			}

			// Field names the panel validates. A rename on either side turns
			// into a silently empty column on the node's Overview tab.
			want := map[string]any{
				"token": "single-use-token", "os": facts.OS, "kernel": facts.Kernel,
				"arch": facts.Arch, "docker": facts.Docker, "agent_version": facts.AgentVersion,
				"cpu_cores": float64(facts.CPUCores), "memory": float64(facts.Memory),
				"disk": float64(facts.Disk),
			}
			for key, value := range want {
				if got[key] != value {
					t.Errorf("body[%q] = %#v, want %#v", key, got[key], value)
				}
			}
			runtimes, ok := got["runtimes"].([]any)
			if !ok || len(runtimes) != 2 || runtimes[0] != "docker" {
				t.Errorf("body[\"runtimes\"] = %#v, want the two available driver names", got["runtimes"])
			}
		})
	}
}

func TestHeartbeatSendsBearerAndPanelFieldNames(t *testing.T) {
	var got map[string]any
	var gotAuth, gotPath string

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		gotPath, gotAuth = r.URL.Path, r.Header.Get("Authorization")
		_ = json.NewDecoder(r.Body).Decode(&got)
		_, _ = w.Write([]byte(`{"ok":true}`))
	}))
	defer srv.Close()

	client := New(srv.URL, "long-lived-secret")
	sample := Metrics{CPU: 12.5, Memory: 2048, Disk: 4096, Load: 0.75, Running: 3, AgentVersion: "0.1.0"}
	if err := client.Heartbeat(context.Background(), sample); err != nil {
		t.Fatalf("Heartbeat: %v", err)
	}

	if gotPath != "/api/node/heartbeat" {
		t.Fatalf("posted to %s, want /api/node/heartbeat", gotPath)
	}
	// The panel's agent.auth middleware reads a plain bearer token and nothing
	// else, so this exact shape is load bearing.
	if gotAuth != "Bearer long-lived-secret" {
		t.Fatalf("Authorization = %q, want %q", gotAuth, "Bearer long-lived-secret")
	}
	for key, value := range map[string]any{
		"cpu": 12.5, "memory": float64(2048), "disk": float64(4096),
		"load": 0.75, "running": float64(3), "agent_version": "0.1.0",
	} {
		if got[key] != value {
			t.Errorf("body[%q] = %#v, want %#v", key, got[key], value)
		}
	}
}

func TestHeartbeatUnauthorizedIsRecognisable(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusUnauthorized)
		_, _ = w.Write([]byte(`{"message":"Invalid node token."}`))
	}))
	defer srv.Close()

	err := New(srv.URL, "revoked").Heartbeat(context.Background(), Metrics{})
	if !errors.Is(err, ErrUnauthorized) {
		t.Fatalf("err = %v, want ErrUnauthorized", err)
	}
	if !strings.Contains(err.Error(), "Invalid node token.") {
		t.Fatalf("err = %v, want the panel's message carried through", err)
	}
}

// Enrollment fails with a 422 and no explanation if a fact is longer than the
// panel's column, so the lengths are capped at the source.
func TestGatherStaysWithinThePanelValidation(t *testing.T) {
	facts := Gather(context.Background(), "/nonexistent/docker.sock", t.TempDir(), strings.Repeat("v", 80), []string{"stub"})

	for _, tc := range []struct {
		field string
		value string
		max   int
	}{
		{"os", facts.OS, 120},
		{"kernel", facts.Kernel, 120},
		{"arch", facts.Arch, 32},
		{"docker", facts.Docker, 64},
		{"agent_version", facts.AgentVersion, 32},
	} {
		if len(tc.value) > tc.max {
			t.Errorf("%s is %d chars (%q), the panel accepts %d", tc.field, len(tc.value), tc.value, tc.max)
		}
	}
	if facts.CPUCores < 1 {
		t.Errorf("cpu_cores = %d, want at least 1", facts.CPUCores)
	}
	// An unreachable Docker socket is normal on a SteamCMD-only node and must
	// leave a blank rather than failing the whole gather.
	if facts.Docker != "" {
		t.Errorf("docker = %q, want empty when the socket does not exist", facts.Docker)
	}
}
