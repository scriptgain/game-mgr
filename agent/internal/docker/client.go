// Package docker is a small Docker Engine API client.
//
// Deliberately not the official SDK. The Engine API is plain HTTP over a unix
// socket and this daemon needs perhaps a dozen calls; vendoring the SDK would
// pull in a large dependency tree for that, and the GameMGR daemon ships as a
// single static binary with no runtime dependencies. Everything here is
// standard library.
package docker

import (
	"bufio"
	"bytes"
	"context"
	"encoding/binary"
	"encoding/json"
	"fmt"
	"io"
	"net"
	"net/http"
	"net/url"
	"strings"
	"time"
)

// APIVersion is pinned rather than negotiated. An unpinned client silently
// changes behaviour when the host's Docker is upgraded, which is the last thing
// you want on a box full of somebody's game servers.
const APIVersion = "v1.43"

type Client struct {
	socket string
	http   *http.Client
}

func New(socket string) *Client {
	if socket == "" {
		socket = "/var/run/docker.sock"
	}

	return &Client{
		socket: socket,
		http: &http.Client{
			Transport: &http.Transport{
				DialContext: func(ctx context.Context, _, _ string) (net.Conn, error) {
					var d net.Dialer
					return d.DialContext(ctx, "unix", socket)
				},
			},
			// No client timeout: log streaming and image pulls are both
			// long-lived, and per-call deadlines come from the context.
		},
	}
}

func (c *Client) url(path string, query url.Values) string {
	u := "http://docker/" + APIVersion + path
	if len(query) > 0 {
		u += "?" + query.Encode()
	}

	return u
}

func (c *Client) do(ctx context.Context, method, path string, query url.Values, body any) (*http.Response, error) {
	var reader io.Reader
	if body != nil {
		encoded, err := json.Marshal(body)
		if err != nil {
			return nil, err
		}
		reader = bytes.NewReader(encoded)
	}

	req, err := http.NewRequestWithContext(ctx, method, c.url(path, query), reader)
	if err != nil {
		return nil, err
	}
	if body != nil {
		req.Header.Set("Content-Type", "application/json")
	}

	res, err := c.http.Do(req)
	if err != nil {
		return nil, err
	}

	if res.StatusCode >= 400 {
		defer res.Body.Close()
		var payload struct {
			Message string `json:"message"`
		}
		raw, _ := io.ReadAll(io.LimitReader(res.Body, 8<<10))
		_ = json.Unmarshal(raw, &payload)
		message := payload.Message
		if message == "" {
			message = strings.TrimSpace(string(raw))
		}

		return nil, &APIError{Status: res.StatusCode, Message: message}
	}

	return res, nil
}

type APIError struct {
	Status  int
	Message string
}

func (e *APIError) Error() string {
	return fmt.Sprintf("docker: %d %s", e.Status, e.Message)
}

// NotFound reports whether an error is Docker's 404, which callers treat as
// "the container is simply not there" rather than a failure.
func NotFound(err error) bool {
	var apiErr *APIError
	if ok := asAPIError(err, &apiErr); ok {
		return apiErr.Status == http.StatusNotFound
	}

	return false
}

func asAPIError(err error, target **APIError) bool {
	if err == nil {
		return false
	}
	if e, ok := err.(*APIError); ok {
		*target = e

		return true
	}

	return false
}

// ------------------------------------------------------------------- health

func (c *Client) Ping(ctx context.Context) error {
	ctx, cancel := context.WithTimeout(ctx, 3*time.Second)
	defer cancel()

	res, err := c.do(ctx, http.MethodGet, "/_ping", nil, nil)
	if err != nil {
		return err
	}
	defer res.Body.Close()
	_, _ = io.Copy(io.Discard, res.Body)

	return nil
}

func (c *Client) Version(ctx context.Context) (string, error) {
	res, err := c.do(ctx, http.MethodGet, "/version", nil, nil)
	if err != nil {
		return "", err
	}
	defer res.Body.Close()

	var payload struct {
		Version string `json:"Version"`
	}
	if err := json.NewDecoder(res.Body).Decode(&payload); err != nil {
		return "", err
	}

	return payload.Version, nil
}

// RunningServers counts the containers this node has up right now. Filtered on
// the label every GameMGR container is created with, so a box that also runs
// somebody's database does not report it as a game server.
func (c *Client) RunningServers(ctx context.Context) (int, error) {
	query := url.Values{"filters": {`{"label":["io.gamemgr.server"],"status":["running"]}`}}
	res, err := c.do(ctx, http.MethodGet, "/containers/json", query, nil)
	if err != nil {
		return 0, err
	}
	defer res.Body.Close()

	var list []struct {
		ID string `json:"Id"`
	}
	if err := json.NewDecoder(res.Body).Decode(&list); err != nil {
		return 0, err
	}

	return len(list), nil
}

