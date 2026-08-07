//go:build linux

package panel

import (
	"runtime"
	"syscall"
)

// unameFacts asks the kernel directly rather than shelling out to uname(1),
// which a minimal container image may not ship at all.
func unameFacts() (sysname, release, machine string) {
	var u syscall.Utsname
	if err := syscall.Uname(&u); err != nil {
		return runtime.GOOS, "", runtime.GOARCH
	}

	return utsString(u.Sysname[:]), utsString(u.Release[:]), utsString(u.Machine[:])
}

// The utsname fields are C char arrays, and C's char is signed on x86 but
// unsigned on ARM, so syscall.Utsname is [65]int8 on one and [65]uint8 on the
// other. A generic conversion is what keeps this building for both, which
// matters because arm64 nodes are the cheap ones people actually buy.
func utsString[T int8 | uint8](in []T) string {
	out := make([]byte, 0, len(in))
	for _, c := range in {
		if c == 0 {
			break
		}
		out = append(out, byte(c))
	}

	return string(out)
}
