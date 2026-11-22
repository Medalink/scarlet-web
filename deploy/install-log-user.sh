#!/bin/sh
# Runs on the droplet as ops, from /srv/scarlet-web/deploy (server-setup.sh calls it; safe to run again). Makes the
# scarlet-logs user the flow engine's error watch logs in as: its key (deploy/log-stream.pub) is forced to
# /usr/local/sbin/scarlet-log-stream through one sudoers entry for exactly that path. docs/HOSTING.md, "Error watch".
set -eu
cd /srv/scarlet-web

id scarlet-logs > /dev/null 2>&1 || sudo useradd --no-create-home --home-dir /home/scarlet-logs --shell /bin/sh --comment 'scarlet error watch' scarlet-logs
sudo install -d -o root -g root -m 755 /home/scarlet-logs /home/scarlet-logs/.ssh
sudo install -o root -g root -m 755 deploy/log-stream.sh /usr/local/sbin/scarlet-log-stream

pub=$(tr -d '\r\n' < deploy/log-stream.pub)
case $pub in ssh-ed25519\ *) ;; *) echo "server: deploy/log-stream.pub is not an ssh-ed25519 public key" >&2; exit 1 ;; esac
printf 'restrict,command="sudo -n /usr/local/sbin/scarlet-log-stream" %s\n' "$pub" | sudo install -o root -g root -m 644 /dev/stdin /home/scarlet-logs/.ssh/authorized_keys

tmp=$(mktemp)
trap 'rm -f "$tmp"' EXIT
printf 'Defaults:scarlet-logs !use_pty\nscarlet-logs ALL=(root) NOPASSWD: /usr/local/sbin/scarlet-log-stream\n' > "$tmp"
sudo visudo -cf "$tmp" > /dev/null
sudo install -o root -g root -m 440 "$tmp" /etc/sudoers.d/scarlet-logs

conf=/etc/ssh/sshd_config.d/20-scarlet-logs.conf
printf 'AllowUsers scarlet-logs\n' > "$tmp"
if ! sudo cmp -s "$tmp" "$conf"; then
  sudo install -o root -g root -m 644 "$tmp" "$conf"
  if ! sudo sshd -t || ! sudo sshd -T | grep -i '^allowusers ' | grep -iq 'scarlet-logs' || ! sudo sshd -T | grep -i '^allowusers ' | grep -iqw 'ops'; then
    sudo rm -f "$conf"
    echo "server: sshd would not allow both ops and scarlet-logs; nothing changed" >&2
    exit 1
  fi
  sudo systemctl reload ssh
fi
echo "server: scarlet-logs may run /usr/local/sbin/scarlet-log-stream and nothing else"
