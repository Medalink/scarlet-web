#!/bin/bash
# The database backup of scarletweb.io. Runs on the droplet.
#
#   backup.sh                 dump now, encrypt the dump with age, copy it to Cloudflare R2, prune R2 and the local folder
#   backup.sh status          the newest local and R2 copies with their ages; exits 1 when R2 is unreachable or its
#                             newest daily copy is older than 26 hours
#   backup.sh prune <prefix> <keep>   keep the newest <keep> objects under <prefix> in the bucket, delete the rest
#
# The systemd timer scarlet-backup runs it every night at 03:15 UTC; `bin/deploy backup` starts that same unit. The unit
# loads /etc/scarlet-web/r2.env (root-only), which holds the R2 credentials and the bucket as rclone's own settings
# (RCLONE_CONFIG_R2_*) plus R2_BUCKET. Only the public age key, deploy/backup-age.pub, is on this machine: the private
# key stays with the owner, so a copy of this server cannot read the backups.
#
# R2 layout: daily/scarlet-<stamp>.dump.age every night, the newest 14 kept; weekly/ gets a copy of the Sunday (UTC)
# dump, the newest 8 kept. Any failure exits non-zero, which shows as a failed unit and in `bin/deploy backups`.
set -euo pipefail

cd "${SCARLET_BASE:-/srv/scarlet-web}"
umask 077
keep_daily=${BACKUP_KEEP_DAILY:-14}
keep_weekly=${BACKUP_KEEP_WEEKLY:-8}
stale_seconds=${BACKUP_STALE_SECONDS:-93600}
bucket=${R2_BUCKET:-scarlet-web-backups}
remote() { echo "R2:$bucket/$1"; }

# rclone reads its settings from the environment; it never writes a config file or a cache outside /tmp.
export RCLONE_CONFIG=${RCLONE_CONFIG:-/dev/null} RCLONE_CACHE_DIR=${RCLONE_CACHE_DIR:-/tmp/rclone-cache-scarlet} RCLONE_CONFIG_R2_NO_CHECK_BUCKET=true

fail() { echo "backup: FAILED: $*" >&2; exit 1; }

# The epoch seconds of a name like scarlet-20261121T171755Z.dump(.age).
stamp_epoch() {
  local s
  s=$(sed -E 's/^.*scarlet-([0-9]{4})([0-9]{2})([0-9]{2})T([0-9]{2})([0-9]{2})([0-9]{2})Z.*$/\1-\2-\3 \4:\5:\6/' <<<"$1")
  date -u -d "$s" +%s
}

human_age() {
  local s=$1
  if [ "$s" -lt 7200 ]; then echo "$((s / 60)) min"; elif [ "$s" -lt 172800 ]; then echo "$((s / 3600)) h"; else echo "$((s / 86400)) days"; fi
}

# prune <prefix> <keep>: stamped names sort in time order. Does nothing when the listing fails or has no more than <keep>.
prune() {
  local prefix=$1 keep=$2 names count
  names=$(list_remote "$prefix") || fail "listing $prefix failed, nothing pruned"
  names=$(awk '{print $2}' <<<"$names")
  count=$(grep -c . <<<"$names" || true)
  if [ "$count" -le "$keep" ]; then
    echo "prune: $prefix has $count, keeping all (limit $keep)"
    return 0
  fi
  local doomed
  doomed=$(head -n "$((count - keep))" <<<"$names")
  while IFS= read -r name; do
    rclone deletefile "$(remote "$prefix")$name" || fail "could not delete $prefix$name"
    echo "prune: deleted $prefix$name"
  done <<<"$doomed"
  echo "prune: $prefix had $count, kept $keep"
}

