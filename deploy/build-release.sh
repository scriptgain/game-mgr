#!/usr/bin/env bash
#
# Build the distributable GameMGR panel release.
#
# Produces  dist/gamemgr-<version>.tar.gz  containing the panel source plus a
# fully installed vendor/, so a target VM never needs composer network access,
# and  dist/latest.json  the manifest app/Services/UpdateService.php polls at
# https://scriptgain.com/releases/gamemgr/latest.json
#
# Usage:
#   deploy/build-release.sh 1.0.1
#   deploy/build-release.sh                       # reads ./VERSION
#   deploy/build-release.sh 1.0.1 --ref v1.0.1    # build a tag, not HEAD
#   PHP_BIN=/usr/bin/php8.3 deploy/build-release.sh
#
# The build ALWAYS runs from a fresh `git clone`, never from the working tree.
# That is not tidiness, it is the fix for a real incident: BackupMGR's release
# script rsynced the working directory, swept in the gitignored agent/ tree, and
# shipped its private Go source to paying customers for several releases. The
# clone can only ever contain committed files, agent/ is deleted explicitly on
# top of that, and the tarball is asserted to hold zero .go files before it is
# allowed out of the door.
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# ---- defaults (override via flag or env) ----
VERSION=""
REF="${REF:-HEAD}"
SOURCE_REPO="${SOURCE_REPO:-$ROOT}"
BASE_URL="${BASE_URL:-https://scriptgain.com/releases/gamemgr}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-}"
KEEP_BUILD=0
OUT="$ROOT/dist"

usage() {
  sed -n '3,22p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
  exit "${1:-0}"
}

while [ $# -gt 0 ]; do
  case "$1" in
    --ref)        REF="$2"; shift 2 ;;
    --source)     SOURCE_REPO="$2"; shift 2 ;;
    --base-url)   BASE_URL="${2%/}"; shift 2 ;;
    --out)        OUT="$2"; shift 2 ;;
    --keep-build) KEEP_BUILD=1; shift ;;
    -h|--help)    usage 0 ;;
    -*)           echo "Unknown flag: $1" >&2; usage 1 ;;
    *)            VERSION="$1"; shift ;;
  esac
done

