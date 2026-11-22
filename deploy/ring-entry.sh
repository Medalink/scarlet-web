#!/bin/sh
# The only thing the flow ring's key may run on the app droplet. `bin/deploy ring-key` installs this file as
# /usr/local/sbin/scarlet-deploy-entry (owned by root, outside the deploy folder a release replaces) and makes it the
# forced command of the ring's line in ~ops/.ssh/authorized_keys2, so that key gets no shell. It takes one of the
# verbs below and nothing else: the key can roll out an image, roll back and read status. It cannot change a file,
# install a deploy folder or reach the database host.
#
#   lock | unlock     take or free the deploy lock bin/deploy uses; unlock frees only a lock this entry took
#   registry          start the loopback image registry when it is down
#   pull <release>    take scarlet-web-app:<release> from the loopback registry
#   rollout <release> deploy/rollout.sh <release>
#   rollback          deploy/rollout.sh --rollback
#   release           the release that serves
#   sums              sha256 of every file under deploy/, to compare with a commit
#   status            the containers and the release file
set -euf
cd /srv/scarlet-web
set -- ${SSH_ORIGINAL_COMMAND:-}
verb=${1:-}
release=${2:-}
lock=state/deploy.lock
registry=127.0.0.1:5077

words() { [ "$1" -eq "$2" ] || { echo "ring: $verb takes $(($2 - 1)) argument(s)" >&2; exit 2; }; }
a_release() { printf '%s' "$release" | grep -Eq '^[0-9a-f]{12}$' || { echo "ring: a release is 12 hex digits" >&2; exit 2; }; }

case "$verb" in
  lock)
    words $# 1
    mkdir -p state
    if [ -f $lock/at ] && [ $(($(date +%s) - $(cat $lock/at))) -gt 2400 ]; then rm -rf $lock; fi
    mkdir $lock 2>/dev/null || { echo "ring: another deploy holds the lock" >&2; exit 75; }
    date +%s > $lock/at
    echo ring > $lock/by
    ;;
  unlock)
    words $# 1
    [ "$(cat $lock/by 2>/dev/null)" != ring ] || rm -rf $lock
    ;;
  registry)
    words $# 1
    curl -sf -o /dev/null http://$registry/v2/ || deploy/dc up -d --no-deps --wait registry >&2
    ;;
  pull)
    words $# 2
    a_release
    docker pull -q $registry/scarlet-web-app:$release > /dev/null
    docker tag $registry/scarlet-web-app:$release scarlet-web-app:$release
    docker rmi $registry/scarlet-web-app:$release > /dev/null
    ;;
  rollout)
    words $# 2
    a_release
    exec deploy/rollout.sh "$release"
    ;;
  rollback)
    words $# 1
    exec deploy/rollout.sh --rollback
    ;;
  release)
    words $# 1
    sed -n 's/^SCARLET_RELEASE=//p' release.env | tail -n 1
    ;;
  sums)
    words $# 1
    cd deploy
    set +f
    find . -type f | LC_ALL=C sort | xargs sha256sum
    ;;
  status)
    words $# 1
    deploy/dc ps
    cat release.env
    ;;
  *)
    echo "ring: this key runs lock, unlock, registry, pull, rollout, rollback, release, sums and status only" >&2
    exit 2
    ;;
esac
