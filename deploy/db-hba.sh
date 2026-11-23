#!/bin/sh
# Runs on the database host as root, from /srv/scarlet-db/deploy: `db-hba.sh <address>...` writes etc/pg_hba.conf for the app
# hosts at those VPC addresses and, when Postgres runs, makes it read the file again. deploy/server-setup-db.sh calls it
# with one address, `bin/deploy hosts` with one per app host.
set -eu
[ "$#" -gt 0 ] || { echo "db-hba: no client address" >&2; exit 1; }
base=/srv/scarlet-db
cd "$base"
umask 022
install -d -m 755 etc
{
  echo '# written by deploy/server-setup-db.sh'
  echo 'local   all             all                                     trust'
  echo 'local   replication     all                                     trust'
  for c in "$@"; do
    echo "hostssl scarlet         scarlet_app     $c/32            scram-sha-256"
    echo "hostssl all             postgres        $c/32            scram-sha-256"
    echo "hostssl replication     replicator      $c/32            scram-sha-256"
  done
} > etc/pg_hba.conf
chmod 644 etc/pg_hba.conf
if deploy/dc-db ps --status running --services 2>/dev/null | grep -qx postgres; then
  deploy/dc-db exec -T postgres psql -X -U postgres -d scarlet -Atq -c 'select pg_reload_conf()' >/dev/null
  echo "db-hba: Postgres reads pg_hba.conf again ($*)"
fi
