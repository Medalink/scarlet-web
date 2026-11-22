#!/bin/bash
# Runs on the app droplet as ops, from /srv/scarlet-web. deploy/db-move.sh (bin/deploy db-move) calls it over ssh; it is
# the part of a database move that has to run next to the old Postgres, with no ssh hop between two of its steps.
#
#   db-cutover.sh expose                  let a standby on the database host copy the local Postgres: the role `replicator`, one
#                                         pg_hba line for the compose network, the slot scarlet_db_move and a TCP relay on this
#                                         droplet's Tailscale address (port 15432). Prints the Tailscale address.
#   db-cutover.sh unexpose                remove the relay and the slot
#   db-cutover.sh check <ip>              connect to the database host the way the app does and print ssl, the client address and the role
#   db-cutover.sh set-env <ip|local>      point DB_HOST (and DB_SSLMODE=require) of .env at the database host, or back at the
#                                         compose service `postgres`. New containers read it; running ones keep theirs.
#   db-cutover.sh cutover new|old <ip>    move the writer: the local Postgres becomes read-only, the standby at <ip> catches up
#                                         and is promoted, visitors move to the prepared slot (rollout.sh --switch). `old` goes
#                                         the other way, from the database host back to the local Postgres.
#   db-cutover.sh reseed <ip>             make the local Postgres a standby of the primary at <ip> (the reverse replica)
#   db-cutover.sh retire                  remove the reverse replica once the database host has proven itself
#   db-cutover.sh status
#
#   db-cutover.sh cutover-start new|old <ip>   run `cutover` detached from the ssh session; it writes state/cutover.log and, when it
#                                         ends, its exit code to state/cutover.code
#   db-cutover.sh cutover-state           print RUNNING, DONE <code> or LOST; cutover-log prints the log
#
# Exit codes of `cutover`: 0 moved, 10 nothing moved (the writer is where it was and writable again), 20 promoted but the
# switch of visitors failed or did not happen, 21 promotion requested and not confirmed (the old writer stays read-only).
set -euo pipefail
cd /srv/scarlet-web
dc=deploy/dc
state=state/db-move.env
umask 077

env_value() { sed -n "s/^$1=//p" .env | tail -n 1; }
password=$(env_value DB_PASSWORD)
local_pg() { $dc exec -T postgres psql -X -U postgres -d scarlet -Atq "$@"; }
remote_pg() { $dc exec -T -e PGPASSWORD="$password" postgres psql -X "host=$1 port=5432 user=postgres dbname=scarlet sslmode=require connect_timeout=5" -Atq "${@:2}"; }
image() { $dc config --images | grep "$1" | head -n 1; }
now() { date +%s.%N; }
running_horizons() {
  for service in horizon horizon-blue horizon-green horizon-a horizon-b horizon-c; do
    docker ps -q --filter label=com.docker.compose.project=scarlet-web --filter label=com.docker.compose.service="$service"
  done
}
elapsed() { awk -v a="$1" -v b="$(now)" 'BEGIN { printf "%.2f", b - a }'; }

replicator_password() {
  [ -f state/replicator.pass ] || openssl rand -hex 24 > state/replicator.pass
  cat state/replicator.pass
}

expose() {
  local pass subnet line hba ts
  pass=$(replicator_password)
  ts=$(tailscale ip -4 | head -n 1)
  [ -n "$ts" ] || { echo "db-cutover: this droplet has no Tailscale address" >&2; exit 1; }
  local_pg -c "do \$\$ begin if not exists (select from pg_roles where rolname = 'replicator') then create role replicator login replication; end if; end \$\$" \
    -c "alter role replicator password '$pass'" >/dev/null
  subnet=$(docker network inspect scarlet-web_backend --format '{{range .IPAM.Config}}{{.Subnet}}{{end}}')
  line="host replication replicator $subnet scram-sha-256"
  hba=$(local_pg -c 'show hba_file')
  $dc exec -T postgres sh -c "grep -qxF '$line' '$hba' || echo '$line' >> '$hba'"
  local_pg -c 'select pg_reload_conf()' >/dev/null
  [ "$(local_pg -c "select count(*) from pg_hba_file_rules where error is not null")" = 0 ] || { echo "db-cutover: pg_hba.conf has an error" >&2; exit 1; }
  local_pg -c "select pg_create_physical_replication_slot('scarlet_db_move', true) where not exists (select from pg_replication_slots where slot_name = 'scarlet_db_move')" >/dev/null
  cat > state/dbrelay.conf <<EOF
worker_processes 1;
pid /tmp/relay.pid;
error_log /dev/stderr warn;
events { worker_connections 64; }
stream { server { listen 15432; proxy_pass postgres:5432; } }
EOF
  docker rm -f scarlet-web-dbrelay >/dev/null 2>&1 || true
  docker run -d --name scarlet-web-dbrelay --network scarlet-web_backend -p "$ts:15432:15432" -v "$PWD/state/dbrelay.conf:/etc/nginx/relay.conf:ro" \
    "$(image nginx)" nginx -c /etc/nginx/relay.conf -g 'daemon off;' >/dev/null
  echo "$ts"
}