// -------------------------------------------------------------------- images

// PullImage streams progress to w. Docker answers with a JSON line per event,
// which is condensed here into something readable in a console.
func (c *Client) PullImage(ctx context.Context, image string, w io.Writer) error {
	query := url.Values{"fromImage": {image}}
	if !strings.Contains(image, ":") {
		query.Set("fromImage", image+":latest")
	}

	res, err := c.do(ctx, http.MethodPost, "/images/create", query, nil)
	if err != nil {
		return err
	}
	defer res.Body.Close()

	scanner := bufio.NewScanner(res.Body)
	scanner.Buffer(make([]byte, 0, 64<<10), 1<<20)
	lastStatus := ""

	for scanner.Scan() {
		var event struct {
			Status string `json:"status"`
			Error  string `json:"error"`
		}
		if err := json.Unmarshal(scanner.Bytes(), &event); err != nil {
			continue
		}
		if event.Error != "" {
			return fmt.Errorf("pull %s: %s", image, event.Error)
		}
		// Layer progress repeats the same status hundreds of times. Only the
		// transitions are worth a console line.
		if event.Status != "" && event.Status != lastStatus {
			fmt.Fprintf(w, "[docker] %s\n", event.Status)
			lastStatus = event.Status
		}
	}

	return scanner.Err()
}

func (c *Client) ImageExists(ctx context.Context, image string) bool {
	res, err := c.do(ctx, http.MethodGet, "/images/"+url.PathEscape(image)+"/json", nil, nil)
	if err != nil {
		return false
	}
	defer res.Body.Close()
	_, _ = io.Copy(io.Discard, res.Body)

	return true
}

// ---------------------------------------------------------------- containers

type ContainerConfig struct {
	Image        string              `json:"Image"`
	Cmd          []string            `json:"Cmd,omitempty"`
	Entrypoint   []string            `json:"Entrypoint,omitempty"`
	Env          []string            `json:"Env,omitempty"`
	WorkingDir   string              `json:"WorkingDir,omitempty"`
	OpenStdin    bool                `json:"OpenStdin"`
	StdinOnce    bool                `json:"StdinOnce"`
	AttachStdin  bool                `json:"AttachStdin"`
	AttachStdout bool                `json:"AttachStdout"`
	AttachStderr bool                `json:"AttachStderr"`
	Tty          bool                `json:"Tty"`
	Labels       map[string]string   `json:"Labels,omitempty"`
	ExposedPorts map[string]struct{} `json:"ExposedPorts,omitempty"`
	HostConfig   *HostConfig         `json:"HostConfig,omitempty"`
}

type HostConfig struct {
	Binds          []string                 `json:"Binds,omitempty"`
	PortBindings   map[string][]PortBinding `json:"PortBindings,omitempty"`
	Memory         int64                    `json:"Memory,omitempty"`
	MemorySwap     int64                    `json:"MemorySwap,omitempty"`
	CPUQuota       int64                    `json:"CpuQuota,omitempty"`
	CPUPeriod      int64                    `json:"CpuPeriod,omitempty"`
	CpusetCpus     string                   `json:"CpusetCpus,omitempty"`
	BlkioWeight    uint16                   `json:"BlkioWeight,omitempty"`
	OomKillDisable *bool                    `json:"OomKillDisable,omitempty"`
	RestartPolicy  struct {
		Name string `json:"Name"`
	} `json:"RestartPolicy"`
	NetworkMode string   `json:"NetworkMode,omitempty"`
	DNS         []string `json:"Dns,omitempty"`
	// Capped so a runaway server cannot fill the node's disk with logs.
	LogConfig struct {
		Type   string            `json:"Type"`
		Config map[string]string `json:"Config"`
	} `json:"LogConfig"`
}

type PortBinding struct {
	HostIP   string `json:"HostIp"`
	HostPort string `json:"HostPort"`
}

func (c *Client) CreateContainer(ctx context.Context, name string, config ContainerConfig) (string, error) {
	res, err := c.do(ctx, http.MethodPost, "/containers/create", url.Values{"name": {name}}, config)
	if err != nil {
		return "", err
	}
	defer res.Body.Close()

	var payload struct {
		ID string `json:"Id"`
	}
	if err := json.NewDecoder(res.Body).Decode(&payload); err != nil {
		return "", err
	}

	return payload.ID, nil
}

