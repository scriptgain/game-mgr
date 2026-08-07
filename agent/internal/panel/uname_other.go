//go:build !linux

package panel

import "runtime"

// A node only ever runs on Linux; this exists so the package still builds on a
// developer's machine.
func unameFacts() (sysname, release, machine string) {
	return runtime.GOOS, "", runtime.GOARCH
}
