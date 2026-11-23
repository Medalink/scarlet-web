#!/bin/sh
# The map art's way to the droplet. The art (map tiles, dungeon maps, scene art, item icons) is never in git, so medaware
# keeps the one copy every deploy reads, the art store at $store, and this script runs there:
#
#   art.sh publish <tar>   add the folder packed in <tar> to the store and delete <tar>. `bin/deploy maps <folder>` sends
#                          the folder from a dev checkout and runs this. Only .json, .png and .webp files under plain
#                          folder names are taken; anything else refuses the whole archive
#   art.sh sync            send the droplet every store file it lacks or holds with other bytes, the .json files last so
#                          that no page reads a list before its files. scripts/deploy/ring.sh runs it before every
#                          rollout and `bin/deploy maps` runs it after a publish, so the droplet cannot fall behind the
#                          store. It fails when the droplet's art still differs afterwards
#
# Neither deletes a file: a release that serves, or one a rollback returns to, may still read older art. sync reaches the
# droplet with the ring's key over the connection pool ring.sh shares (the same ControlMaster options), whose entry
# (scripts/deploy/ring-entry.sh) has the verbs `mapsums` and `maps`, and sends
# at most $batch_bytes or $batch_files files in one archive, which stays under the entry's limit. publish refuses what
# sync could not send later: a path over $path_limit characters or a file over $batch_bytes. `bin/deploy maps` pipes this file to `sh -s`, so every ssh here names its
# own stdin. The names an archive may hold are the ones ring-entry.sh takes; change both together.
set -eu

key=${SCARLET_RING_KEY:-$HOME/.config/scarlet-web/ring_ed25519}
host=${DEPLOY_HOST:-scarlet-web}
store=${SCARLET_ART_STORE:-/data/medalink/scarlet-web/maps}
batch_bytes=268435456
batch_files=20000
path_limit=99
art_file='^[.](/[A-Za-z0-9][A-Za-z0-9._-]*)+[.](json|png|webp)$'
art_folder='^[.]/$|^[.](/[A-Za-z0-9][A-Za-z0-9._-]*)+/$'

ssh_opts="-i $key -o IdentitiesOnly=yes -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ServerAliveInterval=30"
mux_opts="-o ControlMaster=auto -o ControlPath=$HOME/.ssh/scarlet-ring-cm-%C -o ControlPersist=600"
entry() { ssh $ssh_opts $mux_opts "ops@$host" "$@"; }
fail() {
  echo "art: $1" >&2
  exit 1
}

hold_store() {
  exec 8> "$store.lock"
  flock -w 600 8 || fail "another publish or sync held $store for 10 minutes"
}

sums_of_store() { (cd "$store" && find . -type f -print0 | LC_ALL=C sort -z | xargs -0 -r sha256sum) | LC_ALL=C sort; }
sums_of_droplet() {
  entry mapsums < /dev/null > "$work/listed" || fail "the droplet did not list its art"
  LC_ALL=C sort "$work/listed"
}
behind() { LC_ALL=C comm -23 "$work/here" "$1" | cut -c67-; }

publish() {
  [ -f "$pack" ] || fail "publish takes the tar archive of an art folder"
  trap 'rm -rf "$work"; rm -f "$pack"' EXIT
  tar -tf "$pack" > "$work/names" 2> /dev/null || fail "$pack is not a tar archive"
  stray=$(grep -Ev "$art_file|$art_folder" "$work/names" | head -n 1 || true)
  [ -z "$stray" ] || fail "$stray is not map art: the store takes .json, .png and .webp files under folders named with letters, digits, dots, dashes and underscores"
  [ "$(tar -tvf "$pack" | grep -c '^[-d]')" = "$(wc -l < "$work/names")" ] || fail "the folder holds something that is neither a file nor a folder"
  long=$(awk -v limit="$path_limit" 'length($0) > limit' "$work/names" | head -n 1)
  [ -z "$long" ] || fail "$long is longer than $path_limit characters"
  heavy=$(tar --numeric-owner -tvf "$pack" | awk -v limit="$batch_bytes" '$3 > limit { print $NF }' | head -n 1)
  [ -z "$heavy" ] || fail "$heavy is larger than $batch_bytes bytes"
  files=$(grep -Ec "$art_file" "$work/names" || true)
  [ "$files" -gt 0 ] || fail "the folder holds no art"
  mkdir -p "$store"
  hold_store
  tar -C "$store" --no-same-owner --no-same-permissions -xf "$pack" || fail "the archive did not unpack into $store"
  chmod -R u=rwX,go=rX "$store"
  echo "art: the store on $(hostname) took $files files"
}

sync() {
  [ -f "$key" ] || fail "$(hostname) has no ring key at $key (docs/HOSTING.md, Deploy ring)"
  [ -d "$store" ] || fail "$(hostname) has no art store at $store (docs/HOSTING.md, Map art)"
  hold_store
  sums_of_store > "$work/here"
  stray=$(cut -c67- "$work/here" | grep -Ev "$art_file" | head -n 1 || true)
  [ -z "$stray" ] || fail "the store holds $stray, which is not map art; take it out of $store"
  long=$(cut -c67- "$work/here" | awk -v limit="$path_limit" 'length($0) > limit' | head -n 1)
  [ -z "$long" ] || fail "the store holds $long, a path longer than $path_limit characters; take it out of $store"
  sums_of_droplet > "$work/there"
  behind "$work/there" > "$work/behind"
  total=$(wc -l < "$work/here")
  count=$(wc -l < "$work/behind")
  [ "$count" -gt 0 ] || {
    echo "art: the droplet has all $total files of the store"
    return 0
  }
  {
    grep -v '[.]json$' "$work/behind" || true
    grep '[.]json$' "$work/behind" || true
  } > "$work/ordered"
  (cd "$store" && tr '\n' '\0' < "$work/ordered" | xargs -0 stat -c '%s %n') | awk -v limit="$batch_bytes" -v most="$batch_files" -v dir="$work" '
    BEGIN { batch = 1 }
    {
      size = $1
      sub(/^[0-9]+ /, "")
      if (files >= most || (held > 0 && held + size > limit)) { batch++; held = 0; files = 0 }
      held += size
      files++
      print > (dir "/batch." batch)
    }
    END { print batch > (dir "/batches") }'
  batches=$(cat "$work/batches")
  batch=1
  while [ "$batch" -le "$batches" ]; do
    (cd "$store" && tar --format=ustar --no-recursion -cf "$work/pack" -T "$work/batch.$batch") || fail "could not pack the art"
    refused=0
    entry maps "$(sha256sum < "$work/pack" | cut -c1-64)" < "$work/pack" > /dev/null || refused=$?
    [ "$refused" -eq 0 ] || fail "the droplet did not take archive $batch of $batches (exit $refused); $count files are behind"
    batch=$((batch + 1))
  done
  sums_of_droplet > "$work/after"
  still=$(behind "$work/after" | head -n 1)
  [ -z "$still" ] || fail "the droplet's art still differs after the copy, first at $still"
  echo "art: sent $count of the store's $total files to the droplet"
}

verb=${1:-}
pack=${2:-}
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

case "$verb" in
  publish) publish ;;
  sync) sync ;;
  *)
    echo "art.sh publish <tar> | sync" >&2
    exit 2
    ;;
esac
