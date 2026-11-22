#!/bin/sh
# The `live` ring of scarlet-web's flow (roster, flows/scarlet-web.toml). The flow engine runs it on medaware, in a
# checkout at $FLOW_SHA, after every ship to main:
#
#   ring.sh deploy     build the image of $FLOW_SHA, send its new layers to the droplet and roll it out; medaware
#                      then keeps that image and the $kept_images - 1 newest others, and drops older ones
#   ring.sh check      the release that serves is $FLOW_SHA and the public pages answer 200 from the origin, uncached
#   ring.sh rollback   move visitors back to the release before, only when $FLOW_SHA is the one that serves
#
# It reaches the droplet with the ring's own key, which may run scripts/deploy/ring-entry.sh there and nothing else
# (docs/HOSTING.md, "Deploy ring"). That key cannot install a deploy folder, so when a file a rollout reads there
# ($rollout_reads) differs between $FLOW_SHA and the droplet the ring prints `skip:` and deploys nothing until
# `bin/deploy` runs once with the ops key. The rest of deploy/ runs from a PC or on another host and is not compared.
set -eu

key=${SCARLET_RING_KEY:-$HOME/.config/scarlet-web/ring_ed25519}
host=${DEPLOY_HOST:-scarlet-web}
site=${SCARLET_SITE:-https://scarletweb.io}
repo=${DEPLOY_REPO:-https://github.com/Medalink/scarlet-web.git}
port=${SCARLET_RING_PORT:-15078}
sha=${FLOW_SHA:?the flow engine sets FLOW_SHA}
release=$(printf '%s' "$sha" | cut -c1-12)
skipped=${TMPDIR:-/tmp}/scarlet-ring-skipped-$release
rollout_reads='[ *][.]/(rollout[.]sh|dc|compose[.]yaml|compose[.]redis-vpc[.]yaml|nginx/.*)$'
kept_images=5

ssh_opts="-i $key -o IdentitiesOnly=yes -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ServerAliveInterval=30"
entry() { ssh $ssh_opts "ops@$host" "$@"; }
skip() {
  : > "$skipped"
  echo "skip: $1"
  exit 0
}

another_ring_runs() {
  for pid in $(pgrep -f 'ring[.]sh deploy' || true); do
    [ "$pid" = "$$" ] || [ "$pid" = "$PPID" ] || return 0
  done
  return 1
}

take_lock() {
  tries=0
  until entry lock; do
    tries=$((tries + 1))
    if [ "$tries" -eq 1 ] && ! another_ring_runs; then
      echo "ring: no other ring runs here, so a ring lock on the droplet was left by a killed ring; freeing it" >&2
      entry unlock || true
      continue
    fi
    [ "$tries" -lt 200 ] || { echo "ring: the deploy lock stayed taken for 10 minutes" >&2; exit 1; }
    sleep 3
  done
  trap 'entry unlock || true; [ -z "${tunnel:-}" ] || kill "$tunnel" 2>/dev/null || true' EXIT
  trap 'exit 143' TERM
  trap 'exit 130' INT
  trap 'exit 129' HUP
}

deploy() {
  rm -f "$skipped"
  [ -f "$key" ] || skip "medaware has no ring key at $key (docs/HOSTING.md, Deploy ring)"
  [ "$(entry release)" != "$release" ] || { echo "ring: $release already serves"; return 0; }
  mine=$(cd deploy && find . -type f | LC_ALL=C sort | xargs sha256sum | grep -E "$rollout_reads")
  theirs=$(entry sums)
  theirs=$(printf '%s\n' "$theirs" | grep -E "$rollout_reads" || true)
  [ "$mine" = "$theirs" ] || skip "the files a rollout reads under deploy/ at $release differ from the droplet's; run bin/deploy once with the ops key"

  take_lock
  built=$(mktemp)
  bash deploy/build.sh "$repo" "$sha" > "$built" 2>&1 || true
  cat "$built"
  grep -qx "built $sha" "$built" || { rm -f "$built"; echo "ring: the build gave no image" >&2; exit 1; }
  rm -f "$built"
  entry registry
  ssh $ssh_opts -o ExitOnForwardFailure=yes -N -L "127.0.0.1:$port:127.0.0.1:5077" "ops@$host" > /dev/null 2>&1 &
  tunnel=$!
  tries=0
  until curl -sf --max-time 2 -o /dev/null "http://127.0.0.1:$port/v2/"; do
    tries=$((tries + 1))
    [ "$tries" -lt 150 ] && kill -0 "$tunnel" 2>/dev/null || { echo "ring: no path to the droplet's registry" >&2; exit 1; }
    sleep 0.1
  done
  at=127.0.0.1:$port/scarlet-web-app:$release
  sudo -n docker tag "scarlet-web-app:$release" "$at"
  pushed=0
  sudo -n docker push -q "$at" > /dev/null || pushed=1
  sudo -n docker rmi "$at" > /dev/null 2>&1 || true
  kill "$tunnel" 2>/dev/null || true
  wait "$tunnel" 2>/dev/null || true
  tunnel=
  [ "$pushed" = 0 ] || { echo "ring: the image did not reach the droplet's registry" >&2; exit 1; }

  entry pull "$release"
  entry rollout "$release"
  echo "ring: $release is live"
  sudo -n docker images scarlet-web-app --format '{{.Tag}}' | grep -vx "$release" | tail -n +"$kept_images" | while read -r tag; do
    sudo -n docker rmi "scarlet-web-app:$tag" > /dev/null 2>&1 || true
  done
}

check() {
  if [ -e "$skipped" ]; then
    rm -f "$skipped"
    echo "skip: nothing was deployed"
    return 0
  fi
  serving=$(entry release)
  [ "$serving" = "$release" ] || { echo "ring: $serving serves, not $release" >&2; exit 1; }
  bad=0
  for path in /up "/stats?v=$release" "/leaderboards?v=$release" "/rulesets?v=$release" "/talents?v=$release" "/map?v=$release" "/search?q=wolf&v=$release" /api/v1/public/stats; do
    code=$(curl -s -o /dev/null --max-time 30 -w '%{http_code}' "$site$path" || true)
    echo "ring: $code $path"
    [ "$code" = 200 ] || bad=1
  done
  [ "$bad" = 0 ] || { echo "ring: a public page did not answer 200 on $release" >&2; exit 1; }
}

rollback() {
  [ -f "$key" ] || return 0
  rm -f "$skipped"
  if [ "$(entry release)" != "$release" ]; then
    echo "ring: $release does not serve, so there is nothing to roll back"
    return 0
  fi
  take_lock
  entry rollback
  echo "ring: rolled back from $release to $(entry release)"
}

case "${1:-}" in
  deploy) deploy ;;
  check) check ;;
  rollback) rollback ;;
  *)
    echo "ring.sh deploy | check | rollback" >&2
    exit 2
    ;;
esac
