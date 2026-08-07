#!/usr/bin/env bash
#
# Cross-compile the GameMGR node daemon into dist/.
#
# A fresh VM cannot build Go, and should not have to: the daemon is one static
# binary with no runtime dependencies. Build it here, publish dist/ somewhere
# the node can fetch over HTTP(S), and hand the installer the URL plus the
# checksum this script prints:
#
#   ./deploy/build-node.sh
#   sudo bash install-node.sh --panel https://panel.example --token <token> \
#        --binary https://files.example/gamemgr-node-linux-amd64 \
#        --sha256 <hex from dist/gamemgr-node-linux-amd64.sha256>
#
# CGO is off on purpose. A cgo build links against the build host's glibc and
# then refuses to start on anything older, which is exactly the box someone is
# most likely to install a node on.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE_DIR="${REPO_ROOT}/agent"
DIST_DIR="${REPO_ROOT}/dist"

GOOS_LIST="${GOOS_LIST:-linux}"
GOARCH_LIST="${GOARCH_LIST:-amd64}"

usage() {
    cat <<'USAGE'
Build the GameMGR node daemon.

Usage: build-node.sh [options]

  --arch <list>   Comma separated GOARCH values. Default: amd64
                  arm64 builds fine, but SteamCMD and LinuxGSM do not run on
                  it, so an arm64 node is Docker-runtime only.
  --out <dir>     Output directory. Default: <repo>/dist
  -h, --help      This.
USAGE
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --arch) GOARCH_LIST="${2:-}"; shift 2 ;;
        --out)  DIST_DIR="${2:-}"; shift 2 ;;
        -h|--help) usage; exit 0 ;;
        *) printf 'Unknown option: %s (try --help)\n' "$1" >&2; exit 1 ;;
    esac
done

command -v go >/dev/null 2>&1 || {
    printf 'ERROR  No Go toolchain on PATH. Install Go and re-run.\n' >&2
    exit 1
}
[[ -f "${SOURCE_DIR}/go.mod" ]] || {
    printf 'ERROR  No go.mod at %s. Run this from inside the GameMGR repo.\n' "$SOURCE_DIR" >&2
    exit 1
}

VERSION="$(cat "${REPO_ROOT}/VERSION" 2>/dev/null || echo dev)"
VERSION="${VERSION//[$'\t\r\n ']/}"

mkdir -p "$DIST_DIR"

printf '==> building gamemgr-node %s with %s\n' "$VERSION" "$(go version)"

IFS=',' read -r -a arches <<< "$GOARCH_LIST"
IFS=',' read -r -a oses <<< "$GOOS_LIST"

for goos in "${oses[@]}"; do
    for goarch in "${arches[@]}"; do
        goos="${goos// /}"; goarch="${goarch// /}"
        [[ -n "$goos" && -n "$goarch" ]] || continue

        out="${DIST_DIR}/gamemgr-node-${goos}-${goarch}"

        # -trimpath keeps build host paths out of the binary, so the same source
        # gives the same bytes anywhere. -s -w drops the symbol table and DWARF,
        # which is a third of the size for something never debugged in place.
        ( cd "$SOURCE_DIR" && CGO_ENABLED=0 GOOS="$goos" GOARCH="$goarch" \
            go build -trimpath -ldflags "-s -w" -o "$out" ./cmd/node )

        chmod 0755 "$out"
        ( cd "$DIST_DIR" && sha256sum "$(basename "$out")" > "$(basename "$out").sha256" )

        sum="$(awk '{print $1}' "${out}.sha256")"
        size="$(du -h "$out" | awk '{print $1}')"
        printf '    %s  %s  %s\n' "$(basename "$out")" "$size" "$sum"
    done
done

cat <<EOF

Built into ${DIST_DIR}

Install on a node with:

    sudo bash install-node.sh --panel <panel url> --token <enrol token> \\
         --binary <url or path to the binary> \\
         --sha256 <the hex above>

The installer also picks the checksum up automatically if you publish the
.sha256 file next to the binary at the same URL.
EOF
