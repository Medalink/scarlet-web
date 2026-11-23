#!/bin/sh
# Runs on the droplet as ops, once the image is there, started by bin/deploy or by the live ring's entry
# (scripts/deploy/ring-entry.sh):
#   rollout.sh <release>     migrate, then start <release> beside the running one and move visitors to it
#   rollout.sh --rollback    move visitors back to the release before the current one (code only)
#
# This file, deploy/dc, the compose files and deploy/nginx/ are the files a rollout reads. A ship that changes one can
# reach the droplet after the release it ships with has rolled out, so that release must also run on the copy before it
# (docs/HOSTING.md, "Files a rollout reads").
#
# Blue/green with no failed request: the new release starts as app-blue or app-green next to the active slot, answers
# /up (which queries Postgres) five times in a row and renders /release-notes once, through nginx's own configuration
# (loopback ports 8091 and 8092), and only then does nginx's upstream move to it with a reload. nginx finishes the
# requests it has, the old php-fpm gets SIGQUIT and finishes
# its own, then stops. A release that fails its check never gets visitors: the old one keeps serving and this exits 1.
# Horizon: the new release's Horizon starts beside the old one (horizon-a, -b or -c, whichever has no container), which
# stops gracefully in the background (running jobs finish), so no deploy waits for a job. The scheduler is stopped and
# started on the new release.
#
# Migrations run before the switch while the old code still serves. They must be expand-only (docs/HOSTING.md).
#
# Roles: SCARLET_ROLES (bin/deploy passes the host's line of deploy/hosts.tsv; kept in release.env) says what this host runs.
# web: nginx and the blue/green php-fpm slots. ingest: Horizon with the ingest supervisor. scheduler: the scheduler, Redis,
# the registry, migrations and the other Horizon supervisors. A host with no SCARLET_ROLES runs all three, as one droplet always did.
set -eu
cd /srv/scarlet-web
dc=deploy/dc
mkdir -p state public nginx
touch releases release.env
# One rollout at a time: agents deploy in parallel, and two rollouts would race for the same blue/green slot.
exec 9>state/rollout.lock
if ! flock -n 9; then
  echo "rollout: another rollout is running; waiting for it"
  flock 9
fi

get() { sed -n "s/^$1=//p" release.env | tail -n 1; }
put() {
  grep -v "^$1=" release.env > release.env.new || true
  printf '%s=%s\n' "$1" "$2" >> release.env.new
  mv release.env.new release.env
}
roles=${SCARLET_ROLES:-$(get SCARLET_ROLES)}
roles=${roles:-web ingest scheduler}
[ "$roles" = "$(get SCARLET_ROLES)" ] || put SCARLET_ROLES "$roles"
has_role() { case " $roles " in *" $1 "*) return 0 ;; esac; return 1; }

nginx_changed=0
if has_role web; then
  nginx_changed=$(diff -rq deploy/nginx nginx 2>/dev/null | grep -Ec '^(Files .* differ|Only in deploy/nginx)' || true)
  cp -a deploy/nginx/. nginx/
  [ -f state/upstream.conf ] || echo 'upstream php { server 127.0.0.1:9000; }' > state/upstream.conf
  if [ ! -f state/aop.conf ]; then
    if [ -f aop.conf ]; then mv aop.conf state/aop.conf; else cp deploy/nginx/aop-off.conf state/aop.conf; fi
  fi
  [ -f tls/origin-pull-ca.pem ] || cp deploy/nginx/origin-pull-ca.pem tls/origin-pull-ca.pem
fi
slot_port() { if [ "$1" = blue ]; then echo 9001; else echo 9002; fi; }
check_port() { if [ "$1" = blue ]; then echo 8091; else echo 8092; fi; }
slot_var() { if [ "$1" = blue ]; then echo BLUE_RELEASE; else echo GREEN_RELEASE; fi; }
active_slot() { cat state/active 2>/dev/null || echo none; }
phase() { awk -v a="$2" -v b="${3:-$(date +%s.%N)}" -v n="$1" 'BEGIN { printf "phase %s %.1f\n", n, b - a }'; }
nginx_cmd='nginx -c /etc/nginx/scarlet/nginx.conf'
reload_nginx() { $dc exec -T web $nginx_cmd -t && $dc exec -T web $nginx_cmd -s reload; }

fpm_listening() {
  $dc exec -T "app-$1" php -r 'exit(@fsockopen("127.0.0.1", 9000) ? 0 : 1);' >/dev/null 2>&1
}

healthy() {
  port=$(check_port "$1")
  good=0
  tries=0
  while [ "$tries" -lt 240 ]; do
    if ! fpm_listening "$1"; then
      tries=$((tries + 1))
      sleep 0.5
      continue
    fi
    up=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$port/up" || true)
    if [ "$up" = 200 ]; then good=$((good + 1)); else good=0; fi
    if [ "$good" -ge 5 ]; then
      page=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$port/release-notes" || true)
      [ "$page" = 200 ] && return 0
      echo "rollout: /release-notes answered $page"
      return 1
    fi
    tries=$((tries + 1))
    sleep 0.5
  done
  return 1
}

