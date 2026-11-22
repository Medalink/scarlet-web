#!/bin/sh
# Installed as /usr/local/sbin/scarlet-log-stream (root-owned) by deploy/server-setup.sh. It is the forced command of the
# scarlet-logs key, through one sudoers entry for exactly this path, so that key can read the app's logs and do nothing else.
# The flow engine on medaware follows its output (roster flows/scarlet-web.toml, watch `logs`; docs/HOSTING.md, "Error watch").
#
# Output, one line each:
#   SCARLET-RELEASE <release>     first, then again whenever release.env names another release
#   SCARLET-LOG <fields>          one line per Laravel log record, from the logs volume (app/Support/Logging/OneLineFormatter.php)
#   SCARLET-5XX <status> <method> <route>   an nginx access line that answered 5xx, with its path cut to the route
#   anything else                 stderr and stdout of the app slots, Horizon, the scheduler and nginx's error log, minus
#                                 Horizon's RUNNING and DONE lines and the Laravel records already read from the volume
#   SCARLET-HEARTBEAT             every 30 seconds, so a closed connection is noticed
set -u
cd /srv/scarlet-web || exit 1
project=scarlet-web
state=$(mktemp -d /run/scarlet-log-stream.XXXXXX)

descendants() {
  for child in $(pgrep -P "$1" 2>/dev/null); do
    descendants "$child"
    echo "$child"
  done
}
cleanup() {
  trap '' TERM HUP INT PIPE EXIT
  kill $(descendants $$) 2>/dev/null
  rm -rf "$state"
  exit 0
}
trap cleanup TERM HUP INT PIPE EXIT

release() { sed -n 's/^SCARLET_RELEASE=//p' release.env 2>/dev/null | tail -n 1; }

nginx_filter() {
  awk '
    /^[0-9][0-9][0-9][0-9]\/[0-9][0-9]\/[0-9][0-9] [0-9:]+ \[(error|crit|alert|emerg)\]/ { print; fflush(); next }
    {
      n = split($0, q, "\"")
      if (n < 3) next
      split(q[3], f, " ")
      if (f[1] !~ /^5[0-9][0-9]$/) next
      split(q[2], r, " ")
      path = r[2]
      sub(/\?.*/, "", path)
      m = split(path, seg, "/")
      keep = (seg[2] == "api") ? 4 : 2
      route = ""
      for (i = 2; i <= m && i <= keep; i++) route = route "/" seg[i]
      if (m > keep) route = route "/*"
      printf "SCARLET-5XX %s %s %s\n", f[1], r[1], route
      fflush()
    }'
}

app_filter() {
  sed -u -e 's/\x1b\[[0-9;]*m//g' -e 's/\r$//' | grep --line-buffered -v -e '^SCARLET-LOG ' -e ' RUNNING$' -e ' DONE$'
}

follow() {
  since=$3
  case "$2" in
    laravel)
      dir=$(docker volume inspect "${project}_logs" --format '{{.Mountpoint}}' 2>/dev/null) || exit 0
      tail -n0 -F "$dir/laravel.log" 2>/dev/null | grep --line-buffered '^SCARLET-LOG '
      ;;
    web) docker logs -f "$since" "$1" 2>&1 | nginx_filter ;;
    *) docker logs -f "$since" "$1" 2>&1 | app_filter ;;
  esac
}

start() {
  name=$1
  [ -f "$state/$name" ] && kill -0 "$(cat "$state/$name")" 2>/dev/null && return
  follow "$@" &
  echo $! > "$state/$name"
}

sweep() {
  since=$1
  start laravel laravel "$since"
  docker ps --filter "label=com.docker.compose.project=$project" --format '{{.Names}} {{.Label "com.docker.compose.service"}}' 2>/dev/null |
    while read -r container service; do
      case "$service" in
        app-blue | app-green | horizon-* | schedule | web) echo "$container $service" ;;
      esac
    done > "$state/containers"
  while read -r container service; do
    start "$container" "$service" "$since"
  done < "$state/containers"
}

last=$(release)
[ -z "$last" ] || printf 'SCARLET-RELEASE %s\n' "$last"
sweep --tail=0
tick=0
while :; do
  sleep 5
  tick=$((tick + 1))
  now=$(release)
  if [ -n "$now" ] && [ "$now" != "$last" ]; then
    printf 'SCARLET-RELEASE %s\n' "$now"
    last=$now
  fi
  [ $((tick % 4)) -ne 0 ] || sweep --since=25s
  [ $((tick % 6)) -ne 0 ] || printf 'SCARLET-HEARTBEAT\n'
done
