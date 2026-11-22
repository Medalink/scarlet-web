#!/bin/bash
# Runs on the build host (medaware), piped in by bin/deploy: `bash -s -- <repo> <ref>`.
# Clones the full history, checks out the ref and builds the app image through `fleet slot`, so the compile waits its
# turn. Prints `built <sha>` as its last line. Checkouts live under /data/<user>/scratch, never on the 4 GB droplet.
# The image copies the checkout with its file modes and php-fpm reads it as www-data, so every file and folder is made
# readable by others whatever umask the caller has: the flow engine runs with 077, which left the files a checkout
# rewrote readable by their owner only.
set -euo pipefail
umask 022
repo=$1
ref=$2
export PATH="$HOME/Dev/roster/bin:$PATH"
base=/data/$(id -un)/scratch/scarlet-web-build
mkdir -p "$base"
cd "$base"

[ -d src/.git ] || git clone --quiet "$repo" src
cd src
git remote set-url origin "$repo"
# `app:version` and the release notes need every commit and tag: a shallow clone is deepened.
if [ "$(git rev-parse --is-shallow-repository)" = true ]; then git fetch --quiet --unshallow --tags origin; fi
git fetch --quiet --tags --prune origin
git fetch --quiet origin "$ref" 2>/dev/null || true
sha=$(git rev-parse --verify "origin/$ref^{commit}" 2>/dev/null || git rev-parse --verify "$ref^{commit}")
git checkout --quiet --force --detach "$sha"
git clean -ffdxq
find . -path ./.git -prune -o -exec chmod go+rX {} +
tag=${sha:0:12}

now() { date +%s.%N; }
phase() { awk -v a="$2" -v b="$3" -v n="$1" 'BEGIN { printf "phase %s %.1f\n", n, b - a }'; }

started=$(mktemp)
trap 'rm -f "$started"' EXIT
slot() { if command -v fleet >/dev/null; then fleet slot -- "$@"; else "$@"; fi; }
t_wait=$(now)
slot bash -c 'date +%s.%N > "$1"; shift; exec "$@"' _ "$started" \
  sudo -n docker build -f docker/prod.Dockerfile --target app --build-context "gitdir=$PWD/.git" -t "scarlet-web-app:$tag" .
t_done=$(now)
t_start=$(cat "$started")
phase slot-wait "$t_wait" "$t_start"
phase docker-build "$t_start" "$t_done"
echo "built $sha"