nginx_workers_drained() {
  n=0
  while [ "$n" -lt 10 ]; do
    $dc exec -T web ps 2>/dev/null | grep -q 'shutting down' || return 0
    n=$((n + 1))
    sleep 0.5
  done
}

service_containers() {
  docker ps "$@" --filter label=com.docker.compose.project=scarlet-web --filter label=com.docker.compose.service="$svc_name"
}

running_horizons() {
  for svc_name in horizon horizon-blue horizon-green horizon-a horizon-b horizon-c; do service_containers -q; done
}

idle_horizon() {
  for svc_name in horizon-a horizon-b horizon-c; do
    [ -n "$(service_containers -aq)" ] || { echo "$svc_name"; return; }
  done
  for svc_name in horizon-a horizon-b horizon-c; do
    [ -n "$(service_containers -q)" ] || { echo "$svc_name"; return; }
  done
  echo horizon-a
}

drain_without_rollout_lock() {
  [ -n "$1" ] || return 0
  setsid sh -c 'docker stop -t 150 "$@" >/dev/null 2>&1; docker rm "$@" >/dev/null 2>&1' drain $1 9>&- >/dev/null 2>&1 </dev/null &
}

# The built assets of every release stay in one folder, so a page from the old release still finds its files.
sync_assets() {
  container=$(docker create "scarlet-web-app:$1")
  docker cp "$container:/app/public/." public/
  docker rm "$container" >/dev/null
  chmod -R a+rX public
}

slots() {
  cur=$(active_slot)
  nxt=blue
  if [ "$cur" = blue ]; then nxt=green; fi
}

start_slot() {
  release=$1
  slots
  docker image inspect "scarlet-web-app:$release" >/dev/null

  t_start=$(date +%s.%N)
  put "$(slot_var "$nxt")" "$release"
  sync_assets "$release" &
  assets_job=$!
  $dc up -d --no-deps "app-$nxt"
  if ! healthy "$nxt"; then
    echo "rollout: $release did not pass its health check; $( [ "$cur" = none ] && echo 'nothing is serving' || echo "$cur keeps serving")"
    $dc logs --no-log-prefix --tail 40 "app-$nxt" || true
    $dc stop "app-$nxt" >/dev/null 2>&1 || true
    $dc rm -f "app-$nxt" >/dev/null 2>&1 || true
    return 1
  fi

  phase start-health "$t_start"
}

swap_workers() {
  put SCARLET_RELEASE "$1"
  retiring=$(running_horizons)
  workers=
  if has_role ingest || has_role scheduler; then workers=$(idle_horizon); fi
  if has_role scheduler; then workers="$workers schedule"; fi
  [ -n "$workers" ] || return 0
  $dc stop $workers >/dev/null 2>&1 || true
  $dc rm -f $workers >/dev/null 2>&1 || true
  $dc up -d --no-deps $workers
  drain_without_rollout_lock "$retiring"
  for svc in $workers; do
    if ! $dc ps --services --status running "$svc" | grep -qx "$svc"; then
      echo "rollout: $svc is not running on $1" >&2
      $dc logs --no-log-prefix --tail 40 "$svc" >&2 || true
      return 1
    fi
  done
}

switch_slot() {
  release=$1
  slots
  t_switch=$(date +%s.%N)
  wait "$assets_job" || { echo "rollout: copying the assets of $release failed; $cur keeps serving"; return 1; }
  cp state/upstream.conf state/upstream.conf.prev
  printf 'upstream php { server 127.0.0.1:%s; }\n' "$(slot_port "$nxt")" > state/upstream.conf.new
  mv state/upstream.conf.new state/upstream.conf
  if ! reload_nginx; then
    mv state/upstream.conf.prev state/upstream.conf
    echo "rollout: nginx refused the new upstream; $cur keeps serving"
    return 1
  fi
  echo "$nxt" > state/active.new && mv state/active.new state/active
  echo "rollout: visitors moved to $nxt ($release)"
  phase switch-nginx "$t_switch"
  t_old=$(date +%s.%N)

  nginx_workers_drained
  if [ "$cur" != none ]; then
    $dc stop -t 60 "app-$cur" >/dev/null
    $dc rm -f "app-$cur" >/dev/null
  fi
  docker rm -f scarlet-web-app-1 >/dev/null 2>&1 || true

  phase stop-old-app "$t_old"
  t_workers=$(date +%s.%N)
  swap_workers "$release" || return 1
  phase start-workers "$t_workers"
  phase switch "$t_switch"
}

activate() {
  if has_role web; then
    start_slot "$1" || return 1
    switch_slot "$1"
    return
  fi
  t_workers=$(date +%s.%N)
  swap_workers "$1" || return 1
  phase start-workers "$t_workers"
}