log()  { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m!!  %s\033[0m\n' "$*" >&2; }
die()  { printf '\033[1;31m!!  %s\033[0m\n' "$*" >&2; exit 1; }

VERSION="${VERSION:-$(cat "$ROOT/VERSION" 2>/dev/null || true)}"
VERSION="$(printf '%s' "${VERSION#v}" | tr -d '[:space:]')"
[ -n "$VERSION" ] || die "Set a version: deploy/build-release.sh <version>  (or create ./VERSION)"

command -v git >/dev/null || die "git is required."
command -v tar >/dev/null || die "tar is required."
command -v sha256sum >/dev/null || die "sha256sum is required."

# Composer is driven through PHP_BIN rather than its own shebang, so the vendor
# tree is resolved against the PHP the release actually targets. Building on an
# older PHP would quietly install older, still-compatible package versions.
if [ -z "$COMPOSER_BIN" ]; then
  COMPOSER_BIN="$(command -v composer || true)"
fi
[ -n "$COMPOSER_BIN" ] || die "composer not found; install it or set COMPOSER_BIN=/path/to/composer.phar"

# composer.json is the authority on the PHP floor; do not hardcode it here.
# shellcheck disable=SC2016  # single quotes are deliberate: this is PHP, not shell
PHP_CONSTRAINT="$("$PHP_BIN" -r 'echo json_decode(file_get_contents($argv[1]),true)["require"]["php"] ?? "";' "$ROOT/composer.json" 2>/dev/null || true)"
PHP_MIN="$(printf '%s' "$PHP_CONSTRAINT" | grep -oE '[0-9]+\.[0-9]+' | head -n1)"
[ -n "$PHP_MIN" ] || die "Could not read the php constraint from composer.json."
# shellcheck disable=SC2016
"$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, $argv[1], ">=") ? 0 : 1);' "$PHP_MIN" \
  || die "composer.json needs PHP >= ${PHP_MIN}; PHP_BIN ($PHP_BIN) is $("$PHP_BIN" -r 'echo PHP_VERSION;'). Set PHP_BIN."

NAME="gamemgr-${VERSION}"
TARBALL="$OUT/${NAME}.tar.gz"

BUILD="$(mktemp -d "${TMPDIR:-/tmp}/gamemgr-release.XXXXXXXX")"
cleanup() {
  if [ "$KEEP_BUILD" = "1" ]; then
    echo "Build tree kept at $BUILD"
  else
    rm -rf "$BUILD"
  fi
}
trap cleanup EXIT
STAGE="$BUILD/src"

log "Cloning ${SOURCE_REPO} @ ${REF}"
# A clone can only contain committed files. Anything gitignored, half-written,
# or local-only in the working tree is structurally incapable of shipping.
if [ -d "$SOURCE_REPO/.git" ] && [ -n "$(git -C "$SOURCE_REPO" status --porcelain 2>/dev/null)" ]; then
  warn "Working tree at ${SOURCE_REPO} has uncommitted changes; they will NOT be in this release."
fi
git clone --quiet --no-hardlinks "$SOURCE_REPO" "$STAGE"
if [ "$REF" = "HEAD" ] && [ -d "$SOURCE_REPO/.git" ]; then
  REF="$(git -C "$SOURCE_REPO" rev-parse HEAD)"
fi
git -C "$STAGE" checkout --quiet --detach "$REF"
COMMIT="$(git -C "$STAGE" rev-parse --short HEAD)"
echo "    commit ${COMMIT}"

log "Removing the node daemon source"
# agent/ is the node daemon's private Go source. It is tracked (the daemon is
# part of this project) but it is NOT part of the panel release: nodes get a
# compiled binary from deploy/build-node.sh, never source. UpdateService lists
# 'agent' in STALE_PATHS precisely so an install that once had it loses it.
rm -rf "$STAGE/agent"

log "Stamping VERSION"
printf '%s\n' "$VERSION" > "$STAGE/VERSION"

log "Installing production dependencies"
# .env has to exist before composer runs: the post-autoload-dump script boots
# Laravel for package:discover, which reads config that reads env().
cp "$STAGE/.env.example" "$STAGE/.env"
( cd "$STAGE" && "$PHP_BIN" "$COMPOSER_BIN" install \
    --no-dev --optimize-autoloader --no-interaction --no-progress --no-ansi )
[ -f "$STAGE/vendor/autoload.php" ] || die "composer install produced no vendor/autoload.php."

log "Pruning non-shipping paths"
# .env must never ship (it would overwrite a live one on self-update), and
# storage/ is deliberately absent so an update cannot clobber logs, sessions or
# the operator's uploaded files. install-panel.sh creates the storage skeleton.
rm -rf "$STAGE/.git" "$STAGE/.github" "$STAGE/.gitattributes" \
       "$STAGE/tests" "$STAGE/phpunit.xml" "$STAGE/.phpunit.result.cache" \
       "$STAGE/storage" "$STAGE/node_modules" "$STAGE/dist" \
       "$STAGE/.editorconfig" "$STAGE/.npmrc"
# .env.example stays: it is the template install-panel.sh builds .env from.
rm -f "$STAGE/.env" "$STAGE/.env.backup" "$STAGE/.env.production"

log "Packing ${NAME}.tar.gz"
mkdir -p "$OUT"
rm -f "$TARBALL"
# Rooted at the app root, not in a versioned top directory: UpdateService
# applies a release with `tar xzf <file> -C base_path()` and no strip.
tar czf "$TARBALL" -C "$STAGE" \
  --owner=0 --group=0 --numeric-owner \
  --exclude='./.git' --exclude='./agent' --exclude='./TODO.md' \
  .

log "Verifying the archive"
LISTING="$BUILD/listing.txt"
tar tzf "$TARBALL" > "$LISTING"

# The assertion this whole script exists for.
GO_COUNT="$(grep -c '\.go$' "$LISTING" || true)"
if [ "$GO_COUNT" != "0" ]; then
  grep '\.go$' "$LISTING" | head -20 >&2
  die "Release contains ${GO_COUNT} Go source file(s). Private node source must never ship."
fi
echo "    .go files:      ${GO_COUNT}  (must be 0)"

AGENT_COUNT="$(grep -c '^\./agent/' "$LISTING" || true)"
[ "$AGENT_COUNT" = "0" ] || die "Release contains ${AGENT_COUNT} path(s) under agent/."
echo "    agent/ paths:   ${AGENT_COUNT}  (must be 0)"

# .env.example is the installer's template and must stay; any other .env is a leak.
ENV_COUNT="$(grep -E '^\./\.env($|\.)' "$LISTING" | grep -vxc './.env.example' || true)"
[ "$ENV_COUNT" = "0" ] || die "Release contains a .env file."
echo "    .env files:     ${ENV_COUNT}  (must be 0, .env.example excepted)"

grep -qx './vendor/autoload.php' "$LISTING" || die "Release has no vendor/autoload.php."
grep -qx './.env.example' "$LISTING"        || die "Release has no .env.example for the installer."
grep -qx './artisan' "$LISTING"             || die "Release has no artisan."
echo "    vendor/:        $(grep -c '^\./vendor/' "$LISTING") paths"
echo "    total entries:  $(wc -l < "$LISTING")"

SHA="$(sha256sum "$TARBALL" | cut -d' ' -f1)"
printf '%s  %s\n' "$SHA" "${NAME}.tar.gz" > "${TARBALL}.sha256"

log "Writing latest.json"
# EXACTLY the three fields UpdateService::record() reads. Anything else would be
# decoration the updater ignores, so it is not written.
cat > "$OUT/latest.json" <<JSON
{
  "latest_version": "${VERSION}",
  "download_url": "${BASE_URL}/${NAME}.tar.gz",
  "download_sha256": "${SHA}"
}
JSON

SIZE="$(du -h "$TARBALL" | cut -f1)"
log "Done"
echo "  tarball : $TARBALL ($SIZE)"
echo "  sha256  : $SHA"
echo "  manifest: $OUT/latest.json"
echo "  commit  : $COMMIT"
echo
echo "Publish by copying both to ${BASE_URL}/ then install with:"
echo "  ./deploy/install-panel.sh --source ${BASE_URL}/${NAME}.tar.gz --domain panel.example.com --email you@example.com"
