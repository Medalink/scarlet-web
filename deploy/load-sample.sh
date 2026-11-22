#!/bin/bash
# Samples one load droplet and prints a TSV line every INTERVAL seconds (default 15): bin/load sample runs it on both.
#
#   load-sample.sh app <seconds> [interval]   from /srv/scarlet-web
#   load-sample.sh db <seconds> [interval]    from /srv/scarlet-db
#
# Every column is read from /proc, ss, redis-cli or psql; nothing is estimated.
#   cpu_pct     busy share of all vCPUs since the last line   iowait_pct   share waiting on disk
#   mem_pct     RAM in use (MemTotal - MemAvailable)           swap_mb      swap in use
#   load1       one-minute load average
# app: fpm_conns established connections from nginx to php-fpm (one per busy child), redis_mb, ingest_q and rollups_q (list
#      lengths, jobs waiting), horizon_cpu_pct the containers named horizon-* and app-* (docker stats), net_in_kbs
# db:  pg_conns client backends, pg_active running queries, oldest_undecoded_s age of the oldest part with no decoded_at,
#      parts rows in parts, vol_pct use of /mnt/scarlet-db-data, vol_wr_mbs and vol_rd_mbs volume throughput, wal_age_s age of
#      the last archived WAL segment
set -euo pipefail
role=${1:?app or db}
seconds=${2:?seconds}
interval=${3:-15}

cpu_line() { awk '/^cpu /{idle=$5+$6; total=0; for (i=2;i<=NF;i++) total+=$i; print total, idle, $6}' /proc/stat; }
volume_dev() { findmnt -n -o SOURCE /mnt/scarlet-db-data 2>/dev/null | sed 's#/dev/##'; }
disk_sectors() { awk -v d="$1" '$3 == d {print $6, $10}' /proc/diskstats; }

app_header='ts\tcpu_pct\tiowait_pct\tmem_pct\tswap_mb\tload1\tfpm_conns\tredis_mb\tingest_q\trollups_q\tnet_in_kbs'
db_header='ts\tcpu_pct\tiowait_pct\tmem_pct\tswap_mb\tload1\tpg_conns\tpg_active\toldest_undecoded_s\tparts\tvol_pct\tvol_wr_mbs\tvol_rd_mbs\twal_age_s'
if [ "$role" = app ]; then printf "$app_header\n"; else printf "$db_header\n"; fi

pg() { deploy/dc-db exec -T postgres psql -X -U postgres -d scarlet -Atq -F $'\t' -c "$1" 2>/dev/null || echo; }
redis() { deploy/dc exec -T redis redis-cli "$@" 2>/dev/null | tr -d '\r'; }

read -r total0 idle0 wait0 < <(cpu_line)
dev=
[ "$role" = db ] && dev=$(volume_dev)
read -r rd0 wr0 < <([ -n "$dev" ] && disk_sectors "$dev" || echo "0 0")
net0=$(awk '$1 ~ /^(eth0|ens3):/ {print $2}' /proc/net/dev | head -n 1)
end=$((SECONDS + seconds))
while [ "$SECONDS" -lt "$end" ]; do
  sleep "$interval"
  read -r total1 idle1 wait1 < <(cpu_line)
  dt=$((total1 - total0))
  [ "$dt" -gt 0 ] || dt=1
  cpu=$(awk -v a="$dt" -v b="$((idle1 - idle0))" 'BEGIN { printf "%.1f", 100 * (a - b) / a }')
  iow=$(awk -v a="$dt" -v b="$((wait1 - wait0))" 'BEGIN { printf "%.1f", 100 * b / a }')
  total0=$total1 idle0=$idle1 wait0=$wait1
  mem=$(awk '/MemTotal/{t=$2} /MemAvailable/{a=$2} END { printf "%.1f", 100 * (t - a) / t }' /proc/meminfo)
  swap=$(awk '/SwapTotal/{t=$2} /SwapFree/{f=$2} END { printf "%d", (t - f) / 1024 }' /proc/meminfo)
  load1=$(cut -d' ' -f1 /proc/loadavg)
  ts=$(date -u +%H:%M:%S)
  if [ "$role" = app ]; then
    fpm=$(ss -Htn state established '( dport = :9001 or dport = :9002 )' | wc -l)
    rmb=$(redis info memory | awk -F: '/^used_memory:/{printf "%.1f", $2 / 1048576}')
    iq=$(redis llen scarlet_queues:ingest)
    rq=$(redis llen scarlet_queues:rollups)
    net1=$(awk '$1 ~ /^(eth0|ens3):/ {print $2}' /proc/net/dev | head -n 1)
    kbs=$(awk -v a="$net0" -v b="$net1" -v i="$interval" 'BEGIN { printf "%d", (b - a) / 1024 / i }')
    net0=$net1
    printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' "$ts" "$cpu" "$iow" "$mem" "$swap" "$load1" "$fpm" "$rmb" "$iq" "$rq" "$kbs"
  else
    stats=$(pg "set statement_timeout = '5s'; select count(*) filter (where backend_type = 'client backend'), count(*) filter (where state = 'active' and backend_type = 'client backend') from pg_stat_activity" | tail -n 1)
    old=$(pg "set statement_timeout = '5s'; select coalesce(extract(epoch from now() - min(created_at))::int, 0) from parts where decoded_at is null" | tail -n 1)
    rows=$(pg "select reltuples::bigint from pg_class where relname = 'parts'" | tail -n 1)
    wal=$(pg "select coalesce(extract(epoch from now() - last_archived_time)::int, -1) from pg_stat_archiver" | tail -n 1)
    vol=$(df --output=pcent /mnt/scarlet-db-data | tail -n 1 | tr -dc '0-9')
    read -r rd1 wr1 < <([ -n "$dev" ] && disk_sectors "$dev" || echo "0 0")
    wr=$(awk -v a="$wr0" -v b="$wr1" -v i="$interval" 'BEGIN { printf "%.1f", (b - a) * 512 / 1048576 / i }')
    rd=$(awk -v a="$rd0" -v b="$rd1" -v i="$interval" 'BEGIN { printf "%.1f", (b - a) * 512 / 1048576 / i }')
    rd0=$rd1 wr0=$wr1
    printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' "$ts" "$cpu" "$iow" "$mem" "$swap" "$load1" "${stats:-?}" "${old:-?}" "${rows:-?}" "${vol:-?}" "$wr" "$rd" "${wal:-?}"
  fi
done
