#!/bin/bash
# Runs on the database host as ops, from /srv/scarlet-db. bin/deploy and deploy/db-move.sh call it over ssh.
#
#   db-host.sh seed <host> <port>   copy the primary at <host>:<port> into the volume with pg_basebackup and make this a standby
#                                   (reads `REPL_PASS=<password of role replicator>` on stdin). Needs an empty data folder
#                                   or one that is still a standby.
#   db-host.sh up                   start Postgres and wait until it is healthy
#   db-host.sh primary-ready        after promotion: create the pgBackRest stanza, check it, take the first full backup and
#                                   turn the backup timers on
#   db-host.sh upgrade              pull the pinned image and recreate Postgres when the pin or its settings changed (same major only)
#   db-host.sh status               role, archiver, last backup, volume and replication; exits 1 when this is a primary
#                                   whose last archived WAL segment is older than 5 minutes
set -euo pipefail
cd /srv/scarlet-db
dc=deploy/dc-db
data=/mnt/scarlet-db-data/pgdata
wal_stale_seconds=${WAL_STALE_SECONDS:-300}
pg() { $dc exec -T postgres psql -X -U postgres -d scarlet -Atq -F '|' "$@"; }
image() { $dc config --images | grep timescaledb-ha | head -n 1; }

require_volume() {
  mountpoint -q /mnt/scarlet-db-data || { echo "db-host: /mnt/scarlet-db-data is not mounted" >&2; exit 1; }
  [ -d "$data" ] || { echo "db-host: $data is missing" >&2; exit 1; }
}

seed() {
  local host=$1 port=$2 pass=
  while IFS='=' read -r key value; do [ "$key" = REPL_PASS ] && pass=$value; done
  [ -n "$pass" ] || { echo "db-host: no REPL_PASS on stdin" >&2; exit 1; }
  require_volume
  if [ -n "$(ls -A "$data/data" 2>/dev/null || true)" ]; then
    if [ ! -f "$data/data/standby.signal" ] && [ -z "${SEED_FORCE:-}" ]; then
      echo "db-host: $data/data holds a database that is not a standby; not touching it (DEPLOY_DB_FORCE_SEED=1 replaces it)" >&2
      exit 1
    fi
    $dc stop postgres >/dev/null 2>&1 || true
    $dc run --rm --no-deps -T --entrypoint sh postgres -c 'pgbackrest --stanza=scarlet stop; pgbackrest --stanza=scarlet --force stanza-delete; true' || true
    docker run --rm --user 1000:1000 -v "$data:/home/postgres/pgdata" --entrypoint sh "$(image)" -c 'rm -rf /home/postgres/pgdata/data'
  fi
  local img
  img=$(image)
  docker pull -q "$img" >/dev/null
  docker run --rm --network host --user 1000:1000 -e PGPASSWORD="$pass" -v "$data:/home/postgres/pgdata" --entrypoint pg_basebackup "$img" \
    -h "$host" -p "$port" -U replicator -D /home/postgres/pgdata/data -X stream -S scarlet_db_move --checkpoint=fast --no-password
  docker run --rm -i --user 1000:1000 -v "$data:/home/postgres/pgdata" -e HOSTVAL="$host" -e PORTVAL="$port" -e PASSVAL="$pass" --entrypoint sh "$img" -s <<'EOF'
set -e
cd /home/postgres/pgdata/data
touch standby.signal
grep -v -E '^(primary_conninfo|primary_slot_name)' postgresql.auto.conf > postgresql.auto.conf.new || true
{
  cat postgresql.auto.conf.new
  echo "primary_conninfo = 'host=$HOSTVAL port=$PORTVAL user=replicator password=$PASSVAL sslmode=prefer application_name=scarlet_db'"
  echo "primary_slot_name = 'scarlet_db_move'"
} > postgresql.auto.conf
rm postgresql.auto.conf.new
chmod 600 postgresql.auto.conf
EOF
  echo "db-host: seeded from $host:$port"
}

upgrade() {
  local img new_major cur_major t
  img=$(image)
  new_major=$(docker run --rm --entrypoint postgres "$img" --version | grep -oE "[0-9]+" | head -n 1)
  cur_major=$(($(pg -c "show server_version_num") / 10000))
  [ "$new_major" = "$cur_major" ] || { echo "db-host: the pinned image is PostgreSQL $new_major and the server is $cur_major; a major upgrade is not done by this command" >&2; exit 1; }
  docker pull -q "$img" >/dev/null
  pg -c checkpoint >/dev/null
  t=$(date +%s)
  $dc up -d --wait postgres
  echo "db-host: Postgres is up on $img after $(($(date +%s) - t)) s"
  $dc exec -T postgres pgbackrest check
}

