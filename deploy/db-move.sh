#!/bin/bash
# Sourced by bin/deploy: the database host. It uses bin/deploy's srv (the app droplet), dsrv (the database host), take_lock,
# step_r2 and variables, so it is not run on its own.
#
#   bin/deploy db-server           install the database host: folders, TLS key, pg_hba, pgBackRest settings, backup tools and
#                                  units. Safe to run again. Needs `bin/deploy r2` for the host first (this does it).
#   bin/deploy db-move             move the database from the app droplet to the database host
#   bin/deploy db-move rollback    move it back (needs the old Postgres to still follow the new one)
#   bin/deploy db-move finish      after a promotion that stopped early: pgBackRest, the nightly dump, the reverse replica and its retirement
#   bin/deploy db-move abort       undo a move that stopped before the cutover
#   bin/deploy db-move retire      remove the old Postgres and its volume now, ahead of the 48 hours
#   bin/deploy db status           the app side and the database host: role, archiver, last backup, volume, replication
#   bin/deploy db upgrade          push this checkout's deploy/ to the database host and recreate Postgres when the pin or its
#                                  settings changed (same major version only; a few seconds without a database)
#
# The move, in order (docs/HOSTING.md has the picture):
#   1. the app droplet lets the database host copy it: role replicator, a pg_hba line for the compose network, the slot
#      scarlet_db_move, and a relay container on its Tailscale address (port 15432); the live firewall is not touched
#   2. the database host copies it with pg_basebackup and follows it as a standby; nothing visitors use has changed yet
#   3. .env gets DB_HOST=<VPC address of the database host> and DB_SSLMODE=require; the idle slot starts with it and passes
#      its health check (reads on the standby work); Horizon and the scheduler pause
#   4. the old Postgres goes read-only, the standby replays to its last record and is promoted, nginx moves to the new slot
#      (deploy/db-cutover.sh): reads never fail; writes fail only between the read-only switch and the nginx reload
#   5. stanza, check and the first full backup to R2; the app droplet's nightly dump stops and the database host's starts
#   6. the old Postgres is re-seeded from the new one and follows it for 48 hours (the way back); a timer retires it
# A move that fails before step 4 puts .env back and removes the idle slot; the old Postgres is untouched and writable.

pgbr_pass_file() { echo "$secrets/${pgbr_name}-passphrase"; }
cutover() { srv "cd /srv/scarlet-web && deploy/db-cutover.sh $*"; }
db_private_address() { dsrv 'curl -fsS http://169.254.169.254/metadata/v1/interfaces/private/0/ipv4/address'; }
app_private_address() { srv 'curl -fsS http://169.254.169.254/metadata/v1/interfaces/private/0/ipv4/address'; }

db_moved() { ! srv "grep -qx 'DB_HOST=postgres' /srv/scarlet-web/.env"; }

db_server() {
  local pass_file clients
  pass_file=$(pgbr_pass_file)
  if [ ! -s "$pass_file" ]; then
    (umask 077 && openssl rand -hex 32 > "$pass_file")
    echo "db-server: made the pgBackRest passphrase in $pass_file. Keep a second copy: without it the WAL archive and backups in R2 cannot be read."
  fi
  step_r2 dsrv
  clients=$(app_private_address)
  tar -C "$here" -cf - deploy | dsrv 'sudo install -d -o ops -g ops -m 750 /srv/scarlet-db && tar -C /srv/scarlet-db --no-same-owner -xf -'
  {
    printf 'DB_PASSWORD=%s\n' "$(srv "sed -n 's/^DB_PASSWORD=//p' /srv/scarlet-web/.env")"
    printf 'PGBR_PASS=%s\n' "$(tr -d '\r\n' < "$pass_file")"
  } | dsrv "sudo sh /srv/scarlet-db/deploy/server-setup-db.sh $pgbr_name $clients"
}

db_keep_lock_alive() {
  while sleep 120; do srv "date +%s > $lock/at" >/dev/null 2>&1 || true; done
}

db_abort() {
  echo "db-move: undoing the preparation (the old Postgres stays the writer)"
  cutover set-env local || true
  srv 'cd /srv/scarlet-web && deploy/rollout.sh --unprepare' || true
  dsrv '/srv/scarlet-db/deploy/dc-db stop postgres' >/dev/null 2>&1 || true
  cutover unexpose || true
}

