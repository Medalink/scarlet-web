#!/bin/sh
# Installs age and rclone on a server: the versions in deploy/backup-tools.tsv, checked against its sha256 before anything is
# installed. Run from the folder that holds deploy/ (/srv/scarlet-web or /srv/scarlet-db). The R2 credentials are not here:
# `bin/deploy r2` writes /etc/scarlet-web/r2.env.
set -eu
tool_row() { awk -F'\t' -v t="$1" '$1 == t' deploy/backup-tools.tsv; }
fetch_tool() {
  url=$(tool_row "$1" | cut -f3)
  sum=$(tool_row "$1" | cut -f4)
  curl -fsSL -o "$2" "$url"
  echo "$sum  $2" | sha256sum -c - >/dev/null || { echo "server: $1 download does not match its pinned sha256" >&2; exit 1; }
}
tmp=$(mktemp -d)
want_age=$(tool_row age | cut -f2)
if [ "$(age --version 2>/dev/null || true)" != "v$want_age" ]; then
  fetch_tool age "$tmp/age.tgz"
  tar -C "$tmp" -xzf "$tmp/age.tgz"
  sudo install -m 755 "$tmp/age/age" /usr/local/bin/age
  echo "server: installed age $want_age"
fi
want_rclone=$(tool_row rclone | cut -f2)
if [ "$(rclone version 2>/dev/null | sed -n '1s/^rclone v//p')" != "$want_rclone" ]; then
  fetch_tool rclone "$tmp/rclone.deb"
  sudo dpkg -i "$tmp/rclone.deb" >/dev/null
  echo "server: installed rclone $want_rclone"
fi
rm -rf "$tmp"
