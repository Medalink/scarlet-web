#!/bin/sh
# The `live` ring of scarlet-web's flow (roster, flows/scarlet-web.toml). The flow engine runs it on medaware, in a
# checkout at $FLOW_SHA, after every ship to main:
#
#   ring.sh deploy     send the droplet the map art it lacks (scripts/deploy/art.sh sync), build the image of $FLOW_SHA,
#                      send its new layers to the droplet and roll it out; medaware then keeps that image and the
#                      $kept_images - 1 newest others, and drops older ones
#   ring.sh check      the release that serves is $FLOW_SHA and the public pages answer 200 from the origin, uncached
#   ring.sh rollback   move visitors back to the release before, only when $FLOW_SHA is the one that serves
#
# It reaches the droplet with the ring's own key, which may run scripts/deploy/ring-entry.sh there and nothing else
# (docs/HOSTING.md, "Deploy ring"). Every ship rolls out. When a file a rollout reads on the droplet ($rollout_files)
# differs between $FLOW_SHA and the droplet, the ring sends this commit's copies to the entry's `install` verb before
# the rollout, and fails when the entry refuses them. The rest of deploy/ runs from a PC or on another host and is not
# compared. The art goes before the build, so a release never rolls out ahead of the art in medaware's store, and a
# deploy whose art does not arrive fails before it touches the release. A commit that already serves gets no sync: a
# failed step makes the engine run `ring.sh rollback`, which would then take a good release down over its art.
#
# A deploy lock someone else holds is waited for, $lock_wait seconds at most: the droplet clears a lock older than 40
# minutes, so the wait ends with the lock. Any other answer to the lock request fails the ring at once.
set -eu

key=${SCARLET_RING_KEY:-$HOME/.config/scarlet-web/ring_ed25519}
host=${DEPLOY_HOST:-scarlet-web}
site=${SCARLET_SITE:-https://scarletweb.io}
repo=${DEPLOY_REPO:-https://github.com/Medalink/scarlet-web.git}
port=${SCARLET_RING_PORT:-15078}
sha=${FLOW_SHA:?the flow engine sets FLOW_SHA}
release=$(printf '%s' "$sha" | cut -c1-12)
rollout_files='rollout.sh dc compose.yaml compose.redis-vpc.yaml nginx'
rollout_reads="^[0-9a-f]{64}  [.]/($(printf '%s' "$rollout_files" | sed 's/[.]/[.]/g; s/ /|/g'))(/|\$)"
lock_wait=2520
lock_held=75
kept_images=5

ssh_opts="-i $key -o IdentitiesOnly=yes -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ServerAliveInterval=30"
entry() { ssh $ssh_opts "ops@$host" "$@"; }

another_ring_runs() {
  pids=$(mktemp)
  pgrep -f 'ring[.]sh deploy' > "$pids" || true
  found=1
  while read -r pid; do
    [ "$pid" = "$$" ] || [ "$pid" = "$PPID" ] || found=0
  done < "$pids"
  rm -f "$pids"
  return "$found"
}

take_lock() {
  give_up_at=$(($(date +%s) + lock_wait))
  waits=0
  while :; do
    answer=0
    if [ "$waits" -eq 0 ]; then entry lock || answer=$?; else entry lock 2> /dev/null || answer=$?; fi
    [ "$answer" -ne 0 ] || break
    [ "$answer" -eq "$lock_held" ] || { echo "ring: the droplet did not give the deploy lock (exit $answer)" >&2; exit 1; }
    waits=$((waits + 1))
    if [ "$waits" -eq 1 ]; then
      echo "ring: waiting for the deploy lock" >&2
      if ! another_ring_runs; then
        echo "ring: no other ring runs here, so a ring lock on the droplet was left by a killed ring; freeing it" >&2
        entry unlock || true
        continue
      fi
    fi
    [ "$(date +%s)" -lt "$give_up_at" ] || { echo "ring: the deploy lock stayed taken for $((lock_wait / 60)) minutes" >&2; exit 1; }
    sleep 3
  done
  trap 'entry unlock || true; [ -z "${tunnel:-}" ] || kill "$tunnel" 2>/dev/null || true' EXIT
  trap 'exit 143' TERM
  trap 'exit 130' INT
  trap 'exit 129' HUP
}

sums_here() { (cd deploy && find . -type f | LC_ALL=C sort | xargs sha256sum | grep -E "$rollout_reads"); }
sums_there() {
  all=$(entry sums)
  printf '%s\n' "$all" | grep -E "$rollout_reads" || true
}
differing() {
  { printf '%s\n' "$1"; printf '%s\n' "$2"; } | LC_ALL=C sort | uniq -u | cut -c69- | sed '/^$/d' | LC_ALL=C sort -u | tr '\n' ' ' | sed 's/ $//'
}

carry_rollout_files() {
  mine=$(sums_here) || { echo "ring: this commit has none of the files a rollout reads under deploy/" >&2; exit 1; }
  theirs=$(sums_there) || { echo "ring: the droplet did not list its deploy/ files" >&2; exit 1; }
  [ "$mine" != "$theirs" ] || return 0
  changed=$(differing "$mine" "$theirs")
  present=
  for name in $rollout_files; do
    [ ! -e "deploy/$name" ] || present="$present $name"
  done
  pack=$(mktemp)
  (cd deploy && tar -cf "$pack" $present) || { rm -f "$pack"; echo "ring: could not pack this commit's copy of: $changed" >&2; exit 1; }
  installed=0
  entry install "$release" "$(sha256sum < "$pack" | cut -c1-64)" < "$pack" || installed=$?
  rm -f "$pack"
  [ "$installed" -eq 0 ] || { echo "ring: the droplet did not install this commit's copy of: $changed (exit $installed)" >&2; exit 1; }
  [ "$mine" = "$(sums_there)" ] || { echo "ring: the droplet's copy still differs after the install: $changed" >&2; exit 1; }
  echo "ring: installed this commit's copy of: $changed"
}

deploy() {
  [ -f "$key" ] || { echo "ring: medaware has no ring key at $key (docs/HOSTING.md, Deploy ring)" >&2; exit 1; }
  [ "$(entry release)" != "$release" ] || { echo "ring: $release already serves"; return 0; }
  sh scripts/deploy/art.sh sync

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
  carry_rollout_files
  entry rollout "$release"
  echo "ring: $release is live"
  sudo -n docker images scarlet-web-app --format '{{.Tag}}' | grep -vx "$release" | tail -n +"$kept_images" | while read -r tag; do
    sudo -n docker rmi "scarlet-web-app:$tag" > /dev/null 2>&1 || true
  done
}

check() {
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
