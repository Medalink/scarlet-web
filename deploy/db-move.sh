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

db_moved() {
  local rc=0
  srv "grep -qx 'DB_HOST=postgres' /srv/scarlet-web/.env" || rc=$?
  case $rc in
    0) return 1 ;;
    1) return 0 ;;
    *) echo "deploy: cannot read .env on $host (ssh exit $rc); not guessing where the database is" >&2; exit 2 ;;
  esac
}

keeper=
keep_lock=
finish_pending=
cut_code=

db_exit() {
  [ -z "$keeper" ] || kill "$keeper" 2>/dev/null || true
  [ -z "$finish_pending" ] || echo "db-move: $db_host is the writer, but the last step did not finish. Run: bin/deploy db-move finish" >&2
  report
  [ -n "$keep_lock" ] || srv "rm -rf $lock" || true
}

db_lock() {
  take_lock
  db_keep_lock_alive &
  keeper=$!
  trap db_exit EXIT
}

db_server() {
  local pass_file clients
  pass_file=$(pgbr_pass_file)
  if [ ! -s "$pass_file" ]; then
    (umask 077 && openssl rand -hex 32 > "$pass_file")
    echo "db-server: made the pgBackRest passphrase in $pass_file. Keep a second copy: without it the WAL archive and backups in R2 cannot be read."
  fi
  step_r2 dsrv
  clients=$(app_private_addresses)
  tar -C "$here" -cf - deploy | dsrv 'sudo install -d -o ops -g ops -m 750 /srv/scarlet-db && tar -C /srv/scarlet-db --no-same-owner -xf -'
  {
    printf 'DB_PASSWORD=%s\n' "$(srv "sed -n 's/^DB_PASSWORD=//p' /srv/scarlet-web/.env")"
    printf 'PGBR_PASS=%s\n' "$(tr -d '\r\n' < "$pass_file")"
  } | dsrv "sudo sh /srv/scarlet-db/deploy/server-setup-db.sh $pgbr_name $clients"
  dsrv 'cat /srv/scarlet-db/etc/tls/server.crt' | srv 'mkdir -p /srv/scarlet-web/dbtls && cat > /srv/scarlet-web/dbtls/db-ca.crt && chmod 644 /srv/scarlet-web/dbtls/db-ca.crt'
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

db_abort_manual() {
  local rec
  rec=$(dsrv 'if /srv/scarlet-db/deploy/dc-db ps --status running --services | grep -qx postgres; then /srv/scarlet-db/deploy/dc-db exec -T postgres psql -X -U postgres -d scarlet -Atq -c "select pg_is_in_recovery()"; else echo stopped; fi' 2>/dev/null) || rec=unknown
  case $rec in
    t | stopped) ;;
    *) echo "db-move: $db_host answered '$rec', not 'standby' or 'stopped'; refusing to make the old Postgres writable. If $db_host is the writer, run bin/deploy db-move finish" >&2; exit 1 ;;
  esac
  cutover writable
  db_abort
}

db_pg_state() {
  local out
  out=$("$@" 2>/dev/null) || out=unknown
  case $out in t) echo standby ;; f) echo writer ;; stopped) echo stopped ;; *) echo unknown ;; esac
}

db_cutover_run() {
  local direction=$1 ip=$2 out misses=0
  cut_code=
  srv 'cd /srv/scarlet-web && rm -f state/cutover.code state/cutover.pid' || { echo "db-move: cannot reach $host; nothing started" >&2; cut_code=10; return 0; }
  srv "cd /srv/scarlet-web && deploy/db-cutover.sh cutover-start $direction $ip" >/dev/null || true
  while :; do
    if out=$(srv 'cd /srv/scarlet-web && deploy/db-cutover.sh cutover-state' 2>/dev/null); then
      misses=0
      case $out in
        'DONE '*) cut_code=${out#DONE }; break ;;
        RUNNING) ;;
        *) cut_code=99; break ;;
      esac
    else
      misses=$((misses + 1))
      if [ "$misses" -ge 90 ]; then cut_code=98; break; fi
    fi
    sleep 2
  done
  srv 'cd /srv/scarlet-web && deploy/db-cutover.sh cutover-log' 2>/dev/null || true
}