unexpose() {
  local line subnet hba
  docker rm -f scarlet-web-dbrelay >/dev/null 2>&1 || true
  rm -f state/dbrelay.conf
  if $dc ps --status running --services | grep -qx postgres && [ "$(local_pg -c 'select pg_is_in_recovery()')" = f ]; then
    local_pg -c "select pg_drop_replication_slot('scarlet_db_move') where exists (select from pg_replication_slots where slot_name = 'scarlet_db_move' and not active)" >/dev/null || true
    subnet=$(docker network inspect scarlet-web_backend --format '{{range .IPAM.Config}}{{.Subnet}}{{end}}')
    line="host replication replicator $subnet scram-sha-256"
    hba=$(local_pg -c 'show hba_file')
    $dc exec -T postgres sh -c "grep -vxF '$line' '$hba' > '$hba.new'; cat '$hba.new' > '$hba'; rm -f '$hba.new'"
    local_pg -c 'select pg_reload_conf()' >/dev/null
  fi
}

check() {
  remote_pg "$1" -c "select 'ok: ssl ' || (select ssl::text from pg_stat_ssl where pid = pg_backend_pid()) || ', client ' || inet_client_addr() || ', standby ' || pg_is_in_recovery()"
}

set_env() {
  local target=$1
  [ -f state/env.before-db-move ] || cp .env state/env.before-db-move
  grep -v -E '^(DB_HOST|DB_SSLMODE|DB_SSLROOTCERT)=' .env > .env.new
  if [ "$target" = local ]; then
    echo 'DB_HOST=postgres' >> .env.new
  elif [ -s dbtls/db-ca.crt ]; then
    printf 'DB_HOST=%s\nDB_SSLMODE=verify-ca\nDB_SSLROOTCERT=/etc/scarlet-dbtls/db-ca.crt\n' "$target" >> .env.new
  else
    printf 'DB_HOST=%s\nDB_SSLMODE=require\n' "$target" >> .env.new
  fi
  mv .env.new .env
  echo "db-cutover: .env now has DB_HOST=$(env_value DB_HOST) DB_SSLMODE=$(env_value DB_SSLMODE)"
}

quiesce() {
  local cut n stale
  for _ in $(seq 1 50); do
    [ "$($src -c 'show default_transaction_read_only')" = on ] && break
    sleep 0.1
  done
  [ "$($src -c 'show default_transaction_read_only')" = on ] || { echo "cutover: new sessions are not read-only" >&2; return 1; }
  sleep 0.3
  cut=$($src -c "select clock_timestamp()")
  stale="from pg_stat_activity where xact_start < '$cut'::timestamptz and pid <> pg_backend_pid() and backend_type <> 'walsender'"
  for _ in $(seq 1 30); do
    n=$($src -c "select count(*) $stale")
    [ "$n" = 0 ] && return 0
    sleep 0.1
  done
  echo "cutover: $n transactions that began before read-only are still open after 3 s; ending them"
  $src -c "select pg_terminate_backend(pid) $stale" >/dev/null
  for _ in $(seq 1 30); do
    n=$($src -c "select count(*) $stale")
    [ "$n" = 0 ] && return 0
    sleep 0.1
  done
  echo "cutover: $n backends would not end" >&2
  return 1
}