# <prefix>: "size name" lines, oldest first. A prefix with no objects yet is an empty list; any other error fails.
list_remote() {
  local out rc=0
  out=$(rclone lsf --files-only --format sp --separator ' ' "$(remote "$1")") || rc=$?
  [ "$rc" = 0 ] || [ "$rc" = 3 ] || return "$rc"
  awk '$2 ~ /^scarlet-[0-9]{8}T[0-9]{6}Z\.dump\.age$/' <<<"$out" | sort -k2
}

status() {
  local rc=0 newest_local line name size age_s now errors
  now=$(date -u +%s)
  newest_local=$(ls -1 backups 2>/dev/null | grep -E '^scarlet-.*\.dump$' | sort | tail -n 1 || true)
  if [ -n "$newest_local" ]; then
    age_s=$((now - $(stamp_epoch "$newest_local")))
    echo "local   newest  $newest_local  $(du -h "backups/$newest_local" | cut -f1)  $(human_age "$age_s") ago  ($(ls backups | grep -c '^scarlet-.*dump$') kept)"
  else
    echo "local   none"; rc=1
  fi
  errors=$(mktemp)
  for prefix in daily weekly; do
    if ! line=$(list_remote "$prefix" 2>"$errors"); then
      echo "r2      $prefix/ unreachable: $(tail -n 1 "$errors" | cut -c1-160)"; rc=1; continue
    fi
    if [ -z "$line" ]; then echo "r2      $prefix/ none"; [ "$prefix" = daily ] && rc=1; continue; fi
    read -r size name <<<"$(tail -n 1 <<<"$line")"
    age_s=$((now - $(stamp_epoch "$name")))
    echo "r2      $prefix/ newest  $name  $size bytes  $(human_age "$age_s") ago  ($(grep -c . <<<"$line") kept)"
    if [ "$prefix" = daily ] && [ "$age_s" -gt "$stale_seconds" ]; then echo "r2      daily is older than $((stale_seconds / 3600)) hours: STALE"; rc=1; fi
  done
  rm -f "$errors"
  return "$rc"
}

case "${1:-run}" in
  status) status; exit $? ;;
  prune) prune "${2:?prefix, for example daily/}" "${3:?how many to keep}"; exit 0 ;;
  run) ;;
  *) fail "unknown command $1" ;;
esac

[ -f deploy/backup-age.pub ] || fail "deploy/backup-age.pub is missing"
[ -n "${RCLONE_CONFIG_R2_ACCESS_KEY_ID:-}" ] || fail "R2 credentials are not loaded (/etc/scarlet-web/r2.env, run bin/deploy r2)"

stamp=$(date -u +%Y%m%dT%H%M%SZ)
name="scarlet-$stamp.dump"
file="backups/$name"
trap 'rm -f "$file.part" "$file.age.part"' EXIT

deploy/dc exec -T postgres pg_dump -U postgres -d scarlet -Fc > "$file.part" || fail "pg_dump"
mv "$file.part" "$file"
find backups -name 'scarlet-*.dump' -mmin +10080 -delete
echo "backup: $file $(du -h "$file" | cut -f1) (local copy kept 7 days)"

age -R deploy/backup-age.pub -o "$file.age.part" "$file" || fail "age encryption"
local_size=$(stat -c %s "$file.age.part")

rclone copyto "$file.age.part" "$(remote "daily/$name.age")" || fail "upload to daily/ (the local dump is kept)"
if [ "$(date -u +%u)" = 7 ]; then
  rclone copyto "$(remote "daily/$name.age")" "$(remote "weekly/$name.age")" || fail "copy to weekly/"
  weekly=" and weekly/$name.age"
fi
remote_size=$(list_remote daily | awk -v n="$name.age" '$2 == n {print $1}')
[ "$remote_size" = "$local_size" ] || fail "R2 has ${remote_size:-no object} for daily/$name.age, expected $local_size bytes"
echo "backup: uploaded daily/$name.age${weekly:-}, $local_size bytes, size checked in R2"

prune daily/ "$keep_daily"
prune weekly/ "$keep_weekly"
echo "backup: done"
