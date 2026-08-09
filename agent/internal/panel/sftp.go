package panel

import (
	"context"
	"errors"
	"fmt"
	"strings"
)

// ErrDenied is the panel refusing an SFTP login: no such account, wrong
// password, a suspended user or server, or an account without permission to
// reach this server's files.
//
// Deliberately one error for all of those. The daemon turns it into a single
// "permission denied" for the client, because telling somebody at the other end
// of an SSH connection which of those it was is telling them whether an account
// exists and which servers it can see.
var ErrDenied = errors.New("panel refused the credential")

// SFTPGrant is what the panel says a login is allowed to do. The daemon holds
// no accounts and no permissions of its own; this is the whole of its
// authority for the life of one connection.
type SFTPGrant struct {
	// Granted is the answer. A refusal is a 200 with this false rather than a
	// 4xx, because the daemon's own bearer token is checked by the same
	// endpoint: if a refused password and a rejected node token both came back
	// as 401, a node whose credential had gone stale would report every login
	// as a wrong password and nobody would look at the node.
	Granted bool `json:"granted"`
	// ServerUUID identifies the one directory this connection may touch. Any
	// path outside it is refused by the daemon regardless of what is here.
	ServerUUID string `json:"server_uuid"`
	Runtime    string `json:"runtime"`
	// Permissions are the same strings the panel uses everywhere else:
	// file.read, file.create, file.update, file.delete.
	Permissions []string `json:"permissions"`
	Username    string   `json:"username"`
	// The server's disk limit in MiB. Zero means unlimited.
	DiskMiB int64 `json:"disk_mib"`
}

// Can reports whether the grant carries a permission.
func (g *SFTPGrant) Can(permission string) bool {
	if g == nil {
		return false
	}
	for _, held := range g.Permissions {
		if held == permission {
			return true
		}
	}

	return false
}

// AuthenticateSFTP asks the panel whether a username and password may open an
// SFTP session, and for which server.
//
// Every login is a fresh call. Caching the answer would mean a password change,
// a revoked subuser or a suspended server took effect only after some interval,
// and "I removed their access an hour ago" has to be true the moment it is said.
// The cost is one HTTP call per connection, not per file operation.
func (c *Client) AuthenticateSFTP(ctx context.Context, username, password, ip string) (*SFTPGrant, error) {
	if strings.TrimSpace(username) == "" || password == "" {
		return nil, ErrDenied
	}

	body := struct {
		Username string `json:"username"`
		Password string `json:"password"`
		IP       string `json:"ip"`
	}{Username: username, Password: password, IP: ip}

	var out SFTPGrant
	if err := c.post(ctx, "/api/node/sftp/authenticate", body, &out); err != nil {
		// Anything non-2xx here is the panel being unreachable, broken, or
		// refusing this node's own credential. None of those are the user's
		// doing and the daemon logs them differently.
		return nil, err
	}
	if !out.Granted {
		return nil, ErrDenied
	}
	if out.ServerUUID == "" {
		return nil, fmt.Errorf("panel granted the login but named no server")
	}

	return &out, nil
}
