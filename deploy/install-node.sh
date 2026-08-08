#!/usr/bin/env bash
#
# GameMGR node installer.
#
# Turns a fresh Ubuntu 22.04/24.04 or Debian 12 box into a GameMGR node: Docker,
# SteamCMD, LinuxGSM's dependencies, the daemon binary, a systemd unit, and the
# firewall holes the thing actually needs to be reachable.
#
# The panel serves this file at GET /install/node with its own URL substituted
# as the default --panel, which is where the one-liner on the Enroll screen comes
# from:
#
#   curl -fsSL https://panel.example/install/node | sudo bash -s -- \
#       --panel https://panel.example --token <enroll token>
#
# Re-running is safe. It will not clobber the token of a node that is already
# enrolled and working; pass --reenroll if that is genuinely what you want.

set -euo pipefail

# ----------------------------------------------------------------- defaults

# The panel substitutes its own base URL into the default below when it serves
# this script. Left empty in the repo copy, which is why --panel is required
# when the script is run straight out of a git checkout.
PANEL="${PANEL:-}"

TOKEN=""
NODE_ROOT="/var/lib/gamemgr/volumes"
PORT="8942"
NODE_NAME=""
BINARY=""
BINARY_SHA256=""
SOURCE_DIR=""
REENROLL="no"
TOUCH_FIREWALL="yes"
# Broad game port ranges, off by default now that the daemon opens the exact
# port each server is allocated and closes it again when the server is deleted.
#
# This used to default to 8211:8226,25565:25595,27000:27050, which was wrong in
# both directions: a server allocated 2456 was unreachable because it fell
# outside every range, while ports for games nobody was running stayed open.
# --game-ports brings the old behaviour back for anyone who wants it.
GAME_PORTS="${GAME_PORTS:-}"

CONFIG_DIR="/etc/gamemgr-node"
CONFIG_FILE="${CONFIG_DIR}/node.env"
UNIT_FILE="/etc/systemd/system/gamemgr-node.service"
BIN_PATH="/usr/local/bin/gamemgr-node"
STEAMCMD_DIR="/opt/steamcmd"
STEAMCMD_URL="https://steamcdn-a.akamaihd.net/client/installer/steamcmd_linux.tar.gz"
SERVICE_USER="gamemgr"

WARNINGS=()
FIREWALL_NOTES=()

# ------------------------------------------------------------------ output

log()  { printf '\033[0;36m==>\033[0m %s\n' "$*"; }
ok()   { printf '\033[0;32m  ok\033[0m %s\n' "$*"; }
warn() { printf '\033[0;33m  !!\033[0m %s\n' "$*" >&2; WARNINGS+=("$*"); }
die()  { printf '\033[0;31mERROR\033[0m %s\n' "$*" >&2; exit 1; }

usage() {
    cat <<'USAGE'
GameMGR node installer.

Usage: install-node.sh [options]

  --panel <url>       Panel base URL, e.g. https://panel.example or
                      http://203.0.113.10 . Required unless this script was
                      served by the panel itself.
  --token <token>     Single-use enroll token from the panel's Enroll screen.
  --root <path>       Where server data lives. Default /var/lib/gamemgr/volumes
  --port <port>       Port the daemon listens on. Default 8942
  --name <name>       Name this node reports to the panel. Default: hostname
  --binary <url|path> Prebuilt gamemgr-node binary to install. A fresh VM has
                      no Go toolchain, so this is the normal path.
  --sha256 <hex>      Expected sha256 of that binary. Verified before install.
  --source <dir>      Build from this source tree instead (needs Go installed).
  --reenroll          Discard an existing node token and enroll again.
                      --reenrol, the old British spelling, still works.
  --game-ports <list> Extra game port ranges to open, comma separated, ufw
                      syntax, for example 25565:25595,27000:27050. Empty by
                      default. The daemon now opens the exact port each server
                      is allocated when it is installed or started, and closes
                      it again when the server is deleted, so blanket ranges
                      are no longer needed: they left ports open for games
                      nobody was running and still missed any allocation that
                      fell outside them. Use this only if something else on the
                      box needs a range open.
  --no-firewall       Do not touch ufw at all.
  -h, --help          This.
USAGE
}

# ------------------------------------------------------------------- flags

