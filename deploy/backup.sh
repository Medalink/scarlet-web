#!/bin/sh
# A custom-format dump of the scarlet database into /srv/scarlet-web/backups, kept for 7 days.
# The systemd timer scarlet-backup runs it every night; run it by hand before a risky change.
set -eu
cd /srv/scarlet-web
umask 077
file="backups/scarlet-$(date -u +%Y%m%dT%H%M%SZ).dump"
deploy/dc exec -T postgres pg_dump -U postgres -d scarlet -Fc > "$file.part"
mv "$file.part" "$file"
find backups -name 'scarlet-*.dump' -mmin +10080 -delete
echo "backup: $file $(du -h "$file" | cut -f1), $(ls backups | grep -c '^scarlet-.*dump$') kept"
