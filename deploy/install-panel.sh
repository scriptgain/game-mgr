#!/usr/bin/env bash
#
# GameMGR panel installer for a fresh Ubuntu 24.04 server (Vultr, Hetzner, any
# plain VM). Provisions nginx, PHP-FPM, MariaDB, the app, .env, the database,
# reference data, the first admin, the queue worker, the scheduler and TLS.
#
# Usage (as root):
#   ./install-panel.sh --source ./dist/gamemgr-1.0.0.tar.gz --domain panel.example.com --email you@example.com
#   ./install-panel.sh --source https://scriptgain.com/releases/gamemgr/gamemgr-1.0.0.tar.gz --no-ssl
#
# Flags:
#   --source <path|url>   release tarball, or a directory holding the app tree
#   --domain <fqdn>       hostname the panel answers on
#   --email <addr>        Let's Encrypt registration address (required for TLS)
#   --no-ssl              serve plain HTTP (use when the box is reached by bare IP)
#   --db-pass <pass>      database password (generated when omitted)
#   --admin-email <addr>  first admin login (defaults to --email)
#   --admin-pass <pass>   first admin password (generated and printed when omitted)
#   --app-dir <path>      install location (default /var/www/gamemgr)
#   --node-port <port>    node daemon the /daemon/ console proxy targets (8942)
#   --dry-run             resolve flags and unpack the source, change nothing else
#
# Idempotent: safe to re-run. An existing .env keeps its APP_KEY and DB password.
#
set -euo pipefail

# ---- defaults ----
APP_DIR="${APP_DIR:-/var/www/gamemgr}"
DB_NAME="${DB_NAME:-gamemgr}"
DB_USER="${DB_USER:-gamemgr}"
DB_HOST="127.0.0.1"
SERVICE="gamemgr-queue"
NGINX_SITE="gamemgr"
# Where the /daemon/ console proxy sends traffic. A single-box install runs the
# node daemon on loopback; config/node.php defaults its port to 8942.
DAEMON_HOST="${DAEMON_HOST:-127.0.0.1}"
DAEMON_PORT="${DAEMON_PORT:-8942}"

DOMAIN=""
EMAIL=""
SOURCE=""
DB_PASS=""
ADMIN_EMAIL=""
ADMIN_PASS=""
NO_SSL=0
DRY_RUN=0

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

log()  { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m!!  %s\033[0m\n' "$*" >&2; }
die()  { printf '\033[1;31m!!  %s\033[0m\n' "$*" >&2; exit 1; }
usage() { sed -n '3,22p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit "${1:-0}"; }

need_arg() { [ -n "${2:-}" ] || die "$1 needs a value."; }

while [ $# -gt 0 ]; do
  case "$1" in
    --domain)      need_arg "$1" "${2:-}"; DOMAIN="$2"; shift 2 ;;
    --email)       need_arg "$1" "${2:-}"; EMAIL="$2"; shift 2 ;;
    --source)      need_arg "$1" "${2:-}"; SOURCE="$2"; shift 2 ;;
    --db-pass)     need_arg "$1" "${2:-}"; DB_PASS="$2"; shift 2 ;;
    --admin-email) need_arg "$1" "${2:-}"; ADMIN_EMAIL="$2"; shift 2 ;;
    --admin-pass)  need_arg "$1" "${2:-}"; ADMIN_PASS="$2"; shift 2 ;;
    --app-dir)     need_arg "$1" "${2:-}"; APP_DIR="$2"; shift 2 ;;
    --db-name)     need_arg "$1" "${2:-}"; DB_NAME="$2"; shift 2 ;;
    --db-user)     need_arg "$1" "${2:-}"; DB_USER="$2"; shift 2 ;;
    --node-port)   need_arg "$1" "${2:-}"; DAEMON_PORT="$2"; shift 2 ;;
    --node-host)   need_arg "$1" "${2:-}"; DAEMON_HOST="$2"; shift 2 ;;
    --no-ssl)      NO_SSL=1; shift ;;
    --dry-run)     DRY_RUN=1; shift ;;
    -h|--help)     usage 0 ;;
    *)             echo "Unknown argument: $1" >&2; usage 1 ;;
  esac
done

