#!/bin/sh
# Runs on the database host as root, from /srv/scarlet-db/deploy: `bin/deploy db-server` copies the deploy folder there and
# calls it with the pgBackRest repository path (default pgbackrest, a folder in the R2 bucket) and the private addresses of
# the app droplets allowed to connect. It reads `DB_PASSWORD=` and `PGBR_PASS=` lines on stdin and is safe to run again.
#
# It writes /srv/scarlet-db/.env, the TLS key pair of Postgres, pg_hba.conf, pgbackrest.conf and the systemd units. It does not
# start Postgres and does not enable the backup timers: deploy/db-host.sh does both once the database exists.
set -eu
repo_path=${1:-pgbackrest}
shift || true
clients=$*
base=/srv/scarlet-db
cd "$base"
umask 077

while IFS='=' read -r key value; do
  case $key in
    DB_PASSWORD) DB_PASSWORD=$value ;;
    PGBR_PASS) PGBR_PASS=$value ;;
  esac
done
[ -f .env ] && [ -z "${DB_PASSWORD:-}" ] && DB_PASSWORD=$(sed -n 's/^DB_PASSWORD=//p' .env)
[ -n "${DB_PASSWORD:-}" ] || { echo "server-db: no DB_PASSWORD" >&2; exit 1; }
[ -n "$clients" ] || { echo "server-db: no client address" >&2; exit 1; }
[ -f /etc/scarlet-web/r2.env ] || { echo "server-db: /etc/scarlet-web/r2.env is missing (bin/deploy r2)" >&2; exit 1; }

private=$(curl -fsS http://169.254.169.254/metadata/v1/interfaces/private/0/ipv4/address)
[ -n "$private" ] || { echo "server-db: no VPC address" >&2; exit 1; }

printf 'DB_PASSWORD=%s\nDB_NAME=scarlet\nDB_LISTEN=%s,127.0.0.1\n' "$DB_PASSWORD" "$private" > .env.new
chown ops:ops .env.new
mv .env.new .env

install -d -m 755 etc
install -d -m 700 -o 1000 -g 1000 etc/tls
if [ ! -f etc/tls/server.key ]; then
  openssl req -x509 -newkey ec -pkeyopt ec_paramgen_curve:prime256v1 -nodes -days 3650 -subj "/CN=scarlet-db" \
    -keyout etc/tls/server.key -out etc/tls/server.crt 2>/dev/null
  echo "server-db: made the TLS key pair"
fi
chown 1000:1000 etc/tls/server.key etc/tls/server.crt
chmod 600 etc/tls/server.key
chmod 644 etc/tls/server.crt

{
  echo '# written by deploy/server-setup-db.sh'
  echo 'local   all             all                                     trust'
  echo 'local   replication     all                                     trust'
  for c in $clients; do
    echo "hostssl all             all             $c/32            scram-sha-256"
    echo "hostssl replication     replicator      $c/32            scram-sha-256"
  done
} > etc/pg_hba.conf
chmod 644 etc/pg_hba.conf

if [ -n "${PGBR_PASS:-}" ]; then
  (
    set -a
    . /etc/scarlet-web/r2.env
    set +a
    endpoint=$(echo "$RCLONE_CONFIG_R2_ENDPOINT" | sed 's#^https\?://##')
    cat > etc/pgbackrest.conf <<EOF
[global]
repo1-type=s3
repo1-path=/$repo_path
repo1-s3-bucket=$R2_BUCKET
repo1-s3-endpoint=$endpoint
repo1-s3-region=auto
repo1-s3-uri-style=path
repo1-s3-key=$RCLONE_CONFIG_R2_ACCESS_KEY_ID
repo1-s3-key-secret=$RCLONE_CONFIG_R2_SECRET_ACCESS_KEY
repo1-cipher-type=aes-256-cbc
repo1-cipher-pass=$PGBR_PASS
repo1-retention-full=2
compress-type=zst
log-path=/tmp
log-level-file=warn
log-level-console=info
start-fast=y
process-max=4

[scarlet]
pg1-path=/home/postgres/pgdata/data
pg1-socket-path=/var/run/postgresql
pg1-user=postgres
EOF
  )
  chown 1000:1000 etc/pgbackrest.conf
  chmod 600 etc/pgbackrest.conf
  echo "server-db: wrote pgbackrest.conf (repository /$repo_path)"
fi
[ -f etc/pgbackrest.conf ] || { echo "server-db: no pgbackrest.conf yet and no PGBR_PASS on stdin" >&2; exit 1; }

sh deploy/install-backup-tools.sh
chmod +x deploy/dc-db deploy/backup.sh deploy/db-host.sh
install -d -o ops -g ops -m 750 backups
cp deploy/systemd-db/*.service deploy/systemd-db/*.timer /etc/systemd/system/
systemctl daemon-reload
echo "server-db: ready ($private)"