func (c *Client) StartContainer(ctx context.Context, id string) error {
	res, err := c.do(ctx, http.MethodPost, "/containers/"+id+"/start", nil, nil)
	if err != nil {
		return err
	}
	defer res.Body.Close()
	_, _ = io.Copy(io.Discard, res.Body)

	return nil
}

func (c *Client) StopContainer(ctx context.Context, id string, timeout int) error {
	query := url.Values{"t": {fmt.Sprint(timeout)}}
	res, err := c.do(ctx, http.MethodPost, "/containers/"+id+"/stop", query, nil)
	if err != nil {
		return err
	}
	defer res.Body.Close()
	_, _ = io.Copy(io.Discard, res.Body)

	return nil
}

func (c *Client) KillContainer(ctx context.Context, id string) error {
	res, err := c.do(ctx, http.MethodPost, "/containers/"+id+"/kill", nil, nil)
	if err != nil {
		return err
	}
	defer res.Body.Close()
	_, _ = io.Copy(io.Discard, res.Body)

	return nil
}

func (c *Client) RemoveContainer(ctx context.Context, id string, force bool) error {
	query := url.Values{"v": {"0"}}
	if force {
		query.Set("force", "1")
	}
	res, err := c.do(ctx, http.MethodDelete, "/containers/"+id, query, nil)
	if err != nil {
		if NotFound(err) {
			return nil
		}

		return err
	}
	defer res.Body.Close()
	_, _ = io.Copy(io.Discard, res.Body)

	return nil
}

type Inspect struct {
	ID    string `json:"Id"`
	State struct {
		Status     string `json:"Status"`
		Running    bool   `json:"Running"`
		ExitCode   int    `json:"ExitCode"`
		OOMKilled  bool   `json:"OOMKilled"`
		StartedAt  string `json:"StartedAt"`
		FinishedAt string `json:"FinishedAt"`
	} `json:"State"`
	Config struct {
		Image string `json:"Image"`
	} `json:"Config"`
}

func (c *Client) Inspect(ctx context.Context, id string) (*Inspect, error) {
	res, err := c.do(ctx, http.MethodGet, "/containers/"+id+"/json", nil, nil)
	if err != nil {
		return nil, err
	}
	defer res.Body.Close()

	var out Inspect
	if err := json.NewDecoder(res.Body).Decode(&out); err != nil {
		return nil, err
	}

	return &out, nil
}

// Wait blocks until the container exits and returns its exit code.
func (c *Client) Wait(ctx context.Context, id string) (int, error) {
	res, err := c.do(ctx, http.MethodPost, "/containers/"+id+"/wait", nil, nil)
	if err != nil {
		return 0, err
	}
	defer res.Body.Close()

	var payload struct {
		StatusCode int `json:"StatusCode"`
	}
	if err := json.NewDecoder(res.Body).Decode(&payload); err != nil {
		return 0, err
	}

	return payload.StatusCode, nil
}

// ---------------------------------------------------------------------- logs

// Logs streams container output to w until the context is cancelled.
//
// Docker multiplexes stdout and stderr into a framed stream unless the
// container was created with a TTY. demux handles the 8 byte header; without it
// every line arrives with binary rubbish on the front, which is the classic
// symptom of reading this endpoint naively.
func (c *Client) Logs(ctx context.Context, id string, tail int, follow bool, w io.Writer) error {
	query := url.Values{
		"stdout": {"1"},
		"stderr": {"1"},
		"tail":   {fmt.Sprint(tail)},
	}
	if follow {
		query.Set("follow", "1")
	}

	res, err := c.do(ctx, http.MethodGet, "/containers/"+id+"/logs", query, nil)
	if err != nil {
		return err
	}
	defer res.Body.Close()

	return demux(res.Body, w)
}

func demux(r io.Reader, w io.Writer) error {
	header := make([]byte, 8)
	for {
		if _, err := io.ReadFull(r, header); err != nil {
			if err == io.EOF || err == io.ErrUnexpectedEOF {
				return nil
			}

			return err
		}
		// A container created with a TTY sends a raw stream with no header, in
		// which case the first byte will not be a valid stream id. Fall back to
		// copying the lot rather than mangling it.
		if header[0] > 2 {
			if _, err := w.Write(header); err != nil {
				return err
			}
			if _, err := io.Copy(w, r); err != nil {
				return err
			}

			return nil
		}

		size := binary.BigEndian.Uint32(header[4:8])
		if size == 0 {
			continue
		}
		if _, err := io.CopyN(w, r, int64(size)); err != nil {
			if err == io.EOF {
				return nil
			}

			return err
		}
	}
}