db_wait_streaming() {
  local lag
  for _ in $(seq 1 90); do
    lag=$(printf '%s\n' "select coalesce(max(pg_wal_lsn_diff(pg_current_wal_lsn(), replay_lsn))::bigint, -1) from pg_stat_replication where application_name = 'scarlet_db' and state = 'streaming'" \
      | srv 'cd /srv/scarlet-web && deploy/dc exec -T postgres psql -X -U postgres -d scarlet -Atq' || echo -1)
    if [ "$lag" != -1 ] && [ "$lag" -lt 1000000 ]; then echo "db-move: the database host follows the app droplet (replay lag $lag bytes)"; return 0; fi
    sleep 2
  done
  echo "db-move: the database host does not stream from the app droplet" >&2
  return 1
}

db_move() {
  local db_ip tailscale_ip code=0
  [ "${DB_MOVE_ALLOWED:-1}" = 1 ] || exit 1
  take_lock
  db_keep_lock_alive &
  keeper=$!
  trap 'kill "$keeper" 2>/dev/null || true; report; srv "rm -rf '"$lock"'"' EXIT
  db_moved && { echo "db-move: the database is already on $db_host (DB_HOST is not postgres)" >&2; exit 1; }
  srv 'test -x /srv/scarlet-web/deploy/db-cutover.sh' || { echo "db-move: deploy/db-cutover.sh is not on the app droplet; run bin/deploy first" >&2; exit 1; }
  dsrv 'mountpoint -q /mnt/scarlet-db-data && test -d /mnt/scarlet-db-data/pgdata' || { echo "db-move: the data volume is not mounted on $db_host" >&2; exit 1; }
  dsrv 'test -f /srv/scarlet-db/etc/pgbackrest.conf' || { echo "db-move: run bin/deploy db-server first" >&2; exit 1; }
  db_ip=$(db_private_address)
  echo "db-move: $db_host is $db_ip on the VPC"

  echo "db-move: 1/6 letting the database host copy the app droplet"
  tailscale_ip=$(cutover expose | tail -n 1)
  dsrv "timeout 5 bash -c 'exec 3<>/dev/tcp/$tailscale_ip/15432'" || { echo "db-move: $db_host cannot reach $tailscale_ip:15432 (Tailscale)" >&2; db_abort; exit 1; }

  echo "db-move: 2/6 copying the data to $db_host (pg_basebackup)"
  if ! srv 'cat /srv/scarlet-web/state/replicator.pass' | sed 's/^/REPL_PASS=/' | dsrv "SEED_FORCE=${DEPLOY_DB_FORCE_SEED:-} /srv/scarlet-db/deploy/db-host.sh seed $tailscale_ip 15432" \
    || ! dsrv '/srv/scarlet-db/deploy/db-host.sh up'; then
    db_abort; exit 1
  fi
  db_wait_streaming || { db_abort; exit 1; }
  cutover check "$db_ip" || { db_abort; exit 1; }

  echo "db-move: 3/6 starting the idle slot against $db_host"
  cutover set-env "$db_ip" || { db_abort; exit 1; }
  srv 'cd /srv/scarlet-web && deploy/rollout.sh --prepare' || { db_abort; exit 1; }

  echo "db-move: 4/6 the cutover"
  cutover cutover new "$db_ip" || code=$?
  case $code in
    0) ;;
    10) db_abort; echo "db-move: nothing moved; the old Postgres is the writer again" >&2; exit 1 ;;
    *) echo "db-move: promoted, but the switch failed (code $code). The new Postgres is the writer. Fix the app side, then run bin/deploy with the same release; roll back with: bin/deploy db-move rollback" >&2; exit 1 ;;
  esac

  db_finish "$db_ip"
  trap - EXIT
  kill "$keeper" 2>/dev/null || true
  report
  srv "rm -rf $lock"
  db_status || true
  echo "db-move: done. The way back for 48 hours: bin/deploy db-move rollback"
}

db_finish() {
  local db_ip=$1 moved_at
  echo "db-move: 5/6 pgBackRest and the nightly dump on $db_host"
  dsrv '/srv/scarlet-db/deploy/db-host.sh primary-ready'
  dsrv 'sudo systemctl start scarlet-backup.service'
  echo "db-move: the nightly dump ran on $db_host"
  srv 'sudo systemctl disable --now scarlet-backup.timer >/dev/null 2>&1; cd /srv/scarlet-web && deploy/db-cutover.sh unexpose'

  echo "db-move: 6/6 the old Postgres follows $db_host for 48 hours"
  cutover reseed "$db_ip"
  moved_at=$(date -u +%s)
  srv "printf 'DB_IP=%s\nMOVED_AT=%s\nRETIRE_AFTER=%s\n' $db_ip $moved_at $((moved_at + 172800)) > /srv/scarlet-web/state/db-move.env"
  db_schedule_retire "$((moved_at + 172800))"
}

