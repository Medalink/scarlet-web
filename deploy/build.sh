#!/bin/bash
# Runs on the build host (medaware), piped in by bin/deploy: `bash -s -- <repo> <ref>`.
# Clones the full history, checks out the ref and builds the two images through `fleet slot`, so the compile waits its
# turn. Prints `built <sha>` as its last line. Checkouts live under /data/<user>/scratch, never on the 4 GB droplet.
set -euo pipefail
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
tag=${sha:0:12}

slot() { if command -v fleet >/dev/null; then fleet slot -- "$@"; else "$@"; fi; }
slot sudo -n docker build -f docker/prod.Dockerfile --target app --build-context "gitdir=$PWD/.git" -t "scarlet-web-app:$tag" .
slot sudo -n docker build -f docker/prod.Dockerfile --target web -t "scarlet-web-web:$tag" .
echo "built $sha"