while [[ $# -gt 0 ]]; do
    case "$1" in
        --panel)       PANEL="${2:-}"; shift 2 ;;
        --token)       TOKEN="${2:-}"; shift 2 ;;
        --root)        NODE_ROOT="${2:-}"; shift 2 ;;
        --port)        PORT="${2:-}"; shift 2 ;;
        --name)        NODE_NAME="${2:-}"; shift 2 ;;
        --binary)      BINARY="${2:-}"; shift 2 ;;
        --sha256)      BINARY_SHA256="${2:-}"; shift 2 ;;
        --source)      SOURCE_DIR="${2:-}"; shift 2 ;;
        --game-ports)  GAME_PORTS="${2:-}"; shift 2 ;;
        # --reenrol is the pre-rename spelling, accepted silently so a command
        # copy-pasted out of an older runbook or support ticket still works.
        # Can go once nobody is running instructions written before the rename.
        --reenroll|--reenrol) REENROLL="yes"; shift ;;
        --no-firewall) TOUCH_FIREWALL="no"; shift ;;
        -h|--help)     usage; exit 0 ;;
        *)             die "Unknown option: $1 (try --help)" ;;
    esac
done

# ------------------------------------------------------------ sanity checks

[[ $EUID -eq 0 ]] || die "Run this as root: sudo bash install-node.sh ..."