# Passwords are alphanumeric on purpose. They land in .env, in mysql statements
# and in the closing summary, and every one of those has a quoting trap: dotenv
# treats an unquoted '#' as the start of a comment and silently truncates the
# value, which produces an install that fails to authenticate with no error
# pointing at the password. Restricting the alphabet removes the whole class.
# head reads a bounded chunk FIRST and tr filters what it produced. The obvious
# spelling, `tr < /dev/urandom | head -c N`, is a silent installer killer: head
# exits as soon as it has enough, tr takes SIGPIPE on an infinite input and dies
# 141, and `set -o pipefail` turns that into an exit with no message whatsoever.
gen_pass() {
  local want="${1:-24}" out=""
  while [ "${#out}" -lt "$want" ]; do
    out="${out}$(head -c 256 /dev/urandom | LC_ALL=C tr -dc 'A-Za-z0-9')"
  done
  printf '%s' "${out:0:want}"
}

# ------------------------------------------------------------------ preflight
if [ "$DRY_RUN" = "0" ]; then
  [ "$(id -u)" -eq 0 ] || die "Run as root."
  command -v apt-get >/dev/null || die "This installer targets Ubuntu/Debian (apt)."
fi

if [ -z "$DOMAIN" ] && [ "$NO_SSL" = "0" ]; then
  die "Give --domain <fqdn> (with --email for TLS), or --no-ssl to serve the bare IP over HTTP."
fi
if [ -n "$DOMAIN" ] && [ "$NO_SSL" = "0" ] && [ -z "$EMAIL" ]; then
  die "TLS needs a registration address: add --email you@example.com, or pass --no-ssl."
fi

ADMIN_EMAIL="${ADMIN_EMAIL:-$EMAIL}"
[ -n "$ADMIN_EMAIL" ] || die "No admin login: pass --admin-email you@example.com (or --email)."

USE_SSL=0
[ -n "$DOMAIN" ] && [ "$NO_SSL" = "0" ] && USE_SSL=1

# ------------------------------------------------------- base tools + source
if [ "$DRY_RUN" = "0" ]; then
  log "Installing base tools"
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -y
  apt-get install -y --no-install-recommends \
    ca-certificates curl unzip tar rsync gnupg software-properties-common openssl
fi

WORK="$(mktemp -d "${TMPDIR:-/tmp}/gamemgr-install.XXXXXXXX")"
trap 'rm -rf "$WORK"' EXIT
STAGE="$WORK/app"
mkdir -p "$STAGE"

# Default source: a release next to this script, else the tree this script
# lives in (a checkout or an unpacked release).
if [ -z "$SOURCE" ]; then
  # shellcheck disable=SC2012  # newest-first is the point; release names are safe
  newest="$(ls -1t "$HERE"/../dist/gamemgr-*.tar.gz 2>/dev/null | head -n1 || true)"
  if [ -n "$newest" ]; then
    SOURCE="$newest"
  elif [ -f "$HERE/../artisan" ]; then
    SOURCE="$(cd "$HERE/.." && pwd)"
  else
    die "No --source given and no release found. Build one: deploy/build-release.sh"
  fi
fi

log "Unpacking source: $SOURCE"
case "$SOURCE" in
  http://*|https://*)
    curl -fL --retry 3 -o "$WORK/release.tar.gz" "$SOURCE" || die "Download failed: $SOURCE"
    # The release is rooted at the app root (no versioned top directory), which
    # is what lets UpdateService apply it straight over an install.
    tar xzf "$WORK/release.tar.gz" -C "$STAGE"
    ;;
  *.tar.gz|*.tgz)
    [ -f "$SOURCE" ] || die "No such file: $SOURCE"
    tar xzf "$SOURCE" -C "$STAGE"
    ;;
  *)
    [ -d "$SOURCE" ] || die "--source must be a .tar.gz, a URL, or a directory: $SOURCE"
    rsync -a --exclude '.git' --exclude '.env' --exclude 'node_modules' \
             --exclude 'agent' --exclude 'dist' --exclude 'tests' \
             --exclude 'storage' "$SOURCE"/ "$STAGE"/
    ;;
esac

[ -f "$STAGE/artisan" ] && [ -f "$STAGE/composer.json" ] \
  || die "Source does not look like a GameMGR tree (no artisan/composer.json)."