cutover() {
  local direction=$1 ip=$2 t0 target
  promoted=
  promote_sent=
  sched=
  paused=
  case $direction in
    new) src=local_pg; dst="remote_pg $ip" ;;
    old) src="remote_pg $ip"; dst=local_pg ;;
    *) echo "db-cutover: new or old" >&2; exit 2 ;;
  esac
  [ "$($dst -c 'select pg_is_in_recovery()')" = t ] || { echo "db-cutover: the target is not a standby" >&2; exit 10; }
  [ "$($src -c 'select pg_is_in_recovery()')" = f ] || { echo "db-cutover: the source is not a primary" >&2; exit 10; }
  [ -s state/prepared ] || { echo "db-cutover: no slot is prepared (rollout.sh --prepare)" >&2; exit 10; }

  abort() {
    [ -z "$promoted" ] || return 0
    if [ -n "$promote_sent" ]; then
      if [ "$($dst -c 'select pg_is_in_recovery()' 2>/dev/null || echo unknown)" = f ]; then
        echo "db-cutover: the target became a primary after the promotion call; the source stays read-only and nothing restarts" >&2
        exit 20
      fi
      echo "db-cutover: promotion was requested and is not confirmed; the source stays read-only and Horizon and the scheduler stay paused" >&2
      exit 21
    fi
    echo "db-cutover: failed before promotion; the writer stays where it was" >&2
    $src -c "alter system reset default_transaction_read_only" -c "select pg_reload_conf()" >/dev/null 2>&1 || true
    [ -z "$paused$sched" ] || docker start $paused $sched >/dev/null 2>&1 || true
    exit 10
  }
  trap abort EXIT

  paused=$(running_horizons)
  sched=$(docker ps -q --filter label=com.docker.compose.project=scarlet-web --filter label=com.docker.compose.service=schedule)
  [ -z "$paused" ] || docker stop -t 150 $paused >/dev/null
  [ -z "$sched" ] || docker stop $sched >/dev/null
  echo "cutover: horizon and schedule paused (jobs wait in Redis)"
  t0=$(now)
  $src -c "alter system set default_transaction_read_only = on" -c "select pg_reload_conf()" >/dev/null
  quiesce || exit 10
  target=$($src -c "select pg_current_wal_insert_lsn()")
  for i in $(seq 1 300); do
    [ "$($dst -c "select pg_last_wal_replay_lsn() >= '$target'::pg_lsn")" = t ] && break
    [ "$i" = 300 ] && { echo "cutover: the standby did not reach $target in 30 s" >&2; exit 10; }
    sleep 0.1
  done
  echo "cutover: standby replayed to $target after $(elapsed "$t0") s of read-only"
  promote_sent=1
  $dst -c "select pg_promote(true, 60)" >/dev/null || true
  [ "$($dst -c 'select pg_is_in_recovery()')" = f ] || { echo "cutover: promotion did not finish" >&2; exit 10; }
  promoted=1
  echo "cutover: promoted after $(elapsed "$t0") s"
  trap - EXIT
  if ! deploy/rollout.sh --switch; then
    echo "cutover: the new writer is promoted but the switch of visitors failed" >&2
    exit 20
  fi
  echo "cutover: visitors moved; writes were refused for about $(elapsed "$t0") s"
}

writable() {
  $dc ps --status running --services | grep -qx postgres || return 0
  [ "$(local_pg -c 'select pg_is_in_recovery()')" = f ] || return 0
  local_pg -c "alter system reset default_transaction_read_only" -c "select pg_reload_conf()" >/dev/null
  echo "db-cutover: the local Postgres accepts writes"
}

cutover_start() {
  rm -f state/cutover.code state/cutover.pid
  setsid nohup bash -c 'echo $$ > state/cutover.pid; deploy/db-cutover.sh cutover "$1" "$2" > state/cutover.log 2>&1; echo $? > state/cutover.code.tmp; mv state/cutover.code.tmp state/cutover.code' _ "$1" "$2" > /dev/null 2>&1 < /dev/null &
  for _ in $(seq 1 50); do
    [ -s state/cutover.pid ] && break
    sleep 0.1
  done
  [ -s state/cutover.pid ] || { echo "db-cutover: the cutover did not start" >&2; exit 1; }
  echo started
}

cutover_state() {
  if [ -f state/cutover.code ]; then echo "DONE $(cat state/cutover.code)"
  elif kill -0 "$(cat state/cutover.pid 2>/dev/null)" 2>/dev/null; then echo RUNNING
  elif [ -f state/cutover.code ]; then echo "DONE $(cat state/cutover.code)"
  else echo LOST
  fi
}