PANEL="${PANEL%/}"
[[ -n "$PANEL" ]] || die "No panel URL. Pass --panel https://your-panel"
[[ "$PANEL" =~ ^https?:// ]] || die "The panel URL must start with http:// or https:// (got: $PANEL)"
if [[ ! "$PORT" =~ ^[0-9]+$ ]] || (( PORT < 1 || PORT > 65535 )); then
    die "--port must be a port number (got: $PORT)"
fi
[[ "$NODE_ROOT" == /* ]] || die "--root must be an absolute path (got: $NODE_ROOT)"

if [[ -z "$NODE_NAME" ]]; then
    NODE_NAME="$(hostname -f 2>/dev/null || hostname)"
fi

ARCH="$(uname -m)"
if [[ "$ARCH" != "x86_64" ]]; then
    # SteamCMD ships a 32-bit x86 binary and there is no arm build. The daemon
    # itself is fine on arm64, but the SteamCMD and LinuxGSM runtimes are not.
    warn "This box is ${ARCH}, not x86_64. SteamCMD and LinuxGSM will not run here; only the Docker runtime will work."
fi

OS_ID=""; OS_VERSION=""; OS_PRETTY="unknown"
if [[ -r /etc/os-release ]]; then
    # shellcheck disable=SC1091
    . /etc/os-release
    OS_ID="${ID:-}"; OS_VERSION="${VERSION_ID:-}"; OS_PRETTY="${PRETTY_NAME:-$OS_ID $OS_VERSION}"
fi
case "${OS_ID}:${OS_VERSION}" in
    ubuntu:22.04|ubuntu:24.04|debian:12) ;;
    *) warn "Tested on Ubuntu 22.04/24.04 and Debian 12. This is ${OS_PRETTY}; carrying on, but package names may differ." ;;
esac

command -v apt-get >/dev/null 2>&1 || die "No apt-get. This installer only handles Debian and Ubuntu."
command -v systemctl >/dev/null 2>&1 || die "No systemd. The node daemon is installed as a systemd unit."

log "GameMGR node installer"
printf '    panel   %s\n' "$PANEL"
printf '    node    %s (port %s)\n' "$NODE_NAME" "$PORT"
printf '    root    %s\n' "$NODE_ROOT"
printf '    os      %s (%s)\n' "$OS_PRETTY" "$ARCH"

export DEBIAN_FRONTEND=noninteractive

# ------------------------------------------------------------ 1. packages
#
# This list is the runtime stage of agent/Dockerfile, verbatim, and it is that
# list for a reason: SteamCMD is a 32-bit glibc binary, LinuxGSM is a pile of
# bash that shells out to file/bc/jq/tmux and explicitly does not support musl.

log "Installing packages"

if ! dpkg --print-foreign-architectures | grep -qx i386; then
    dpkg --add-architecture i386
    ok "added the i386 architecture (SteamCMD is 32-bit)"
fi

apt-get update -qq

APT_PACKAGES=(
    ca-certificates
    tmux
    bash
    curl
    wget
    tar
    xz-utils
    file
    bsdmainutils
    distro-info-data
    lsb-release
    procps
    netcat-openbsd
    gzip
    bzip2
    unzip
    binutils
    bc
    jq
    python3
    lib32gcc-s1
    lib32stdc++6
)

if ! apt-get install -y -qq --no-install-recommends "${APT_PACKAGES[@]}"; then
    die "Package install failed. Fix apt (check 'apt-get update' output) and re-run."
fi
ok "${#APT_PACKAGES[@]} packages present, including tmux, lib32gcc-s1 and lib32stdc++6"

# ------------------------------------------------------------- 2. docker

log "Docker engine"

if command -v docker >/dev/null 2>&1; then
    ok "already installed: $(docker --version 2>/dev/null || echo 'version unknown')"
else
    log "  fetching get.docker.com"
    tmp_docker="$(mktemp)"
    if curl -fsSL https://get.docker.com -o "$tmp_docker"; then
        sh "$tmp_docker" >/dev/null 2>&1 || die "The Docker install script failed. Run 'sh $tmp_docker' by hand to see why."
        rm -f "$tmp_docker"
        ok "installed: $(docker --version 2>/dev/null || echo 'version unknown')"
    else
        rm -f "$tmp_docker"
        die "Could not download get.docker.com. Check outbound HTTPS and DNS on this box."
    fi
fi

systemctl enable --now docker >/dev/null 2>&1 || warn "Could not enable the docker service. The Docker runtime will report unavailable until it starts."
if docker info >/dev/null 2>&1; then
    ok "docker daemon is responding"
else
    warn "The docker daemon is not responding to 'docker info'. Containerised templates will not run until it does."
fi

# ------------------------------------------------------------ 3. steamcmd
#
# From Valve, not from Debian's non-free package: that one wants an interactive
# licence prompt, which has no place in an unattended install.

log "SteamCMD"

if [[ -x "${STEAMCMD_DIR}/steamcmd.sh" ]]; then
    ok "already present at ${STEAMCMD_DIR}"
else
    mkdir -p "$STEAMCMD_DIR"
    if curl -fsSL "$STEAMCMD_URL" | tar -xz -C "$STEAMCMD_DIR"; then
        ok "unpacked into ${STEAMCMD_DIR}"
    else
        die "Could not fetch SteamCMD from ${STEAMCMD_URL}. Check outbound HTTPS and re-run."
    fi
fi

printf '#!/bin/sh\nexec %s/steamcmd.sh "$@"\n' "$STEAMCMD_DIR" > /usr/local/bin/steamcmd
chmod 0755 /usr/local/bin/steamcmd
ok "shim at /usr/local/bin/steamcmd"

# --------------------------------------------------------------- 4. user
#
# LinuxGSM refuses to run as root, and rightly so: it downloads and executes
# game code. The daemon runs as root because it needs the Docker socket, and
# drops to this user for LinuxGSM and SteamCMD work itself.

log "Service user"

if id -u "$SERVICE_USER" >/dev/null 2>&1; then
    ok "user ${SERVICE_USER} already exists"
else
    useradd --create-home --shell /bin/bash "$SERVICE_USER"
    ok "created the unprivileged user ${SERVICE_USER} (LinuxGSM will not run as root)"
fi

mkdir -p "$NODE_ROOT"
chown -R "${SERVICE_USER}:${SERVICE_USER}" "$NODE_ROOT" "$STEAMCMD_DIR"
chmod 0755 "$NODE_ROOT"
ok "${NODE_ROOT} and ${STEAMCMD_DIR} owned by ${SERVICE_USER}"

# ------------------------------------------------------------- 5. cgroups
#
# Resource limits are enforced through cgroup v2. If it is not mounted, or this
# kernel is still on v1, the daemon cannot enforce anything. Say so plainly
# rather than installing a node that silently ignores every memory limit.

log "cgroup v2"

if [[ -e /sys/fs/cgroup/cgroup.controllers ]]; then
    probe="/sys/fs/cgroup/gamemgr-probe.$$"
    if mkdir "$probe" 2>/dev/null; then
        rmdir "$probe" 2>/dev/null || true
        controllers="$(cat /sys/fs/cgroup/cgroup.controllers 2>/dev/null || true)"
        ok "cgroup v2 mounted and writable (controllers: ${controllers:-none listed})"
        for want in memory cpu; do
            grep -qw "$want" <<<"$controllers" || warn "The cgroup v2 '${want}' controller is not delegated here. ${want^} limits will be reported as unenforced."
        done
    else
        warn "cgroup v2 is mounted but not writable by this process. Memory and CPU limits will be REPORTED BUT NOT ENFORCED."
    fi
elif [[ -d /sys/fs/cgroup/memory ]]; then
    warn "This kernel is on cgroup v1. GameMGR enforces limits through cgroup v2 only, so limits here will be REPORTED BUT NOT ENFORCED. Boot with systemd.unified_cgroup_hierarchy=1 to fix it."
else
    warn "No cgroup hierarchy found at /sys/fs/cgroup. Memory and CPU limits will be REPORTED BUT NOT ENFORCED."
fi

# -------------------------------------------------------------- 6. binary

log "Node daemon binary"

fetch_to() {
    # fetch_to <src> <dest>: src is a URL or a local path.
    local src="$1" dest="$2"
    if [[ "$src" =~ ^https?:// ]]; then
        curl -fsSL --retry 3 --retry-delay 2 -o "$dest" "$src" \
            || die "Could not download ${src}"
    else
        [[ -f "$src" ]] || die "No such file: ${src}"
        cp -f "$src" "$dest"
    fi
}

sha_of() { sha256sum "$1" | awk '{print $1}'; }

staged="$(mktemp)"
trap 'rm -f "$staged"' EXIT

if [[ -n "$BINARY" ]]; then
    log "  fetching ${BINARY}"
    fetch_to "$BINARY" "$staged"

    # A checksum published next to the binary is not a strong guarantee on its
    # own (same host, same attacker), but it does catch a truncated download,
    # which is the failure that actually happens. --sha256 is the real check.
    if [[ -z "$BINARY_SHA256" && "$BINARY" =~ ^https?:// ]]; then
        side="$(curl -fsSL "${BINARY}.sha256" 2>/dev/null | awk '{print $1}' || true)"
        if [[ "$side" =~ ^[0-9a-fA-F]{64}$ ]]; then
            BINARY_SHA256="$side"
            log "  using the checksum published at ${BINARY}.sha256"
        fi
    fi

    if [[ -n "$BINARY_SHA256" ]]; then
        got="$(sha_of "$staged")"
        if [[ "${got,,}" != "${BINARY_SHA256,,}" ]]; then
            die "Checksum mismatch. Expected ${BINARY_SHA256}, got ${got}. Nothing was installed."
        fi
        ok "sha256 verified: ${got}"
    else
        warn "No checksum given and none published alongside the binary; installing it unverified. Pass --sha256 <hex> from deploy/build-node.sh next time."
    fi

    file "$staged" | grep -qi 'ELF 64-bit' \
        || die "That file is not a 64-bit Linux binary: $(file -b "$staged")"

elif [[ -n "$SOURCE_DIR" || -d ./agent || -d /usr/local/src/gamemgr/agent ]] && command -v go >/dev/null 2>&1; then
    src="${SOURCE_DIR:-}"
    [[ -n "$src" ]] || { [[ -d ./agent ]] && src="./agent"; }
    [[ -n "$src" ]] || src="/usr/local/src/gamemgr/agent"
    [[ -f "${src}/go.mod" ]] || die "No go.mod under ${src}. Point --source at the agent/ directory."

    log "  building from source at ${src} with $(go version)"
    ( cd "$src" && CGO_ENABLED=0 GOOS=linux GOARCH=amd64 go build -trimpath -ldflags "-s -w" -o "$staged" ./cmd/node ) \
        || die "The build failed. See the output above."
    ok "built $(sha_of "$staged")"

else
    cat >&2 <<EOF

ERROR  No daemon binary, and this box cannot build one: that needs both a Go
       toolchain and a source tree, and a fresh VM has neither. Do this
       instead:

       On a machine that has Go and a checkout of the GameMGR repo:

           ./deploy/build-node.sh

       That writes dist/gamemgr-node-linux-amd64 and a matching .sha256 file.
       Put the binary somewhere this box can reach over HTTP(S), then re-run:

           sudo bash install-node.sh --panel ${PANEL} \\
                --token <enroll token> \\
                --binary https://example/gamemgr-node-linux-amd64 \\
                --sha256 <the hex from the .sha256 file>

       Or copy the binary onto this box and pass its path to --binary.

EOF
    exit 1
fi

chmod 0755 "$staged"

if [[ -x "$BIN_PATH" ]] && [[ "$(sha_of "$staged")" == "$(sha_of "$BIN_PATH")" ]]; then
    ok "${BIN_PATH} is already this exact build, leaving it alone"
    BINARY_CHANGED="no"
else
    # Replace atomically: a running daemon holds the old inode, so mv over the
    # top is safe where writing in place is not (ETXTBSY).
    install -m 0755 "$staged" "${BIN_PATH}.new"
    mv -f "${BIN_PATH}.new" "$BIN_PATH"
    ok "installed ${BIN_PATH} ($(sha_of "$BIN_PATH"))"
    BINARY_CHANGED="yes"
fi

# -------------------------------------------------------------- 7. config
#
# The daemon is configured purely by environment. This file is the systemd
# EnvironmentFile, and the daemon rewrites it itself after enrolling to store
# the long-lived NODE_TOKEN, so it must stay at exactly this path and 0600.

log "Configuration"

mkdir -p "$CONFIG_DIR"
chmod 0700 "$CONFIG_DIR"

env_get() {
    # env_get <key>: read a key out of the existing config file, if any.
    local key="$1"
    [[ -f "$CONFIG_FILE" ]] || return 0
    sed -n "s/^${key}=//p" "$CONFIG_FILE" | tail -n1 | sed -e 's/^"//' -e 's/"$//'
}

EXISTING_TOKEN="$(env_get NODE_TOKEN)"

if [[ -n "$EXISTING_TOKEN" && "$REENROLL" == "yes" ]]; then
    warn "Discarding the existing node token because --reenroll was given. The panel will treat this as a new enrollment."
    EXISTING_TOKEN=""
fi

ENROLL_TOKEN="$TOKEN"
if [[ -n "$EXISTING_TOKEN" ]]; then
    # Never clobber a working node's credential on a re-run. That is the whole
    # difference between "idempotent" and "breaks the node you just fixed".
    ENROLL_TOKEN=""
    if [[ -n "$TOKEN" ]]; then
        warn "This node is already enrolled, so the --token you passed was ignored. Re-run with --reenroll to force a fresh enrollment."
    fi
    ok "keeping the existing node token"
elif [[ -z "$ENROLL_TOKEN" ]]; then
    warn "No --token and no existing credential: the daemon will start but stay unenrolled and refuse every panel call until you give it one."
fi

umask 077
tmp_env="$(mktemp "${CONFIG_DIR}/node.env.XXXXXX")"
{
    printf '# GameMGR node daemon configuration.\n'
    printf '# Written by install-node.sh on %s.\n' "$(date -u '+%Y-%m-%d %H:%M:%S UTC')"
    printf '# The daemon rewrites this file after enrolling, to store NODE_TOKEN.\n'
    printf 'NODE_NAME=%s\n' "$NODE_NAME"
    printf 'NODE_LISTEN=:%s\n' "$PORT"
    printf 'NODE_ROOT=%s\n' "$NODE_ROOT"
    printf 'NODE_PANEL_URL=%s\n' "$PANEL"
    printf 'NODE_DOCKER_SOCKET=%s\n' "$(env_get NODE_DOCKER_SOCKET || true)"
    printf 'NODE_HEARTBEAT=%s\n' "$(env_get NODE_HEARTBEAT || true)"
    printf 'NODE_CONFIG_FILE=%s\n' "$CONFIG_FILE"
    if [[ -n "$EXISTING_TOKEN" ]]; then printf 'NODE_TOKEN=%s\n' "$EXISTING_TOKEN"; fi
    if [[ -n "$ENROLL_TOKEN" ]]; then printf 'NODE_ENROLL_TOKEN=%s\n' "$ENROLL_TOKEN"; fi
} > "$tmp_env"

# Blank values would override the daemon's own defaults with empty strings on
# some readers, so drop the keys that ended up empty.
sed -i -e '/^NODE_DOCKER_SOCKET=$/d' -e '/^NODE_HEARTBEAT=$/d' "$tmp_env"

chmod 0600 "$tmp_env"
mv -f "$tmp_env" "$CONFIG_FILE"
ok "${CONFIG_FILE} written (0600, root only)"

# -------------------------------------------------------------- 8. systemd
#
# Root, because it needs the Docker socket and the cgroup tree. It drops to the
# gamemgr user itself for the LinuxGSM and SteamCMD work that must not be root.

log "systemd unit"

cat > "$UNIT_FILE" <<EOF
[Unit]
Description=GameMGR node daemon
Documentation=${PANEL}/docs
After=network-online.target docker.service
Wants=network-online.target

[Service]
Type=simple
EnvironmentFile=${CONFIG_FILE}
ExecStart=${BIN_PATH}
# Root on purpose: the daemon needs the Docker socket and the cgroup tree, and
# drops privilege to the ${SERVICE_USER} user itself for LinuxGSM work.
User=root
Restart=always
RestartSec=5
TimeoutStopSec=30
KillMode=mixed
LimitNOFILE=65535
StandardOutput=journal
StandardError=journal
SyslogIdentifier=gamemgr-node

[Install]
WantedBy=multi-user.target
EOF
chmod 0644 "$UNIT_FILE"
ok "${UNIT_FILE}"

systemctl daemon-reload
systemctl enable gamemgr-node >/dev/null 2>&1 || true

if systemctl is-active --quiet gamemgr-node; then
    log "  restarting the running daemon"
    systemctl restart gamemgr-node
else
    systemctl start gamemgr-node
fi
if [[ "$BINARY_CHANGED" == "yes" ]]; then
    ok "running the newly installed binary"
fi

# ------------------------------------------------------------- 9. firewall
#
# This section is not optional. A sibling product's installer left ufw alone,
# produced a perfectly correct install, and the panel could not reach the node.
# Silently. An install that cannot be reached is not an install.
#
# What it opens is now only the management surface: ssh, the daemon's port, and
# http/https. Game ports belong to the daemon, which opens the exact port a
# server was allocated on install and start, and removes it again on delete.
# Those two sets never overlap: the daemon refuses to create or remove a rule
# on 22, 80, 443 or its own port, so nothing written here can be taken away by
# a server operation.

log "Firewall"

open_ufw() {
    local rule="$1" note="$2"
    # The comment is how a human tells these apart from the daemon's per-server
    # rules later. It deliberately does not start with "gamemgr:", which is the
    # marker the daemon requires before it will delete anything: these rules are
    # the installer's and must stay out of that namespace.
    if ufw allow "$rule" comment "gamemgr installer: ${note}" >/dev/null 2>&1; then
        FIREWALL_NOTES+=("opened ${rule} (${note})")
    else
        warn "ufw refused to open ${rule}. Open it by hand: ufw allow ${rule}"
    fi
}

# legacy_ranges lists port-range rules already in the ruleset. Older versions of
# this installer added three of them, and a node upgraded today may well have a
# server running on a port only one of those ranges covers. Removing them
# automatically would cut those players off mid-session, so they are reported
# and left alone.
legacy_ranges() {
    ufw status 2>/dev/null | awk '$1 ~ /^[0-9]+:[0-9]+\/(tcp|udp)$/ {print $1}' | sort -u
}

if [[ "$TOUCH_FIREWALL" != "yes" ]]; then
    warn "Skipping the firewall because --no-firewall was given. If ufw is active, the panel will not reach port ${PORT} and the node will show as offline."
elif command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -q '^Status: active'; then
    open_ufw "22/tcp" "ssh"
    open_ufw "${PORT}/tcp" "the node daemon"
    open_ufw "80/tcp" "http"
    open_ufw "443/tcp" "https"

    IFS=',' read -r -a ranges <<< "$GAME_PORTS"
    for range in "${ranges[@]}"; do
        range="${range// /}"
        [[ -n "$range" ]] || continue
        open_ufw "${range}/tcp" "game ports, requested with --game-ports"
        open_ufw "${range}/udp" "game ports, requested with --game-ports"
    done

    if [[ ${#FIREWALL_NOTES[@]} -gt 0 ]]; then
        ok "ufw is active, so these rules were added:"
        for note in "${FIREWALL_NOTES[@]}"; do printf '       %s\n' "$note"; done
    fi
    ok "game ports are opened per server by the daemon, not here"

    stale="$(legacy_ranges || true)"
    if [[ -n "$stale" ]]; then
        printf '\033[0;33m  !!\033[0m %s\n' "This node has port ranges open from an earlier installer:" >&2
        while read -r r; do [[ -n "$r" ]] && printf '       %s\n' "$r" >&2; done <<< "$stale"
        cat >&2 <<'STALE'
       They are NOT removed automatically: a server running right now may be
       reachable only because of one of them. Check what is running first, then
       remove them one at a time, highest rule number first:

           ufw status numbered
           ufw delete <number>

       Restart each affected server afterwards so the daemon writes the exact
       rule it needs, or just leave the server alone: it opens its own port on
       the next start.
STALE
    fi
elif command -v ufw >/dev/null 2>&1; then
    ok "ufw is installed but inactive, nothing to open"
    ok "the daemon will report this node as unmanaged, and will not add or remove any rule while ufw is off"
elif command -v firewall-cmd >/dev/null 2>&1 && firewall-cmd --state >/dev/null 2>&1; then
    warn "firewalld is running and this installer does not manage it, and neither does the daemon: it only drives ufw. Open the management ports yourself, and a port for every server you create:
       firewall-cmd --permanent --add-port=${PORT}/tcp
       firewall-cmd --reload"
else
    ok "no active host firewall found (ufw/firewalld); nothing to open"
    if command -v iptables >/dev/null 2>&1 && [[ "$(iptables -S INPUT 2>/dev/null | head -n1)" == "-P INPUT DROP" ]]; then
        warn "iptables has a default DROP policy on INPUT. Something other than ufw is filtering here; make sure port ${PORT}/tcp is allowed or the panel cannot reach this node."
    fi
fi

# ------------------------------------------------------- 10. enroll + report

log "Waiting for the daemon"

health_ok="no"
enrolled="no"
for _ in $(seq 1 30); do
    if curl -fsS --max-time 2 "http://127.0.0.1:${PORT}/healthz" >/dev/null 2>&1; then
        health_ok="yes"
        # Enrollment is done by the daemon itself: it trades the enroll token for
        # its long-lived credential and writes that back into the config file.
        if [[ -n "$(env_get NODE_TOKEN)" ]]; then
            enrolled="yes"
            break
        fi
    fi
    sleep 1
done

echo
printf '\033[1m--- gamemgr-node ---------------------------------------------\033[0m\n'
systemctl --no-pager --full status gamemgr-node 2>/dev/null | sed -n '1,12p' || true
echo

if [[ "$health_ok" == "yes" ]]; then
    ok "the daemon is answering on http://127.0.0.1:${PORT}/healthz"
else
    warn "The daemon did not answer /healthz within 30s. See: journalctl -u gamemgr-node -n 50"
fi

if [[ "$enrolled" == "yes" ]]; then
    ok "enrolled with ${PANEL}: it holds a long-lived node token now"
elif [[ -n "$EXISTING_TOKEN" ]]; then
    ok "already enrolled (existing token preserved)"
elif [[ -n "$ENROLL_TOKEN" ]]; then
    warn "The daemon has not enrolled yet. Enroll tokens are single use and expire, so generate a fresh one on the panel and re-run with --token. Last few log lines:"
    journalctl -u gamemgr-node -n 15 --no-pager 2>/dev/null | sed 's/^/       /' || true
else
    warn "This node is not enrolled. Get an enroll token from ${PANEL} and re-run with --token <token>."
fi

if [[ ${#WARNINGS[@]} -gt 0 ]]; then
    echo
    printf '\033[0;33mWarnings (%d):\033[0m\n' "${#WARNINGS[@]}"
    for w in "${WARNINGS[@]}"; do printf '  - %s\n' "$w"; done
fi

cat <<EOF

Check it with:

    systemctl status gamemgr-node

Config:   ${CONFIG_FILE}
Logs:     journalctl -u gamemgr-node -f
Data:     ${NODE_ROOT}
Panel:    ${PANEL}

Game ports are not opened here. The daemon opens the exact port a server is
allocated when it is installed or started, and removes that rule when the
server is deleted. Each rule carries a comment naming the server it belongs to:

    ufw status numbered | grep gamemgr:
EOF
