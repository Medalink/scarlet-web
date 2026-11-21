#!/bin/sh
# Runs on the droplet as ops, from /srv/scarlet-web/deploy: `bin/deploy server` copies the deploy folder there and
# calls it. It is safe to run again; it never replaces a secret that exists.
set -eu
base=/srv/scarlet-web
cd "$base"
install -d -m 750 tls maps backups
umask 077

# The environment of every container. Secrets are made here and stay here.
if [ ! -f .env ]; then
  {
    cat <<EOF
APP_NAME=Scarlet
APP_ENV=production
APP_KEY=base64:$(openssl rand -base64 32)
APP_DEBUG=false
APP_URL=https://scarletweb.io
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single,stderr
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=scarlet
DB_USERNAME=postgres
DB_PASSWORD=$(openssl rand -hex 24)

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=$(openssl rand -hex 24)
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_PREFIX=scarlet_
CACHE_PREFIX=scarlet_cache_
HORIZON_PREFIX=scarlet_horizon:

SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_SECURE_COOKIE=true
SESSION_COOKIE=scarlet_session
CACHE_STORE=redis
QUEUE_CONNECTION=redis
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local

SCARLET_MIN_CHARACTERS=5
SCARLET_PART_BIN=/usr/local/bin/scarlet-part
SCARLET_DEV_LOGIN=false
MAPS_ROOT=/maps

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
BATTLENET_CLIENT_ID=
BATTLENET_CLIENT_SECRET=
BATTLENET_REGION=us
EOF
  } > .env
  chmod 600 .env
  echo "server: wrote .env (mode 600)"
fi
chmod 600 .env

# Authenticated Origin Pulls stays off until the zone setting is on (bin/deploy aop on). rollout.sh keeps the live
# switches of nginx in state/.
install -d state
[ -f state/aop.conf ] || cp deploy/nginx/aop-off.conf state/aop.conf
cp deploy/nginx/origin-pull-ca.pem tls/origin-pull-ca.pem

# The origin key is made on the server and never leaves it; only the request for a certificate does.
if [ ! -f tls/origin.key ]; then
  openssl ecparam -genkey -name prime256v1 -noout -out tls/origin.key
  chmod 600 tls/origin.key
  echo "server: made tls/origin.key"
fi
openssl req -new -key tls/origin.key -subj "/CN=scarletweb.io" -out tls/origin.csr
chmod 644 tls/origin.csr tls/origin-pull-ca.pem

# age and rclone for the off-site backup: the versions in deploy/backup-tools.tsv, checked against its sha256 before
# anything is installed. The R2 credentials are not here: `bin/deploy r2` writes /etc/scarlet-web/r2.env.
tool_row() { awk -F'\t' -v t="$1" '$1 == t' deploy/backup-tools.tsv; }
fetch_tool() { # <tool> <file>
  url=$(tool_row "$1" | cut -f3)
  sum=$(tool_row "$1" | cut -f4)
  curl -fsSL -o "$2" "$url"
  echo "$sum  $2" | sha256sum -c - >/dev/null || { echo "server: $1 download does not match its pinned sha256" >&2; exit 1; }
}
tmp=$(mktemp -d)
want_age=$(tool_row age | cut -f2)
if [ "$(age --version 2>/dev/null || true)" != "v$want_age" ]; then
  fetch_tool age "$tmp/age.tgz"
  tar -C "$tmp" -xzf "$tmp/age.tgz"
  sudo install -m 755 "$tmp/age/age" /usr/local/bin/age
  echo "server: installed age $want_age"
fi
want_rclone=$(tool_row rclone | cut -f2)
if [ "$(rclone version 2>/dev/null | sed -n '1s/^rclone v//p')" != "$want_rclone" ]; then
  fetch_tool rclone "$tmp/rclone.deb"
  sudo dpkg -i "$tmp/rclone.deb" >/dev/null
  echo "server: installed rclone $want_rclone"
fi
rm -rf "$tmp"
sudo install -d -m 755 /etc/scarlet-web

# The nightly dump.
chmod +x deploy/dc deploy/backup.sh deploy/rollout.sh
sudo cp deploy/systemd/scarlet-backup.service deploy/systemd/scarlet-backup.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now scarlet-backup.timer >/dev/null
echo "server: ready"
