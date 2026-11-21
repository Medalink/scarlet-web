#!/bin/sh
# Runs on the droplet as ops: `rollout.sh <release>` starts the images tagged <release>. `rollout.sh --rollback` starts
# the release before the current one. The images are already loaded (bin/deploy pipes them in).
#
# A release rolls out in this order: databases up, migrations and imports with the new image, then the new containers,
# then a health check. A failed health check starts the previous release again. Migrations are forward-only, so a
# rollback changes code only.
set -eu
cd /srv/scarlet-web
dc=deploy/dc
touch releases

current() { sed -n 's/^SCARLET_RELEASE=//p' release.env 2>/dev/null || true; }

wait_healthy() {
  i=0
  while [ "$i" -lt 60 ]; do
    if [ "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8081/up || true)" = 200 ]; then return 0; fi
    i=$((i + 1))
    sleep 2
  done
  return 1
}

start() {
  printf 'SCARLET_RELEASE=%s\n' "$1" > release.env
  $dc up -d --remove-orphans
}

if [ "${1:-}" = --rollback ]; then
  now=$(current)
  previous=$(grep -v "^$now\$" releases | tail -n 1 || true)
  [ -n "$previous" ] || { echo "rollout: no earlier release to go back to"; exit 1; }
  docker image inspect "scarlet-web-app:$previous" >/dev/null || { echo "rollout: image $previous is gone"; exit 1; }
  start "$previous"
  wait_healthy || { echo "rollout: release $previous did not become healthy"; exit 1; }
  echo "rollout: rolled back from $now to $previous (code only; migrations stay)"
  exit 0
fi

new=$1
old=$(current)
docker image inspect "scarlet-web-app:$new" "scarlet-web-web:$new" >/dev/null
[ -f release.env ] || printf 'SCARLET_RELEASE=%s\n' "$new" > release.env

$dc pull postgres redis
$dc up -d --wait postgres redis

artisan() { SCARLET_RELEASE=$new $dc run --rm --no-deps -T app php artisan "$@" --no-interaction; }
artisan migrate --force
artisan maps:load-rects
artisan app:release-notes:publish --from-file=storage/app/release-notes.json
artisan cache:clear

start "$new"
if wait_healthy; then
  grep -qx "$new" releases || echo "$new" >> releases
  echo "rollout: $new is serving (was ${old:-nothing})"
  # keep this release and the two before it
  for tag in $(docker images scarlet-web-app --format '{{.Tag}}' | tail -n +4); do
    docker rmi "scarlet-web-app:$tag" "scarlet-web-web:$tag" >/dev/null 2>&1 || true
  done
  exit 0
fi

echo "rollout: $new did not become healthy"
$dc logs --tail 40 app web || true
if [ -n "$old" ] && [ "$old" != "$new" ]; then
  start "$old"
  wait_healthy && echo "rollout: back on $old" || echo "rollout: $old is not healthy either"
fi
exit 1
