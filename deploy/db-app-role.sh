#!/bin/bash
# Runs on the machine that holds the Postgres container, as ops: on the database host through deploy/server-setup-db.sh
# (`bin/deploy db-server`), or on an app droplet whose DB_HOST is `postgres` (rollout.sh calls it before every migrate
# there). It makes the role `scarlet_app` the owner of the app database and of everything in it, with a login password
# and without superuser, CREATEDB or REPLICATION (docs/HOSTING.md, The database role). The app connects as it. `postgres`
# stays the superuser for setup, restore and pg_dump, reachable through the container's local socket only, with a
# password of its own: DB_ADMIN_PASSWORD of the local .env, which must differ from DB_PASSWORD. Safe to run again: the
# role and the objects it already owns are kept, and both passwords always follow the local .env. It never connects to
# another host: the app droplet holds no superuser password, and pg_hba.conf lets `postgres` in from the machine only.
set -euo pipefail
cd "${SCARLET_BASE:-/srv/scarlet-web}"
dc=${1:-deploy/dc}

env_value() { sed -n "s/^$1=//p" .env | tail -n 1; }
db=$(env_value DB_DATABASE)
db=${db:-scarlet}
password=$(env_value DB_PASSWORD)
admin=$(env_value DB_ADMIN_PASSWORD)
[ -n "$password" ] || { echo "db-app-role: no DB_PASSWORD in $PWD/.env" >&2; exit 1; }
[ "$admin" != "$password" ] || { echo "db-app-role: DB_ADMIN_PASSWORD must differ from DB_PASSWORD" >&2; exit 1; }

sql=$(cat <<'SQL'
do $$
begin
  if not exists (select from pg_roles where rolname = 'scarlet_app') then
    create role scarlet_app login nosuperuser nocreatedb nocreaterole;
  end if;
end $$;
create extension if not exists timescaledb;
create extension if not exists timescaledb_toolkit;
create extension if not exists pg_trgm;
alter role scarlet_app password :'pw';
\if :{?admin}
alter role postgres password :'admin';
\endif
alter database :"db" owner to scarlet_app;
reassign owned by postgres to scarlet_app;
SQL
)

host=$(env_value DB_HOST)
if [ -n "$host" ] && [ "$host" != postgres ]; then
  echo "db-app-role: DB_HOST is $host; run this on the database host (bin/deploy db-server)" >&2
  exit 1
fi
if "$dc" ps --status running --services 2>/dev/null | grep -qx postgres; then
  args=(-v db="$db" -v pw="$password")
  [ -z "$admin" ] || args+=(-v admin="$admin")
  "$dc" exec -T postgres psql -X -v ON_ERROR_STOP=1 "${args[@]}" -U postgres -d "$db" <<<"$sql"
elif [ -z "$host" ]; then
  exit 0
else
  echo "db-app-role: the local Postgres is not running; a rollout starts it" >&2
  exit 1
fi