primary_ready() {
  [ "$(pg -c 'select pg_is_in_recovery()')" = f ] || { echo "db-host: still a standby" >&2; exit 1; }
  $dc exec -T postgres pgbackrest stanza-create
  $dc exec -T postgres pgbackrest check
  $dc exec -T postgres pgbackrest backup --type=full
  sudo systemctl enable --now scarlet-pgbackrest-full.timer scarlet-pgbackrest-diff.timer scarlet-db-heartbeat.timer scarlet-backup.timer >/dev/null
  echo "db-host: stanza scarlet created, checked, full backup taken, timers on"
}

human_age() {
  local s=$1
  if [ "$s" -lt 7200 ]; then echo "$((s / 60)) min"; elif [ "$s" -lt 172800 ]; then echo "$((s / 3600)) h"; else echo "$((s / 86400)) days"; fi
}

status() {
  local rc=0 pgbr_rc listen addr recovery row age failed archived last_failed_newer
  $dc ps --format '{{.Service}} {{.Status}}' postgres
  recovery=$(pg -c 'select pg_is_in_recovery()')
  if [ "$recovery" = t ]; then
    echo "role      standby; replayed to $(pg -c 'select pg_last_wal_replay_lsn()'), last replayed transaction $(pg -c "select coalesce(extract(epoch from now() - pg_last_xact_replay_timestamp())::int::text, 'n/a')") s ago"
  else
    echo "role      primary"
    row=$(pg -c "select coalesce(extract(epoch from now() - last_archived_time)::bigint, -1), failed_count, archived_count, coalesce(last_failed_time > last_archived_time, false) from pg_stat_archiver")
    IFS='|' read -r age failed archived last_failed_newer <<<"$row"
    if [ "$age" = -1 ]; then
      echo "archiver  nothing archived yet (failed $failed): STALE"; rc=1
    else
      echo "archiver  last WAL segment archived $(human_age "$age") ago; $archived archived, $failed failed"
      if [ "$age" -gt "$wal_stale_seconds" ]; then echo "archiver  older than $((wal_stale_seconds / 60)) minutes: STALE"; rc=1; fi
      [ "$last_failed_newer" = f ] || { echo "archiver  the last attempt failed"; rc=1; }
    fi
    pg -c "select 'standby   ' || coalesce(application_name, '') || ' ' || state || ' ' || coalesce(client_addr::text, '') || ' replay lag ' || coalesce(pg_wal_lsn_diff(pg_current_wal_lsn(), replay_lsn)::text, '?') || ' bytes' from pg_stat_replication" || true
  fi
  pgbr_rc=0
  $dc exec -T postgres pgbackrest info --output=json 2>/dev/null | python3 -c '
import json, sys, time
try:
    stanzas = json.load(sys.stdin)
except Exception:
    print("pgbackrest no info"); sys.exit(3)
if not stanzas:
    print("pgbackrest no stanza"); sys.exit(3)
for s in stanzas:
    backups = s.get("backup", [])
    if not backups:
        print("pgbackrest stanza %s has no backup yet" % s["name"]); sys.exit(3)
    b = backups[-1]
    age = int(time.time()) - b["timestamp"]["stop"]
    print("pgbackrest last backup %s %s, %d h %d min ago, %d backups, WAL %s to %s" % (b["type"], b["label"], age // 3600, age % 3600 // 60, len(backups), s["archive"][0]["min"] if s.get("archive") else "?", s["archive"][0]["max"] if s.get("archive") else "?"))
' || pgbr_rc=$?
  if [ "$pgbr_rc" = 3 ] && [ "$recovery" = f ]; then echo "pgbackrest nothing to restore from: STALE"; rc=1; fi
  listen=$(sed -n 's/^DB_LISTEN=//p' /srv/scarlet-db/.env)
  for addr in ${listen//,/ }; do
    ss -ltn 'sport = :5432' | grep -qF "$addr:5432" || { echo "listen    not listening on $addr:5432: STALE"; rc=1; }
  done
  echo "listen    $listen"
  echo "volume  $(df -h --output=used,size,pcent /mnt/scarlet-db-data | tail -n 1 | awk '{print $1 " of " $2 " (" $3 ")"}'); database $(pg -c "select pg_size_pretty(pg_database_size('scarlet'))")"
  return "$rc"
}

case "${1:-}" in
  seed) seed "${2:?host}" "${3:?port}" ;;
  up) require_volume; $dc up -d --wait postgres ;;
  primary-ready) primary_ready ;;
  upgrade) upgrade ;;
  status) status ;;
  *) sed -n '2,14p' "$0"; exit 2 ;;
esac