[ -f "$STAGE/vendor/autoload.php" ] || warn "Source ships no vendor/; composer will have to run on this box (needs network)."
[ -d "$STAGE/agent" ] && { warn "Source contains agent/ (private node source); removing it."; rm -rf "$STAGE/agent"; }

APP_VERSION="$(tr -d '[:space:]' < "$STAGE/VERSION" 2>/dev/null || echo unknown)"

# PHP version comes from composer.json, never from a guess in this script.
PHP_CONSTRAINT="$(grep -oE '"php"[[:space:]]*:[[:space:]]*"[^"]+"' "$STAGE/composer.json" | head -n1 | sed 's/.*"\(.*\)"$/\1/')"
PHP_VER="$(printf '%s' "$PHP_CONSTRAINT" | grep -oE '[0-9]+\.[0-9]+' | head -n1)"
[ -n "$PHP_VER" ] || die "Could not read the php constraint from composer.json."
PHP="/usr/bin/php${PHP_VER}"

echo "    version:  ${APP_VERSION}"
echo "    php:      ${PHP_VER}  (composer.json requires \"${PHP_CONSTRAINT}\")"
echo "    app dir:  ${APP_DIR}"
echo "    domain:   ${DOMAIN:-<bare IP>}"
echo "    tls:      $([ "$USE_SSL" = 1 ] && echo "certbot (${EMAIL})" || echo "off, plain HTTP")"
echo "    admin:    ${ADMIN_EMAIL}"
echo "    console:  proxied to ${DAEMON_HOST}:${DAEMON_PORT} via /daemon/"

# ------------------------------------------------------- nginx config templates
# Rendered here rather than further down so --dry-run can emit the exact files
# the real run would install and they can be checked with `nginx -t` off-box.

MAP_SNIPPET="/etc/nginx/conf.d/gamemgr-upgrade.conf"
# The /daemon/ proxy needs $connection_upgrade, which is a map, not a builtin.
# The dev stack's nginx image ships one; stock Ubuntu nginx does NOT, and the
# config then fails to load with an undefined-variable error.
IFS= read -r -d '' UPGRADE_MAP <<'MAP' || true
# Required by the GameMGR /daemon/ proxy. Included from the http context.
map $http_upgrade $connection_upgrade {
    default upgrade;
    ''      close;
}
MAP

# The live console. NodeClient::streamUrl() returns url("/daemon/api/servers/
# <uuid>/stream?...") -- the PANEL's own origin, never the node's. That is
# deliberate: the panel is HTTPS and the daemon is plain HTTP on localhost, so
# pointing the browser at the node directly would be mixed content and the
# browser would block it. There is no /daemon route in routes/web.php and there
# never will be; this proxy IS the implementation. Without it the EventSource in
# public/js/gamemgr.js 404s and the console silently drops to backlog polling.
IFS= read -r -d '' DAEMON_PROXY <<PROXY || true
    location /daemon/ {
        proxy_pass http://${DAEMON_HOST}:${DAEMON_PORT}/;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection \$connection_upgrade;
        proxy_set_header Host \$host;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;

        # The console is Server-Sent Events. These three are mandatory, not
        # tuning: with nginx's default buffering the browser receives nothing
        # until a buffer fills, so the console sits frozen for minutes and then
        # dumps the lot, which reads as "the node is broken".
        proxy_buffering off;
        proxy_cache off;
        gzip off;

        proxy_read_timeout 3600s;
        proxy_send_timeout 3600s;
    }
PROXY

IFS= read -r -d '' APP_LOCATIONS <<APPLOC || true
    client_max_body_size 512M;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "same-origin" always;

${DAEMON_PROXY}

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }

    location ~ \.php\$ {
        try_files \$uri =404;
        fastcgi_pass unix:/run/php/php${PHP_VER}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_read_timeout 300;
        include fastcgi_params;
    }

    # ACME lives under .well-known, so the dotfile deny must not swallow it.
    location ~ /\\.(?!well-known).* { deny all; }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
APPLOC