db_schedule_retire() {
  local at
  at=$(date -u -d "@$1" '+%Y-%m-%d %H:%M:%S')
  srv "sudo sh -c 'cat > /etc/systemd/system/scarlet-db-retire.service <<EOF
[Unit]
Description=Remove the old local Postgres once the database host has proven itself

[Service]
Type=oneshot
User=ops
WorkingDirectory=/srv/scarlet-web
ExecStart=/srv/scarlet-web/deploy/db-cutover.sh retire
ExecStartPost=/bin/systemctl disable scarlet-db-retire.timer
EOF
cat > /etc/systemd/system/scarlet-db-retire.timer <<EOF
[Unit]
Description=Retire the old local Postgres

[Timer]
OnCalendar=$at UTC
Persistent=true

[Install]
WantedBy=timers.target
EOF
systemctl daemon-reload && systemctl enable --now scarlet-db-retire.timer >/dev/null'"
  echo "db-move: scarlet-db-retire.timer runs at $at UTC"
}

db_rollback() {
  local db_ip code=0
  take_lock
  db_keep_lock_alive &
  keeper=$!
  trap 'kill "$keeper" 2>/dev/null || true; report; srv "rm -rf '"$lock"'"' EXIT
  db_moved || { echo "db-move: the app already uses the local Postgres" >&2; exit 1; }
  db_ip=$(srv "sed -n 's/^DB_HOST=//p' /srv/scarlet-web/.env")
  srv 'cd /srv/scarlet-web && deploy/dc ps --status running --services | grep -qx postgres' || { echo "db-move: the old Postgres is not running; the way back is gone (retired?)" >&2; exit 1; }
  [ "$(srv 'cd /srv/scarlet-web && deploy/dc exec -T postgres psql -X -U postgres -d scarlet -Atq -c "select pg_is_in_recovery()"')" = t ] \
    || { echo "db-move: the old Postgres is not a standby" >&2; exit 1; }
  echo "db-move: moving the database back from $db_ip to the local Postgres"
  cutover set-env local
  srv 'cd /srv/scarlet-web && deploy/rollout.sh --prepare' || { cutover set-env "$db_ip"; srv 'cd /srv/scarlet-web && deploy/rollout.sh --unprepare'; exit 1; }
  cutover cutover old "$db_ip" || code=$?
  case $code in
    0) ;;
    10) cutover set-env "$db_ip"; srv 'cd /srv/scarlet-web && deploy/rollout.sh --unprepare'; echo "db-move: nothing moved; $db_host is the writer again" >&2; exit 1 ;;
    *) echo "db-move: promoted the local Postgres but the switch failed (code $code)" >&2; exit 1 ;;
  esac
  srv 'sudo systemctl enable --now scarlet-backup.timer >/dev/null; sudo systemctl disable --now scarlet-db-retire.timer >/dev/null 2>&1; rm -f /srv/scarlet-web/state/db-move.env'
  dsrv 'sudo systemctl disable --now scarlet-pgbackrest-full.timer scarlet-pgbackrest-diff.timer scarlet-db-heartbeat.timer scarlet-backup.timer >/dev/null 2>&1; /srv/scarlet-db/deploy/dc-db stop postgres' || true
  echo "db-move: the local Postgres is the writer again; $db_host is stopped (its data stays on the volume; re-seed it with DEPLOY_DB_FORCE_SEED=1 bin/deploy db-move)"
}

db_retire() {
  srv 'cd /srv/scarlet-web && deploy/db-cutover.sh retire && sudo systemctl disable --now scarlet-db-retire.timer >/dev/null 2>&1; true'
}

db_status() {
  local rc=0
  srv 'cd /srv/scarlet-web && deploy/db-cutover.sh status' || rc=1
  if db_moved; then
    echo "--- $db_host"
    dsrv '/srv/scarlet-db/deploy/db-host.sh status' || rc=1
  fi
  return "$rc"
}

db_upgrade() {
  db_server
  dsrv '/srv/scarlet-db/deploy/db-host.sh upgrade'
}
