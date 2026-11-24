#!/bin/bash
# Runs on the machine that holds the Postgres container, as ops: on the database host through deploy/server-setup-db.sh
# (`bin/deploy db-server`), or on an app droplet whose DB_HOST is `postgres` (rollout.sh calls it before every migrate
# there). It makes the role `scarlet_app` the owner of the app database and of everything in it, with a login password
# and without superuser, CREATEDB or REPLICATION (docs/HOSTING.md, The database role). The app connects as it. `postgres`
# stays the superuser for setup, restore and pg_dump, reachable through the container's local socket only, with a
# password of its own: DB_ADMIN_PASSWORD of the local .env, which must differ from DB_PASSWORD. Safe to run again: the
# role and the objects it already owns are kept, and both passwords always follow the local .env. It never connects to
# another host: the app droplet holds no superuser password, and pg_hba.conf lets `postgres` in from the machine only.
# SCARLET_KEEP_POSTGRES_PASSWORD=1 leaves the password of `postgres` alone, for an app that still logs in as it.
# Ownership moves object by object in one transaction: `reassign owned by postgres` cannot work, because the bootstrap
# superuser owns the system catalogs, and timescaledb.restoring lets the owner of a continuous aggregate's internal
# tables and of every chunk change the way a restore does. Objects of an extension stay with `postgres`.
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
begin;
set local lock_timeout = '10s';
set local timescaledb.restoring = on;
do $$
declare
  stmt text;
begin
  for stmt in
    with app_schemas as (
      select n.oid, n.nspname from pg_namespace n
      where n.nspname not like 'pg\_%' and n.nspname <> 'information_schema'
        and not exists (select from pg_depend d where d.classid = 'pg_namespace'::regclass and d.objid = n.oid and d.deptype = 'e')
    )
    select format('alter schema %I owner to scarlet_app', s.nspname) from app_schemas s join pg_namespace n on n.oid = s.oid
    where n.nspowner = 'postgres'::regrole
    union all
    select format('alter table %I.%I owner to scarlet_app', n.nspname, c.relname)
    from pg_class c join pg_namespace n on n.oid = c.relnamespace
    where c.relowner = 'postgres'::regrole and c.relkind in ('r', 'p', 'f', 'v', 'm', 'S')
      and n.nspname not like 'pg\_%' and n.nspname <> 'information_schema'
      and not exists (select from pg_depend d where d.classid = 'pg_class'::regclass and d.objid = c.oid and d.deptype = 'e')
      and not (c.relkind = 'S' and exists (select from pg_depend d where d.classid = 'pg_class'::regclass and d.objid = c.oid and d.deptype in ('a', 'i')))
    union all
    select format('alter routine %s owner to scarlet_app', p.oid::regprocedure) from pg_proc p join app_schemas s on s.oid = p.pronamespace
    where p.proowner = 'postgres'::regrole
      and not exists (select from pg_depend d where d.classid = 'pg_proc'::regclass and d.objid = p.oid and d.deptype = 'e')
    union all
    select format('alter %s %s owner to scarlet_app', case t.typtype when 'd' then 'domain' else 'type' end, t.oid::regtype)
    from pg_type t join app_schemas s on s.oid = t.typnamespace
    where t.typowner = 'postgres'::regrole and t.typtype in ('e', 'd', 'r', 'c')
      and (t.typtype <> 'c' or (select c.relkind from pg_class c where c.oid = t.typrelid) = 'c')
      and not exists (select from pg_depend d where d.classid = 'pg_type'::regclass and d.objid = t.oid and d.deptype = 'e')
  loop
    execute stmt;
  end loop;
end $$;
update _timescaledb_config.bgw_job set owner = 'scarlet_app' where owner = 'postgres'::regrole and hypertable_id is not null;
commit;
SQL
)

host=$(env_value DB_HOST)
if [ -n "$host" ] && [ "$host" != postgres ]; then
  echo "db-app-role: DB_HOST is $host; run this on the database host (bin/deploy db-server)" >&2
  exit 1
fi
if "$dc" ps --status running --services 2>/dev/null | grep -qx postgres; then
  args=(-v db="$db" -v pw="$password")
  [ -z "$admin" ] || [ "${SCARLET_KEEP_POSTGRES_PASSWORD:-}" = 1 ] || args+=(-v admin="$admin")
  "$dc" exec -T postgres psql -X -v ON_ERROR_STOP=1 "${args[@]}" -U postgres -d "$db" <<<"$sql"
elif [ -z "$host" ]; then
  exit 0
else
  echo "db-app-role: the local Postgres is not running; a rollout starts it" >&2
  exit 1
fi