# The `http2 on;` directive only exists from nginx 1.25.1. Ubuntu 24.04 ships 1.24, where it
# is an unknown directive and the entire config fails to load.
NGINX_VER="$(nginx -v 2>&1 | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -n1 || true)"
if [ "$(printf '%s\n1.25.1\n' "${NGINX_VER:-1.24.0}" | sort -V | head -n1)" = "1.25.1" ]; then
  SSL_LISTEN="listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;"
else
  SSL_LISTEN="listen 443 ssl http2;
    listen [::]:443 ssl http2;"
fi

VHOST="/etc/nginx/sites-available/${NGINX_SITE}.conf"

# Rendered from scratch on every run rather than patched, so a re-run converges
# instead of accumulating. That is also why certbot runs in certonly mode below:
# anything it edited into this file would be wiped by the next install run.
render_vhost() { # $1 = http|https, $2 = destination file
  if [ "$1" = "https" ]; then
    cat > "$2" <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};

    # Renewal is webroot based, so this must stay reachable on plain port 80.
    location ^~ /.well-known/acme-challenge/ {
        root ${APP_DIR}/public;
        default_type "text/plain";
        try_files \$uri =404;
    }

    location / { return 301 https://\$host\$request_uri; }
}

server {
    ${SSL_LISTEN}
    server_name ${DOMAIN};
    root ${APP_DIR}/public;
    index index.php;
    charset utf-8;

    ssl_certificate     /etc/letsencrypt/live/${DOMAIN}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${DOMAIN}/privkey.pem;
    # Spelled out rather than included from certbot's options-ssl-nginx.conf,
    # which only exists once certbot's nginx *installer* has run and would
    # otherwise be a missing include.
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;

${APP_LOCATIONS}
}
NGINX
  else
    if [ -n "$DOMAIN" ]; then
      _listen="listen 80;
    listen [::]:80;"
      _name="$DOMAIN"
    else
      # No name to match on, so this must be the default server or nginx
      # answers bare-IP requests with the packaged welcome page.
      _listen="listen 80 default_server;
    listen [::]:80 default_server;"
      _name="_"
    fi
    cat > "$2" <<NGINX
server {
    ${_listen}
    server_name ${_name};
    root ${APP_DIR}/public;
    index index.php;
    charset utf-8;

${APP_LOCATIONS}
}
NGINX
  fi
}

write_vhost() { # $1 = http|https
  render_vhost "$1" "$VHOST"
  ln -sf "$VHOST" "/etc/nginx/sites-enabled/${NGINX_SITE}.conf"
  nginx -t || die "Generated nginx config is invalid (see the error above)."
  systemctl reload nginx || systemctl restart nginx
}

