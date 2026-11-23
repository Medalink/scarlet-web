#!/bin/bash
# Runs on the app droplet as ops (rollout.sh calls it before every migrate, and the owner once on an older install), or on
# the database host through deploy/server-setup-db.sh: makes the role `scarlet_app` the owner of the app database and of
# everything in it, with a login password and without superuser, CREATEDB or REPLICATION (docs/HOSTING.md, The database
# role). The app connects as it; `postgres` stays the superuser for setup, restore and pg_dump. Safe to run again: the
# role and the objects it already owns are kept, and the password always follows DB_PASSWORD of the local .env.
# It talks to the local Postgres of the compose wrapper named as the first argument (deploy/dc) when that runs, else over
# TLS to DB_HOST with the connection settings the app itself uses.
set -euo pipefail
cd "${SCARLET_BASE:-/srv/scarlet-web}"
dc=${1:-deploy/dc}

env_value() { sed -n "s/^$1=//p" .env | tail -n 1; }
db=$(env_value DB_DATABASE)
db=${db:-scarlet}
password=$(env_value DB_PASSWORD)
[ -n "$password" ] || { echo "db-app-role: no DB_PASSWORD in $PWD/.env" >&2; exit 1; }

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
alter database :"db" owner to scarlet_app;
reassign owned by postgres to scarlet_app;
SQL
)

host=$(env_value DB_HOST)
if [ -n "$host" ] && [ "$host" != postgres ]; then
  port=$(env_value DB_PORT)
  sslmode=$(env_value DB_SSLMODE)
  port=${port:-5432}
  sslmode=${sslmode:-require}
  conninfo="host=$host port=$port user=postgres dbname=$db sslmode=$sslmode connect_timeout=5"
  image=$("$dc" config --images | grep timescaledb-ha | head -n 1)
  docker_args=(--rm -i --network scarlet-web_backend -e PGPASSWORD="$password" --entrypoint psql)
  if [ "$sslmode" = verify-ca ] && [ -f dbtls/db-ca.crt ]; then
    docker_args+=(-v "$PWD/dbtls/db-ca.crt:/etc/scarlet-dbtls/db-ca.crt:ro")
    conninfo="$conninfo sslrootcert=/etc/scarlet-dbtls/db-ca.crt"
  fi
  docker run "${docker_args[@]}" "$image" -X -v ON_ERROR_STOP=1 -v db="$db" -v pw="$password" "$conninfo" <<<"$sql"
elif "$dc" ps --status running --services 2>/dev/null | grep -qx postgres; then
  "$dc" exec -T postgres psql -X -v ON_ERROR_STOP=1 -v db="$db" -v pw="$password" -U postgres -d "$db" <<<"$sql"
elif [ -z "$host" ]; then
  exit 0
else
  echo "db-app-role: the local Postgres is not running; a rollout starts it, or point DB_HOST at the server first" >&2
  exit 1
fi