// --------------------------------------------------------------------- stats

type Stats struct {
	CPUStats struct {
		CPUUsage struct {
			TotalUsage  uint64   `json:"total_usage"`
			PercpuUsage []uint64 `json:"percpu_usage"`
		} `json:"cpu_usage"`
		SystemUsage uint64 `json:"system_cpu_usage"`
		OnlineCPUs  uint32 `json:"online_cpus"`
	} `json:"cpu_stats"`
	PreCPUStats struct {
		CPUUsage struct {
			TotalUsage uint64 `json:"total_usage"`
		} `json:"cpu_usage"`
		SystemUsage uint64 `json:"system_cpu_usage"`
	} `json:"precpu_stats"`
	MemoryStats struct {
		Usage uint64            `json:"usage"`
		Limit uint64            `json:"limit"`
		Stats map[string]uint64 `json:"stats"`
	} `json:"memory_stats"`
	Networks map[string]struct {
		RxBytes uint64 `json:"rx_bytes"`
		TxBytes uint64 `json:"tx_bytes"`
	} `json:"networks"`
}

// StatsOnce takes a single non-streaming sample.
func (c *Client) StatsOnce(ctx context.Context, id string) (*Stats, error) {
	query := url.Values{"stream": {"false"}, "one-shot": {"false"}}
	res, err := c.do(ctx, http.MethodGet, "/containers/"+id+"/stats", query, nil)
	if err != nil {
		return nil, err
	}
	defer res.Body.Close()

	var out Stats
	if err := json.NewDecoder(res.Body).Decode(&out); err != nil {
		return nil, err
	}

	return &out, nil
}

// CPUPercent converts two cumulative counters into the percentage everyone
// actually wants. 100 means one full core, matching how the panel states limits.
func (s *Stats) CPUPercent() float64 {
	cpuDelta := float64(s.CPUStats.CPUUsage.TotalUsage) - float64(s.PreCPUStats.CPUUsage.TotalUsage)
	systemDelta := float64(s.CPUStats.SystemUsage) - float64(s.PreCPUStats.SystemUsage)
	if systemDelta <= 0 || cpuDelta <= 0 {
		return 0
	}

	cores := float64(s.CPUStats.OnlineCPUs)
	if cores == 0 {
		cores = float64(len(s.CPUStats.CPUUsage.PercpuUsage))
	}
	if cores == 0 {
		cores = 1
	}

	return (cpuDelta / systemDelta) * cores * 100
}

// MemoryUsedMiB excludes the page cache. Docker's raw usage figure includes it,
// which is why a server that has merely read a lot of files appears to be at
// its memory limit when it is nowhere near it.
func (s *Stats) MemoryUsedMiB() int64 {
	usage := s.MemoryStats.Usage
	if cache, ok := s.MemoryStats.Stats["inactive_file"]; ok && cache < usage {
		usage -= cache
	} else if cache, ok := s.MemoryStats.Stats["cache"]; ok && cache < usage {
		usage -= cache
	}

	return int64(usage / (1024 * 1024))
}

func (s *Stats) Network() (rx, tx int64) {
	for _, n := range s.Networks {
		rx += int64(n.RxBytes)
		tx += int64(n.TxBytes)
	}

	return rx, tx
}

// --------------------------------------------------------------------- stdin

// Attach opens the container's stdin so a console command can be written to it.
// Returns the raw hijacked connection; the caller writes the command and closes.
func (c *Client) Attach(ctx context.Context, id string) (net.Conn, error) {
	conn, err := (&net.Dialer{}).DialContext(ctx, "unix", c.socket)
	if err != nil {
		return nil, err
	}

	path := "/" + APIVersion + "/containers/" + id + "/attach?stream=1&stdin=1"
	request := "POST " + path + " HTTP/1.1\r\nHost: docker\r\nConnection: Upgrade\r\nUpgrade: tcp\r\n\r\n"
	if _, err := conn.Write([]byte(request)); err != nil {
		_ = conn.Close()

		return nil, err
	}

	// Read past the 101 response headers so the caller writes into the stream
	// itself rather than into the tail of the HTTP response.
	reader := bufio.NewReader(conn)
	for {
		line, err := reader.ReadString('\n')
		if err != nil {
			_ = conn.Close()

			return nil, err
		}
		if strings.TrimSpace(line) == "" {
			break
		}
	}

	return conn, nil
}