reseed() {
  local ip=$1 pass img
  pass=$(replicator_password)
  img=$(image timescaledb-ha)
  remote_pg "$ip" -c "select pg_create_physical_replication_slot('scarlet_reverse', true) where not exists (select from pg_replication_slots where slot_name = 'scarlet_reverse')" >/dev/null
  $dc stop -t 60 postgres
  docker run --rm --user 1000:1000 -v scarlet-web_pgdata:/home/postgres/pgdata --entrypoint sh "$img" -c \
    'cd /home/postgres/pgdata && if [ -d data.pre-move ]; then rm -rf data; else mv data data.pre-move; fi'
  docker run --rm --user 1000:1000 -e PGPASSWORD="$pass" -e PGSSLMODE=require -v scarlet-web_pgdata:/home/postgres/pgdata --entrypoint pg_basebackup "$img" \
    -h "$ip" -p 5432 -U replicator -D /home/postgres/pgdata/data -X stream -S scarlet_reverse --checkpoint=fast --no-password
  docker run --rm -i --user 1000:1000 -e HOSTVAL="$ip" -e PASSVAL="$pass" -v scarlet-web_pgdata:/home/postgres/pgdata --entrypoint sh "$img" -s <<'EOF'
set -e
cd /home/postgres/pgdata/data
touch standby.signal
grep -v -E '^(primary_conninfo|primary_slot_name|default_transaction_read_only)' postgresql.auto.conf > postgresql.auto.conf.new || true
{
  cat postgresql.auto.conf.new
  echo "primary_conninfo = 'host=$HOSTVAL port=5432 user=replicator password=$PASSVAL sslmode=require application_name=scarlet_web_reverse'"
  echo "primary_slot_name = 'scarlet_reverse'"
} > postgresql.auto.conf
rm postgresql.auto.conf.new
EOF
  $dc up -d --no-recreate --wait postgres
  echo "reseed: the local Postgres follows $ip (slot scarlet_reverse); the old data folder is data.pre-move"
}

retire() {
  local ip
  ip=$(env_value DB_HOST)
  [ "$ip" != postgres ] || { echo "retire: the app still uses the local Postgres" >&2; exit 1; }
  [ "$(remote_pg "$ip" -c "select pg_is_in_recovery()")" = f ] || { echo "retire: the database host is not a primary" >&2; exit 1; }
  [ "$(remote_pg "$ip" -c "select coalesce(extract(epoch from now() - last_archived_time) < 600, false) from pg_stat_archiver")" = t ] \
    || { echo "retire: the database host has not archived WAL in 10 minutes" >&2; exit 1; }
  remote_pg "$ip" -c "select pg_drop_replication_slot('scarlet_reverse') where exists (select from pg_replication_slots where slot_name = 'scarlet_reverse')" >/dev/null
  $dc stop -t 60 postgres
  $dc rm -f postgres
  docker volume rm scarlet-web_pgdata
  rm -f "$state"
  echo "retire: the local Postgres and its volume are gone; the database host is the only copy besides R2"
}

status() {
  local host
  host=$(env_value DB_HOST)
  echo "app uses DB_HOST=$host sslmode=$(env_value DB_SSLMODE)"
  [ ! -f "$state" ] || { echo "recorded: $(tr '\n' ' ' < "$state")"; }
  if $dc ps --status running --services | grep -qx postgres; then
    if [ "$(local_pg -c 'select pg_is_in_recovery()')" = t ]; then
      echo "local Postgres: standby; $(local_pg -c "select status || ', last message ' || coalesce(extract(epoch from now() - last_msg_receipt_time)::int::text, '?') || ' s ago' from pg_stat_wal_receiver")"
    else
      echo "local Postgres: primary; read-only default: $(local_pg -c 'show default_transaction_read_only')"
    fi
  else
    echo "local Postgres: not running"
  fi
}

case "${1:-}" in
  expose) expose ;;
  unexpose) unexpose ;;
  check) check "${2:?address of the database host}" ;;
  set-env) set_env "${2:?ip or local}" ;;
  cutover) cutover "${2:?new or old}" "${3:?address of the other Postgres}" ;;
  cutover-start) cutover_start "${2:?new or old}" "${3:?address of the other Postgres}" ;;
  cutover-state) cutover_state ;;
  writable) writable ;;
  cutover-log) cat state/cutover.log ;;
  reseed) reseed "${2:?address of the primary}" ;;
  retire) retire ;;
  status) status ;;
  *) sed -n '2,27p' "$0"; exit 2 ;;
esac