if [ "$DRY_RUN" = "1" ]; then
  log "Dry run: source resolved and unpacked cleanly, nothing installed."
  echo "    unpacked $(find "$STAGE" -type f | wc -l) files to $STAGE"
  echo "    go files in source: $(find "$STAGE" -name '*.go' | wc -l)"
  mkdir -p "$WORK/nginx"
  printf '%s\n' "$UPGRADE_MAP" > "$WORK/nginx/gamemgr-upgrade.conf"
  render_vhost http  "$WORK/nginx/gamemgr-http.conf"
  render_vhost https "$WORK/nginx/gamemgr-https.conf"
  echo "    rendered nginx config:"
  for f in "$WORK/nginx"/*.conf; do
    echo "      --------------------------------------------- $f"
    sed 's/^/      /' "$f"
  done
  exit 0
fi

# ------------------------------------------------------------------ packages
log "Installing PHP ${PHP_VER}, nginx, MariaDB"
# Ubuntu 24.04 carries PHP 8.3 already; anything else needs ondrej's PPA.
if ! apt-cache show "php${PHP_VER}-fpm" >/dev/null 2>&1; then
  add-apt-repository -y ppa:ondrej/php
  apt-get update -y
fi
# Extension list is the union of the ext-* requirements in composer.lock
# (ctype, dom, fileinfo, filter, hash, iconv, json, libxml, mbstring, openssl,
# pcre, session, tokenizer) plus the PDO driver the app connects with. The ones
# PHP compiles in by default are not listed as packages; the rest are:
apt-get install -y \
  "php${PHP_VER}-fpm" "php${PHP_VER}-cli" "php${PHP_VER}-mysql" \
  "php${PHP_VER}-mbstring" "php${PHP_VER}-xml" "php${PHP_VER}-curl" \
  "php${PHP_VER}-zip" "php${PHP_VER}-bcmath" "php${PHP_VER}-intl" \
  "php${PHP_VER}-gd" "php${PHP_VER}-opcache" \
  mariadb-server nginx

[ -x "$PHP" ] || die "php${PHP_VER} did not install."

for ext in ctype dom fileinfo filter hash iconv json libxml mbstring openssl pcre session tokenizer pdo_mysql curl; do
  "$PHP" -m | grep -qix "$ext" || die "PHP extension missing after install: $ext"
done

log "Installing Composer"
if ! command -v composer >/dev/null; then
  curl -fsS https://getcomposer.org/installer -o "$WORK/composer-setup.php"
  "$PHP" "$WORK/composer-setup.php" --install-dir=/usr/local/bin --filename=composer --quiet
fi

systemctl enable --now mariadb >/dev/null 2>&1 || true
systemctl enable --now "php${PHP_VER}-fpm" >/dev/null 2>&1 || true

# ------------------------------------------------------------------ database
log "Creating database ${DB_NAME}"
# Reuse the password already in place, so a re-run does not lock the app out of
# its own database by rotating the credential on only one side.
EXISTING_DB_PASS=""
if [ -z "$DB_PASS" ] && [ -f "$APP_DIR/.env" ]; then
  EXISTING_DB_PASS="$(sed -n 's/^DB_PASSWORD=//p' "$APP_DIR/.env" | head -n1 | sed 's/^"\(.*\)"$/\1/')"
fi
DB_PASS="${DB_PASS:-${EXISTING_DB_PASS:-$(gen_pass 28)}}"

mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'${DB_HOST}' IDENTIFIED BY '${DB_PASS}';"
mysql -e "ALTER USER '${DB_USER}'@'${DB_HOST}' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'${DB_HOST}'; FLUSH PRIVILEGES;"

# ----------------------------------------------------------------- deploying
log "Deploying to ${APP_DIR}"
mkdir -p "$APP_DIR"
# .env and storage are the install's own state; a redeploy must never touch them.
rsync -a --exclude '.env' --exclude 'storage' "$STAGE"/ "$APP_DIR"/
cd "$APP_DIR"

# git tracks no empty directories and the release ships no storage/ at all, so
# the runtime skeleton is created here rather than shipped.
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
         storage/logs storage/app/public storage/app/private/updates bootstrap/cache

# ------------------------------------------------------------------ env file
log "Writing .env"
[ -f .env ] || cp .env.example .env

# Every value is written double-quoted. An unquoted '#' starts a comment in
# dotenv and truncates the value; quoting removes that trap even for values
# that look safe today.
set_env() {
  local key="$1" val="$2"
  if grep -q "^${key}=" .env; then
    # '|' delimiter: values carry '/' (URLs) but never '|'.
    sed -i "s|^${key}=.*|${key}=\"${val}\"|" .env
  else
    printf '%s="%s"\n' "$key" "$val" >> .env
  fi
}

if [ -n "$DOMAIN" ]; then
  APP_URL="$([ "$USE_SSL" = 1 ] && echo "https://${DOMAIN}" || echo "http://${DOMAIN}")"
else
  PUBLIC_IP="$(curl -fsS4 --max-time 5 https://ifconfig.me 2>/dev/null || true)"
  [ -n "$PUBLIC_IP" ] || PUBLIC_IP="$(hostname -I | awk '{print $1}')"
  APP_URL="http://${PUBLIC_IP}"
fi

set_env APP_NAME "GameMGR"
set_env APP_ENV production
set_env APP_DEBUG false
set_env APP_URL "$APP_URL"
set_env LOG_LEVEL warning
set_env DB_CONNECTION mysql
set_env DB_HOST "$DB_HOST"
set_env DB_PORT 3306
set_env DB_DATABASE "$DB_NAME"
set_env DB_USERNAME "$DB_USER"
set_env DB_PASSWORD "$DB_PASS"
set_env SESSION_DRIVER database
set_env QUEUE_CONNECTION database
set_env CACHE_STORE database
# The dev stack points mail at mailpit, which does not exist here and would hang
# every notification. Real SMTP is configured in the panel (Settings > Email),
# which overrides this at boot.
set_env MAIL_MAILER log
set_env MAIL_HOST 127.0.0.1
set_env MAIL_PORT 25
# THE production switch. NODE_FAKE ships true so the dev panel is exercisable
# with no real node; left on here the panel would serve invented CPU, memory and
# player counts for offline nodes and look perfectly healthy while hosting
# nothing. It is turned off in .env and again in the settings table below.
set_env NODE_FAKE false

if ! grep -q '^APP_KEY="\?base64:' .env; then
  "$PHP" artisan key:generate --force --no-interaction
fi

if [ ! -f vendor/autoload.php ]; then
  log "Installing PHP dependencies"
  COMPOSER_ALLOW_SUPERUSER=1 "$PHP" /usr/local/bin/composer install \
    --no-dev --optimize-autoloader --no-interaction --no-progress
fi

# ---------------------------------------------------------------- migrate/seed
log "Migrating"
"$PHP" artisan migrate --force --no-interaction

log "Seeding reference data"
# ONLY the seeders that are reference data a real install needs.
#
#   SettingsSeeder   panel defaults (branding, retention, limits)
#   CatalogueSeeder  the games, templates and template variables a fresh install
#                    ships with, one worked example per runtime
#
# Deliberately NOT run:
#   AccountSeeder         demo logins admin@/staff@/client@/friend@gamemgr.local,
#                         every one of them with the password gamemgr-dev-pass.
#                         Four known-credential accounts on a public box.
#   InfrastructureSeeder  invented locations, nodes, allocations, node metrics,
#                         a database host with credentials, webhooks and
#                         notification channels pointing at nothing.
#   ServerSeeder          fake game servers.
#   ActivitySeeder        fabricated audit log and activity history.
#
# DatabaseSeeder calls all six, so it must never run on a live install.
"$PHP" artisan db:seed --force --no-interaction --class=SettingsSeeder
"$PHP" artisan db:seed --force --no-interaction --class=CatalogueSeeder

# SettingsSeeder writes node_fake=1 and setup_complete=1 for the dev stack.
# AppServiceProvider lets the settings row override config, so the .env switch
# alone is not enough. setup_complete is reset until an admin actually exists:
# setup_complete=1 with no admin closes the wizard and locks the panel out.
"$PHP" artisan tinker --execute="\App\Models\Setting::put('node_fake','0'); \App\Models\Setting::put('setup_complete','0'); \App\Models\Setting::put('update_auto','0');"

log "Creating the first admin"
ADMIN_PASS_GENERATED=0
if [ -z "$ADMIN_PASS" ]; then
  ADMIN_PASS="$(gen_pass 20)"
  ADMIN_PASS_GENERATED=1
fi
"$PHP" artisan gamemgr:create-admin --no-interaction \
  --email="$ADMIN_EMAIL" --password="$ADMIN_PASS" --force \
  || die "Admin creation failed. The panel is still at /setup; create the admin there or re-run with --admin-email."

# ------------------------------------------------------------------- caching
log "Caching config, routes and views"
cache_or_die() {
  "$PHP" artisan "$1" --no-interaction || {
    "$PHP" artisan optimize:clear || true
    die "artisan $1 failed; caches cleared so the app still boots. Fix the error above and re-run."
  }
}
"$PHP" artisan optimize:clear >/dev/null 2>&1 || true
cache_or_die config:cache
cache_or_die route:cache
cache_or_die view:cache

# view:cache exiting 0 does NOT mean the compiled PHP is valid: Blade happily
# compiles constructs that php then refuses to parse, and this codebase has
# shipped exactly that (an inline @php() with nested parentheses). The only
# honest check is to lint every compiled file.
log "Linting compiled views"
LINT_FAIL=0
while IFS= read -r -d '' compiled; do
  if ! out="$("$PHP" -l "$compiled" 2>&1)"; then
    warn "$out"
    LINT_FAIL=$((LINT_FAIL + 1))
  fi
done < <(find storage/framework/views -maxdepth 1 -name '*.php' -print0)
if [ "$LINT_FAIL" -gt 0 ]; then
  "$PHP" artisan view:clear || true
  die "${LINT_FAIL} compiled view(s) are not valid PHP. Compiled cache cleared; fix the Blade above."
fi
echo "    $(find storage/framework/views -maxdepth 1 -name '*.php' | wc -l) compiled views, all parse."

"$PHP" artisan storage:link --no-interaction >/dev/null 2>&1 || true

log "Permissions"
chown -R www-data:www-data "$APP_DIR"
chmod -R u=rwX,g=rX,o= "$APP_DIR"
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod 2775 {} \;
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type f -exec chmod 664 {} \;
chmod 640 "$APP_DIR/.env"

# -------------------------------------------------------------------- nginx
log "Configuring nginx"
rm -f /etc/nginx/sites-enabled/default

# Two maps of the same name are a hard nginx error, so ours is only written if
# nothing else on the box already defines $connection_upgrade.
# shellcheck disable=SC2016  # $http_upgrade is nginx's variable, not the shell's
OTHER_MAP="$(grep -rslE 'map[[:space:]]+\$http_upgrade[[:space:]]+\$connection_upgrade' /etc/nginx 2>/dev/null | grep -vFx "$MAP_SNIPPET" || true)"
if [ -n "$OTHER_MAP" ]; then
  echo "    \$connection_upgrade already mapped by: ${OTHER_MAP}"
  rm -f "$MAP_SNIPPET"
else
  printf '%s\n' "$UPGRADE_MAP" > "$MAP_SNIPPET"
fi

write_vhost http
systemctl enable nginx >/dev/null 2>&1 || true

# --------------------------------------------------------------------- ufw
# An installer that ignores ufw produces a perfectly correct install that
# nobody can reach, with no error anywhere to explain it. Same trap kills
# certbot: HTTP-01 cannot complete if 80 is filtered.
if command -v ufw >/dev/null && ufw status 2>/dev/null | head -n1 | grep -qi 'Status: active'; then
  log "Opening firewall ports (ufw is active)"
  ufw allow 22/tcp  >/dev/null || warn "ufw: could not allow 22/tcp"
  ufw allow 80/tcp  >/dev/null || warn "ufw: could not allow 80/tcp"
  ufw allow 443/tcp >/dev/null || warn "ufw: could not allow 443/tcp"
  ufw status numbered | sed 's/^/    /'
fi

# --------------------------------------------------------- queue + scheduler
log "Installing the queue worker and scheduler"
# QUEUE_CONNECTION is 'database', so the worker is a plain systemd unit with no
# broker to install. It restarts on deploy via `systemctl restart ${SERVICE}`.
cat > "/etc/systemd/system/${SERVICE}.service" <<UNIT
[Unit]
Description=GameMGR queue worker
After=network.target mariadb.service
Requires=mariadb.service

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=${APP_DIR}
ExecStart=${PHP} ${APP_DIR}/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
ExecReload=/bin/kill -USR2 \$MAINPID
StandardOutput=append:${APP_DIR}/storage/logs/queue.log
StandardError=append:${APP_DIR}/storage/logs/queue.log

[Install]
WantedBy=multi-user.target
UNIT
touch "$APP_DIR/storage/logs/queue.log"
chown www-data:www-data "$APP_DIR/storage/logs/queue.log"
systemctl daemon-reload
systemctl enable "$SERVICE" >/dev/null 2>&1 || true
systemctl restart "$SERVICE"

# Scheduler as www-data, so anything it writes (including a self-update
# unpacking a release over the tree) stays owned by the web user.
cat > /etc/cron.d/gamemgr <<CRON
# GameMGR scheduler. Drives node polling, metrics, watchdog and self-update.
SHELL=/bin/sh
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
* * * * * www-data cd ${APP_DIR} && ${PHP} artisan schedule:run >/dev/null 2>&1
CRON
chmod 644 /etc/cron.d/gamemgr

# --------------------------------------------------------------------- TLS
if [ "$USE_SSL" = "1" ]; then
  log "Issuing a Let's Encrypt certificate for ${DOMAIN}"
  apt-get install -y certbot

  # HTTP-01 preflight. Each of these has silently wasted a rate-limited cert
  # attempt on a real VM.
  ssl_ready=1
  if ! ss -ltn 2>/dev/null | grep -qE '(^|[^0-9])(0\.0\.0\.0|\[::\]|\*):80\b'; then
    warn "Nothing is listening on port 80; HTTP-01 cannot complete."
    ssl_ready=0
  fi
  resolved="$(getent ahostsv4 "$DOMAIN" 2>/dev/null | awk 'NR==1{print $1}')"
  myip="$(curl -fsS4 --max-time 5 https://ifconfig.me 2>/dev/null || true)"
  if [ -z "$resolved" ]; then
    warn "${DOMAIN} does not resolve; point its A record at this box first."
    ssl_ready=0
  elif [ -n "$myip" ] && [ "$resolved" != "$myip" ]; then
    warn "${DOMAIN} resolves to ${resolved} but this box is ${myip}. If that is not a proxy, HTTP-01 will fail."
  fi

  # certonly, not the --nginx installer: this script rewrites the vhost from
  # scratch on every run, so anything certbot edited into it would be lost on
  # the next run. certbot owns the certificate, the installer owns the config.
  # --keep-until-expiring makes a re-run a no-op instead of burning a rate-limit
  # slot on a duplicate issuance.
  if [ "$ssl_ready" = "1" ] && certbot certonly --webroot -w "${APP_DIR}/public" \
        -d "$DOMAIN" --non-interactive --agree-tos -m "$EMAIL" \
        --keep-until-expiring --deploy-hook 'systemctl reload nginx'; then
    rm -rf "${APP_DIR}/public/.well-known/acme-challenge"
    write_vhost https
    echo "    certificate installed; renewal is handled by certbot.timer"
    echo "    HTTP on port 80 now redirects to HTTPS (except /.well-known/acme-challenge/)"
  else
    USE_SSL=0
    APP_URL="http://${DOMAIN}"
    # .env was written for https; put it back or every generated URL, including
    # the console stream URL, points at a scheme the box does not serve.
    set_env APP_URL "$APP_URL"
    warn "certbot did not run or failed. The panel is up on HTTP."
    warn "Fix DNS and port 80, then re-run this installer, or issue by hand:"
    warn "  certbot certonly --webroot -w ${APP_DIR}/public -d ${DOMAIN} -m ${EMAIL} --agree-tos"
  fi
fi

# --------------------------------------------------------------------- done
nginx -t || die "nginx config is invalid at the end of the run."
systemctl reload nginx || systemctl restart nginx
"$PHP" artisan config:cache --no-interaction >/dev/null
chown -R www-data:www-data "$APP_DIR/bootstrap/cache" "$APP_DIR/storage"

svc_state() { systemctl is-active "$1" 2>/dev/null || echo "unknown"; }

log "GameMGR ${APP_VERSION} installed"
cat <<SUMMARY

  URL             ${APP_URL}
  Admin login     ${ADMIN_EMAIL}
  Admin password  ${ADMIN_PASS}$([ "$ADMIN_PASS_GENERATED" = 1 ] && echo "   (generated, shown once)")

  Install dir     ${APP_DIR}
  PHP             ${PHP_VER}  (${PHP})
  Database        ${DB_NAME} / ${DB_USER} @ ${DB_HOST}   (password in ${APP_DIR}/.env)

  Services        nginx              $(svc_state nginx)
                  php${PHP_VER}-fpm       $(svc_state "php${PHP_VER}-fpm")
                  mariadb            $(svc_state mariadb)
                  ${SERVICE}      $(svc_state "$SERVICE")
                  scheduler          /etc/cron.d/gamemgr (every minute)

  Live console    nginx proxies ${APP_URL}/daemon/ to the node daemon on
                  ${DAEMON_HOST}:${DAEMON_PORT}, SSE buffering off. The browser only ever
                  talks to this origin, so an HTTPS panel never trips mixed
                  content. Point --node-port elsewhere if the daemon moves.
  Node data       NODE_FAKE=false and node_fake=0 -- unreachable nodes report
                  offline instead of inventing metrics.
  Seeded          settings + game catalogue only. No demo accounts, no demo
                  nodes, no demo servers.

  Next            Log in, then Admin > Nodes > Add Node and run the node
                  installer on the machine that will host game servers.

SUMMARY
if [ "$ADMIN_PASS_GENERATED" = 1 ]; then
  echo "  Save that password now; it is not stored anywhere in plain text."
  echo
fi