prune_images() {
  keep=" $(get BLUE_RELEASE) $(get GREEN_RELEASE) $(get SCARLET_RELEASE) $(tail -n 3 releases | tr '\n' ' ')"
  for tag in $(docker images scarlet-web-app --format '{{.Tag}}'); do
    case "$keep" in *" $tag "*) ;; *) docker rmi "scarlet-web-app:$tag" >/dev/null 2>&1 || true ;; esac
  done
  for tag in $(docker images scarlet-web-web --format '{{.Tag}}'); do docker rmi "scarlet-web-web:$tag" >/dev/null 2>&1 || true; done
}

prune_registry() {
  api=http://127.0.0.1:5077/v2/scarlet-web-app
  accept='application/vnd.oci.image.index.v1+json,application/vnd.oci.image.manifest.v1+json,application/vnd.docker.distribution.manifest.v2+json,application/vnd.docker.distribution.manifest.list.v2+json'
  keep=" $(get BLUE_RELEASE) $(get GREEN_RELEASE) $(get SCARLET_RELEASE) $(tail -n 3 releases | tr '\n' ' ')"
  deleted=0
  tags=$(curl -sf "$api/tags/list" | sed -n 's/.*"tags":\[\(.*\)\].*/\1/p' | tr -d '"' | tr ',' ' ') || return 0
  for tag in $tags; do
    case "$keep" in *" $tag "*) continue ;; esac
    digest=$(curl -sfI -H "Accept: $accept" "$api/manifests/$tag" | tr -d '\r' | sed -n 's/^[Dd]ocker-[Cc]ontent-[Dd]igest: //p')
    [ -n "$digest" ] && curl -sf -X DELETE "$api/manifests/$digest" >/dev/null && deleted=$((deleted + 1))
  done
  [ "$deleted" -eq 0 ] || $dc exec -T registry registry garbage-collect --delete-untagged /etc/distribution/config.yml >/dev/null 2>&1
}

if [ "${1:-}" = --prepare ]; then
  slots
  start_slot "$(get SCARLET_RELEASE)" || exit 1
  wait "$assets_job"
  echo "$nxt" > state/prepared
  echo "rollout: $nxt is ready beside $cur"
  exit 0
fi
if [ "${1:-}" = --switch ]; then
  [ -s state/prepared ] || { echo "rollout: no slot is prepared"; exit 1; }
  slots
  [ "$(cat state/prepared)" = "$nxt" ] || { echo "rollout: the prepared slot is $(cat state/prepared) but the idle slot is $nxt; not switching"; exit 1; }
  $dc ps --services --status running "app-$nxt" | grep -qx "app-$nxt" || { echo "rollout: app-$nxt is not running; not switching"; exit 1; }
  sync_assets "$(get SCARLET_RELEASE)" &
  assets_job=$!
  switch_slot "$(get SCARLET_RELEASE)" || exit 1
  rm -f state/prepared
  exit 0
fi
if [ "${1:-}" = --unprepare ]; then
  slots
  $dc stop "app-$nxt" >/dev/null 2>&1 || true
  $dc rm -f "app-$nxt" >/dev/null 2>&1 || true
  rm -f state/prepared
  exit 0
fi

if [ "${1:-}" = --rollback ]; then
  now=$(get SCARLET_RELEASE)
  previous=$(grep -v "^$now\$" releases | tail -n 1 || true)
  [ -n "$previous" ] || { echo "rollout: no earlier release to go back to"; exit 1; }
  activate "$previous" || exit 1
  echo "rollout: rolled back from $now to $previous (code only; migrations stay)"
  exit 0
fi

new=$1
docker image inspect "scarlet-web-app:$new" >/dev/null
t_migrate=$(date +%s.%N)
infra=
if has_role scheduler; then infra="redis redis-cache"; fi
if has_role web; then infra="$infra web"; fi
if [ -n "$infra" ]; then
  $dc pull --quiet --policy missing $infra
  if has_role scheduler && grep -qx "DB_HOST=postgres" .env; then $dc up -d --no-recreate --wait postgres; fi
  $dc up -d --wait $infra
  if has_role web && [ "$nginx_changed" -ne 0 ]; then reload_nginx; fi
fi
phase compose-up "$t_migrate"
t_migrate=$(date +%s.%N)

if has_role scheduler; then
  if [ -f deploy/db-app-role.sh ]; then bash deploy/db-app-role.sh; fi
  SCARLET_RELEASE=$new $dc run --rm --no-deps -T tools sh -ec '
    php artisan migrate --force --no-interaction
    php artisan scarlet:storage-policies --no-interaction
    php artisan maps:load-rects --no-interaction
    php artisan app:release-notes:publish --from-file=storage/app/release-notes.json --no-interaction
    php artisan cache:clear --no-interaction'
fi
phase migrate "$t_migrate"

activate "$new" || exit 1
grep -qx "$new" releases || echo "$new" >> releases
t_prune=$(date +%s.%N)
prune_images
if has_role scheduler; then prune_registry || echo "rollout: the registry was not pruned"; fi
phase prune "$t_prune"
echo "rollout: $new is serving"