db_cutover_diagnose() {
  local direction=$1 app dbh prepared active after
  app=$(db_pg_state srv 'cd /srv/scarlet-web && if deploy/dc ps --status running --services | grep -qx postgres; then deploy/dc exec -T postgres psql -X -U postgres -d scarlet -Atq -c "select pg_is_in_recovery()"; else echo stopped; fi')
  dbh=$(db_pg_state dsrv 'if /srv/scarlet-db/deploy/dc-db ps --status running --services | grep -qx postgres; then /srv/scarlet-db/deploy/dc-db exec -T postgres psql -X -U postgres -d scarlet -Atq -c "select pg_is_in_recovery()"; else echo stopped; fi')
  prepared=$(srv 'cat /srv/scarlet-web/state/prepared 2>/dev/null; true' 2>/dev/null || echo unknown)
  active=$(srv 'cat /srv/scarlet-web/state/active 2>/dev/null; true' 2>/dev/null || echo unknown)
  echo "db-move: the cutover ended with code $cut_code. Now: app droplet Postgres $app, $db_host Postgres $dbh, prepared slot '$prepared', active slot '$active'" >&2
  if [ "$app" = unknown ] || [ "$dbh" = unknown ]; then
    keep_lock=1
    echo "db-move: could not read one side, so the deploy lock stays (40 minutes at most). Read both with bin/deploy db status before anything else." >&2
    return 0
  fi
  if [ "$app" = writer ] && [ "$dbh" = writer ]; then
    keep_lock=1
    echo "db-move: BOTH Postgres servers accept writes. Stop the one that should not: on $db_host run /srv/scarlet-db/deploy/dc-db stop postgres, or on the app droplet deploy/dc stop postgres. The lock stays." >&2
  elif [ "$app" != writer ] && [ "$dbh" != writer ]; then
    keep_lock=1
    echo "db-move: neither Postgres accepts writes. Promote the one that should write (select pg_promote()) or run bin/deploy db-move abort. The lock stays." >&2
  elif { [ "$direction" = new ] && [ "$dbh" = writer ]; } || { [ "$direction" = old ] && [ "$app" = writer ]; }; then
    after='see docs/HOSTING.md, Move back'
    [ "$direction" != new ] || after='bin/deploy db-move finish'
    echo "db-move: the target is the writer and visitors may still be on the old slot. If the prepared slot is not the active one, run on the app droplet: cd /srv/scarlet-web && deploy/rollout.sh --switch. Then: $after." >&2
  else
    echo "db-move: nothing moved. Run bin/deploy db-move abort (it makes the old Postgres writable and removes the idle slot), then bin/deploy to restart Horizon and the scheduler." >&2
  fi
}

db_cutover() {
  db_cutover_run "$1" "$2"
  case $cut_code in
    0) return 0 ;;
    10) return 10 ;;
    20)
      echo "db-move: the target is the writer but visitors did not move; switching them now" >&2
      if srv 'cd /srv/scarlet-web && deploy/rollout.sh --switch'; then return 0; fi
      ;;
  esac
  db_cutover_diagnose "$1"
  exit 1
}

db_move() {
  local db_ip tailscale_ip
  [ "${DB_MOVE_ALLOWED:-1}" = 1 ] || exit 1
  db_lock
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
  db_cutover new "$db_ip" || { db_abort; echo "db-move: nothing moved; the old Postgres is the writer again" >&2; exit 1; }

  db_finish "$db_ip"
  db_status || true
  echo "db-move: done. The way back for 48 hours: bin/deploy db-move rollback"
}

db_finish() {
  local db_ip=$1 moved_at
  finish_pending=1
  srv 'sudo systemctl disable --now scarlet-backup.timer >/dev/null 2>&1; true'
  echo "db-move: 5/6 pgBackRest and the nightly dump on $db_host"
  dsrv '/srv/scarlet-db/deploy/db-host.sh primary-ready'
  dsrv 'sudo systemctl start scarlet-backup.service'
  echo "db-move: the nightly dump ran on $db_host"
  srv 'cd /srv/scarlet-web && deploy/db-cutover.sh unexpose'

  echo "db-move: 6/6 the old Postgres follows $db_host for 48 hours"
  cutover reseed "$db_ip"
  moved_at=$(date -u +%s)
  srv "printf 'DB_IP=%s\nMOVED_AT=%s\nRETIRE_AFTER=%s\n' $db_ip $moved_at $((moved_at + 172800)) > /srv/scarlet-web/state/db-move.env"
  db_schedule_retire "$((moved_at + 172800))"
  finish_pending=
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
ExecStartPost=+/bin/systemctl disable scarlet-db-retire.timer
Restart=on-failure
RestartSec=1h
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
  local db_ip
  db_lock
  db_moved || { echo "db-move: the app already uses the local Postgres" >&2; exit 1; }
  db_ip=$(srv "sed -n 's/^DB_HOST=//p' /srv/scarlet-web/.env")
  srv 'cd /srv/scarlet-web && deploy/dc ps --status running --services | grep -qx postgres' || { echo "db-move: the old Postgres is not running; the way back is gone (retired?)" >&2; exit 1; }
  [ "$(srv 'cd /srv/scarlet-web && deploy/dc exec -T postgres psql -X -U postgres -d scarlet -Atq -c "select pg_is_in_recovery()"')" = t ] \
    || { echo "db-move: the old Postgres is not a standby" >&2; exit 1; }
  echo "db-move: moving the database back from $db_ip to the local Postgres"
  cutover set-env local
  srv 'cd /srv/scarlet-web && deploy/rollout.sh --prepare' || { cutover set-env "$db_ip"; srv 'cd /srv/scarlet-web && deploy/rollout.sh --unprepare'; exit 1; }
  db_cutover old "$db_ip" || { cutover set-env "$db_ip"; srv 'cd /srv/scarlet-web && deploy/rollout.sh --unprepare'; echo "db-move: nothing moved; $db_host is the writer again" >&2; exit 1; }
  srv 'sudo systemctl enable --now scarlet-backup.timer >/dev/null; sudo systemctl disable --now scarlet-db-retire.timer >/dev/null 2>&1; sudo systemctl stop scarlet-db-retire.service >/dev/null 2>&1; rm -f /srv/scarlet-web/state/db-move.env'
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
