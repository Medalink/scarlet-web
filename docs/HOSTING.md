# Hosting

scarletweb.io runs on two DigitalOcean droplets: the app droplet behind Cloudflare, and the database droplet that holds
PostgreSQL on a block volume. The code that builds, ships and runs it is in `bin/deploy`, `deploy/` and
`docker/prod.Dockerfile`; this file says what it does and how to operate it.

## Layout

| Part | What |
| --- | --- |
| App droplet | `scarlet-web`, region nyc3, `s-2vcpu-4gb` (2 vCPU, 4 GB RAM, 80 GB disk), Ubuntu 24.04, 2 GB swap, DigitalOcean backups and monitoring on |
| Cloudflare | Zone `scarletweb.io` on the Free plan. Proxied `A` and `AAAA` records for the apex and `www`. SSL mode Full (strict), Authenticated Origin Pulls on, Always Use HTTPS on, minimum TLS 1.2, HSTS off |
| Containers | `web` (nginx), `app-blue` or `app-green` (php-fpm, one serves), `horizon-a`, `-b` or `-c` (the newest one works the queues), `schedule`, `registry` (image layers, loopback only), `redis`. `deploy/compose.yaml`. Its `postgres` service is the database until `bin/deploy db-move` runs, then a replica for 48 hours |
| Database droplet | `scarlet-db`, nyc3, `s-1vcpu-2gb` (1 vCPU, 2 GB RAM, 50 GB disk), Ubuntu 24.04, 2 GB swap, monitoring on, DigitalOcean backups off (they do not cover volumes). One container, `postgres` (TimescaleDB 2.32 with the Toolkit, PostgreSQL 18, `shared_buffers` 512 MB), `deploy/compose.db.yaml`. Data on the 50 GB volume `scarlet-db-data`, mounted at `/mnt/scarlet-db-data` |
| Access | Tailscale only. `ssh ops@scarlet-web` and `ssh ops@scarlet-db` with the servers' own key. The DigitalOcean web console is the fallback |
| Files on the app droplet | `/srv/scarlet-web`: `.env` (mode 600), `tls/`, `maps/`, `backups/`, `deploy/`; `/etc/scarlet-web/r2.env` (R2 credentials, root only) |
| Files on the database droplet | `/srv/scarlet-db`: `.env` (mode 600), `deploy/`, `etc/` (`pg_hba.conf`, `pgbackrest.conf`, TLS key pair), `backups/`; `/etc/scarlet-web/r2.env` (R2 credentials, root only) |

Request path: browser, Cloudflare, nginx on the droplet (port 443, checked against Cloudflare's client certificate),
php-fpm. nginx runs on the host network so it sees the real peer address. It trusts `CF-Connecting-IP` only from
Cloudflare's published ranges (`deploy/nginx/cloudflare-ips.conf`), so Laravel's `$request->ip()` and the rate limits
see the visitor, not Cloudflare. `www` redirects to the apex in nginx.

### Access and exposure

- The DigitalOcean cloud firewall `scarlet-web` (tag `scarlet-web`) lets in tcp 80 and 443 from Cloudflare's IPv4 and
  IPv6 ranges and nothing else. There is no inbound 22. Outbound is open. Docker publishes ports around `ufw`, so
  the cloud firewall is the guard, and nothing else is published: php-fpm listens on `127.0.0.1:9000` and Redis is on
  the compose network only.
- The cloud firewall `scarlet-web-db` (tag `scarlet-db`) lets in tcp 5432 from droplets with the tag `scarlet-web` and
  nothing else: no 22, no public 5432. Postgres runs on the host network and listens on the VPC address of the database
  droplet and loopback only. `pg_hba.conf` accepts the app droplet's VPC address with `hostssl` and `scram-sha-256`
  (the app sets `DB_SSLMODE=require`; the certificate is self-signed, so this encrypts and does not verify). The VPC
  `default-nyc3` is shared with other projects, which is why the firewall names a tag and not the network.
- To open ssh for a while, add a tcp 22 rule for one address to the `scarlet-web` firewall and remove it after.
- sshd allows the user `ops` with a key only; root login and passwords are off. `unattended-upgrades` is on and may
  reboot at 04:30 UTC on the app droplet and 05:45 UTC on the database droplet (the nightly dump runs at 03:15 and
  the pgBackRest backups at 02:00); every container restarts by itself.
- Tailscale's Tailnet Lock is on for the tailnet. A new node stays locked out until a trusted node runs
  `tailscale lock sign nodekey:<key>` (`tailscale lock status` on the trusted node shows the key).
- The Cloudflare IP ranges change rarely. To refresh them: `node deploy/cloudflare.mjs ips > deploy/nginx/cloudflare-ips.conf`,
  edit the firewall rules to match, then deploy.

## Deploy

From medaviper (Git Bash) or medaware, in a checkout of this repository:

```sh
bin/deploy            # build main, ship it, migrate, start it
bin/deploy <sha>      # a given commit
```

What it does: clones the full history on medaware (checkouts under `/data/<user>/scratch/scarlet-web-build`), builds
the `app` image through `fleet slot` (cargo for `scarlet-part`, composer, Vite), runs `app:version --strict` and
`app:release-notes:backfill` with the git history mounted for that one step so no `.git` is in the image. The image goes
to the droplet as the layers its registry lacks: `registry:3` runs on the droplet and listens on 127.0.0.1:5077 only;
`bin/deploy` opens `ssh -L` to it from the machine that runs the deploy and `ssh -R` from there to medaware, which
cannot reach the droplet, so medaware's `docker push` sends only new layers (a code-only release is a few MB) and the
droplet pulls them from its own registry. When the registry does not answer, or with `DEPLOY_TRANSPORT=stream`, it
falls back to `docker save | zstd | ssh | docker load` of the whole image. `bin/deploy` prints the seconds each phase took when it ends. On the droplet `deploy/rollout.sh`:

1. runs `migrate --force`, `maps:load-rects`, `app:release-notes:publish` and `cache:clear` with the new image while
   the old release still serves;
2. starts the new release as `app-blue` or `app-green`, whichever is idle, and waits for five answers in a row, half a second apart, from
   `/up` and `/release-notes` through nginx's own configuration (loopback ports 8091 and 8092);
3. copies the release's built assets into `/srv/scarlet-web/public`, where every release's assets stay, so a page
   from the old release still finds its files;
4. rewrites nginx's upstream (`/srv/scarlet-web/state/upstream.conf`) and runs `nginx -s reload`, not a restart: nginx
   finishes the requests it has;
5. waits until nginx's old workers have exited (5 seconds at most), then sends the old php-fpm SIGQUIT, which finishes
   its requests, and removes it;
6. starts the new release's Horizon (`horizon-a`, `-b` or `-c`, whichever has no container) and restarts the
   scheduler on it, then stops the old Horizon in the background with SIGTERM (Horizon's own terminate: running jobs
   finish, 150 seconds at most). The deploy does not wait for it; both Horizons work the queues meanwhile;
7. keeps the registry to the same releases as the droplet's images and collects the layers no release uses.

`rollout.sh` does not manage Postgres. While `DB_HOST` in `.env` is `postgres` it starts the local container when it is
missing and never recreates it for a new pin; with the database on its own host it touches no database container at all,
and `bin/deploy db upgrade` moves the database's pin.

A release that fails the health check never gets visitors: the old release keeps serving, the new container is
removed and the deploy exits 1. The images of the active release, the other slot and the last three releases stay on
the droplet.

nginx is not rebuilt per release. Its image is the pinned upstream one, its configuration is copied to
`/srv/scarlet-web/nginx` and reloaded, and the Cloudflare ranges, certificate and origin-pull switch are files it
reads, so none of that needs a restart either.

### The migration rule

A migration runs while the old code is still serving, and a rollback runs the old code against the new schema. So
every release must be safe with both: **expand first, contract in a later release.** Add tables and columns as
nullable or defaulted, add indexes without locking (`$withinTransaction = false`, `concurrently`), and deploy the code
that uses them. Drop or rename a column, change a type or tighten a constraint only in a later release, after no
running code reads the old shape. A migration that cannot satisfy this ships behind a flag that stays off until the
schema change is live, or in two releases. `migrationUnsafe` in the analyser enforces part of this.

`bin/deploy status` shows the containers, the release and the Cloudflare settings. Every base image is pinned by
digest in `docker/pins.tsv` and `docker/prod.Dockerfile`; `scripts/deps-age` checks they are at least 7 days old.

## Roll back

```sh
bin/deploy rollback
```

It starts the release before the current one beside the running one and moves visitors to it exactly as a deploy
does (health check, reload, drain), so it also drops no request. Migrations are forward-only and are not undone, so a
rollback changes code only; the expand-first rule above is what keeps the older code working. A second `rollback`
goes forward again.

## Logs and shells

```sh
bin/deploy logs web            # nginx access log: visitor address, Cloudflare peer, status, time
bin/deploy logs app-blue       # php-fpm and Laravel (stderr) of one slot; `bin/deploy status` shows which serves
bin/deploy logs horizon-a     # or -b, -c: `bin/deploy status` shows which runs
bin/deploy ssh                 # a shell on the droplet; deploy/dc is docker compose for the stack
bin/deploy ssh 'cd /srv/scarlet-web && deploy/dc exec horizon-a php artisan horizon:status'
```

The Laravel log is also in the `logs` volume (`storage/logs/laravel.log` in the app containers). Docker keeps three
10 MB files per container.

## Backups

- DigitalOcean backs up the whole app droplet daily and keeps 7 days (the droplet's Backups tab restores or rebuilds
  from one). It is the same DigitalOcean account as the droplet. It does not cover the database droplet's volume;
  see "Database host".
- `scarlet-backup.timer` runs `deploy/backup.sh` at 03:15 UTC, on the database droplet once the database has moved
  there (the app droplet's timer is then disabled). It dumps the `scarlet` database (`pg_dump -Fc`) into
  `/srv/scarlet-db/backups` (`/srv/scarlet-web/backups` before the move; local copies kept 7 days), encrypts the dump with age, copies it to Cloudflare R2 and
  prunes R2. `bin/deploy backup` starts the same unit now and exits 1 when it fails.
- `bin/deploy backups` (also the last lines of `bin/deploy status`) prints the unit's last result and the newest local
  and R2 copies with size and age. It exits 1 when the last run failed, R2 cannot be read (a missing bucket is named
  as such), or the newest `daily/`
  copy is older than 26 hours. With the database on its own host it also checks the WAL archive ("Database host").

### Off-site copy in Cloudflare R2

| Part | What |
| --- | --- |
| Bucket | `scarlet-web-backups`, one R2 API token limited to it (Object Read & Write) |
| Encryption | `age` to the public key in `deploy/backup-age.pub`, before the dump leaves the droplet. The droplet never has the private key, so someone who gets into the droplet or the bucket cannot read old backups |
| Private key | `E:\Dev\secrets\scarlet-web\backup-age.key` on medaviper only. Not in a repo, not on the droplet. **Copy it to a second place you control: if that file is lost, every R2 backup is unreadable.** |
| Credentials | `/etc/scarlet-web/r2.env` on the droplet, `root:root`, mode 600, loaded by systemd's `EnvironmentFile=` before the unit drops to `ops`. `bin/deploy r2` writes it from `r2-access-key-id`, `r2-secret-access-key` and `r2-endpoint` in the secrets folder. Never in the image or the repo |
| Tools | `age` 1.3.2 and `rclone` 1.76.0 installed on the droplet by `bin/deploy server`, pinned by sha256 in `deploy/backup-tools.tsv` (at least 7 days old) |
| Keys | `daily/scarlet-<UTC stamp>.dump.age` every night; on Sundays (UTC) a copy also goes to `weekly/` |
| Retention | A prune step in `backup.sh`, not an R2 lifecycle rule (the token cannot set one): it keeps the newest 14 objects in `daily/` and the newest 8 in `weekly/`, and deletes nothing when the listing fails or has no more than the limit. It runs only after an upload whose size was checked in R2. `backup.sh prune <prefix> <keep>` runs it by hand |

The job exits non-zero on any failure (dump, encryption, upload, size check, prune), so the unit shows as failed and
`bin/deploy backups` says so. Retention counts objects: an extra `bin/deploy backup` adds a `daily/` object, so the 14
copies then cover fewer than 14 days.

Set up or rotate the credentials: `bin/deploy r2` first, then `bin/deploy server` (installs age and rclone and the
unit; no container restarts). To make a new key pair, run `age-keygen -o backup-age.key`, commit the public key to
`deploy/backup-age.pub`, deploy, and keep the old private key for the old copies.

### Restore from R2

On a machine that has the private key (medaviper), with `age` and `rclone`:

```sh
S=E:/Dev/secrets/scarlet-web
export RCLONE_CONFIG_R2_TYPE=s3 RCLONE_CONFIG_R2_PROVIDER=Cloudflare RCLONE_CONFIG_R2_NO_CHECK_BUCKET=true
export RCLONE_CONFIG_R2_ACCESS_KEY_ID=$(cat $S/r2-access-key-id) RCLONE_CONFIG_R2_SECRET_ACCESS_KEY=$(cat $S/r2-secret-access-key) \
  RCLONE_CONFIG_R2_ENDPOINT=$(cat $S/r2-endpoint)
rclone lsf R2:scarlet-web-backups/daily/                 # pick a name; weekly/ has the Sunday ones
rclone copyto R2:scarlet-web-backups/daily/scarlet-<stamp>.dump.age scarlet.dump.age
age -d -i $S/backup-age.key -o scarlet.dump scarlet.dump.age
```

Then load `scarlet.dump` the way the next section shows, into a scratch database first if you only want to check it.
Delete `scarlet.dump` and `scarlet.dump.age` afterwards.

### Restore a dump into the database

Restore into an empty database (the order matters, TimescaleDB needs the two calls around `pg_restore`). A plain
dump on the droplet is `backups/scarlet-<stamp>.dump`; a dump from R2 is piped in from your machine instead:

```sh
bin/deploy ssh
cd /srv/scarlet-web
deploy/dc stop app-blue app-green horizon-a horizon-b horizon-c schedule web
deploy/dc exec -T postgres psql -U postgres -d postgres -c 'drop database scarlet' -c 'create database scarlet'
deploy/dc exec -T postgres psql -U postgres -d scarlet -c 'create extension if not exists timescaledb' \
  -c 'create extension if not exists timescaledb_toolkit' -c 'select timescaledb_pre_restore()'
deploy/dc exec -T postgres pg_restore -U postgres -d scarlet --no-owner < backups/scarlet-<stamp>.dump
# from R2: bin/deploy ssh 'cd /srv/scarlet-web && deploy/dc exec -T postgres pg_restore -U postgres -d scarlet --no-owner' < scarlet.dump
deploy/dc exec -T postgres psql -U postgres -d scarlet -c 'select timescaledb_post_restore()'
deploy/dc up -d web schedule horizon-a
cat state/active   # then: deploy/dc up -d app-blue (or app-green), the slot named there
```

`pg_dump` prints a warning about circular foreign keys on `continuous_agg`; the restore above ran clean from such a dump
into a scratch database (61 tables, 26 continuous aggregates, 15 hypertables).

## Database host

PostgreSQL runs on its own droplet, `scarlet-db`, with its data on the block volume `scarlet-db-data`. The app droplet
reaches it over the nyc3 VPC (`DB_HOST` is the droplet's private address, `DB_SSLMODE=require`). Redis stays on the app
droplet. `deploy/compose.db.yaml` is the stack, `deploy/db-host.sh` runs on the database droplet and
`deploy/db-cutover.sh` on the app droplet; `bin/deploy` calls both through `deploy/db-move.sh`.

### Install the database host

```sh
node deploy/provision.mjs db   # volume, firewall scarlet-web-db, droplet; signs the new node in the tailnet
bin/deploy r2                  # R2 credentials on the app droplet and the database droplet
bin/deploy db-server           # folders, TLS key pair, pg_hba.conf, pgbackrest.conf, age and rclone, systemd units
```

`provision.mjs` tags everything `scarlet-web` (the droplet also `scarlet-db`, the tag the firewall targets), formats and
mounts the volume in cloud-init, and runs `tailscale lock sign` for the new node, because the machine that provisions is
a trusted signing node. `bin/deploy db-server` makes the pgBackRest passphrase in
`E:\Dev\secrets\scarlet-web\pgbackrest-passphrase` when it is missing. **Copy that file to a second place you control**:
without it the WAL archive and the backups under `pgbackrest/` in the bucket cannot be read. It is also in
`/srv/scarlet-db/etc/pgbackrest.conf` on the database droplet (mode 600). The data folder is bound from
`/mnt/scarlet-db-data/pgdata` and never created by Docker, and `deploy/dc-db` refuses to start the container when the
volume is not mounted, so a missing volume cannot turn into a database on the root disk.

### Move the database

```sh
bin/deploy db-move
```

It holds the deploy lock for the whole move and runs these steps, which `deploy/db-move.sh` documents in its header:

1. The app droplet lets the database droplet copy it: the role `replicator`, one `pg_hba.conf` line for the compose
   network, the slot `scarlet_db_move`, and a relay container (nginx `stream`) on its Tailscale address, port 15432.
   The live firewall is not touched, and the old Postgres container is not recreated.
2. The database droplet copies it with `pg_basebackup` and follows it as a standby.
3. `.env` gets `DB_HOST` and `DB_SSLMODE=require`; the idle slot starts with it and passes its health check (reads
   work on the standby); Horizon and the scheduler stop (queued jobs wait in Redis).
4. The old Postgres goes read-only (`ALTER SYSTEM`, not a database setting, so nothing read-only replicates), writers
   still in a transaction are ended after 5 seconds, the standby replays to the last WAL position and is promoted,
   and nginx moves to the new slot. Reads never fail. Writes fail from the read-only switch until the nginx reload: 3 to
   4 seconds in the rehearsals.
5. On the new primary: `pgbackrest stanza-create`, `check`, a first full backup, the timers (weekly full, daily
   differential, a one-record heartbeat every minute) and the first nightly dump. The app droplet's dump timer is
   disabled.
6. The old Postgres is re-seeded from the new one and follows it as a replica (slot `scarlet_reverse`). The earlier data
   folder stays beside it as `data.pre-move`. `scarlet-db-retire.timer` removes the replica 48 hours later.

A move that fails before step 4 puts `.env` back, removes the idle slot and the relay and leaves the old Postgres
writable (`bin/deploy db-move abort` does the same by hand). After a promotion that stops early,
`bin/deploy db-move finish` runs steps 5 and 6.

### Move back

For 48 hours after the move the old Postgres follows the new one:

```sh
bin/deploy db-move rollback
```

It runs the same cutover the other way: the database droplet goes read-only, the old Postgres replays to its last
position, is promoted, and visitors move to a slot that uses it. The database droplet's container and timers are stopped
(its volume keeps the data); `DEPLOY_DB_FORCE_SEED=1 bin/deploy db-move` seeds it again, deleting its pgBackRest stanza
first, because the archive of the first attempt has WAL for the same timelines. After `scarlet-db-retire.timer` has run
(`bin/deploy db-move retire` runs it now) the old Postgres and its volume are gone; the way back is then a restore from
pgBackRest or R2. `retire` refuses when the database droplet is not a primary or has not archived WAL in 10 minutes.

### Status and upgrades

```sh
bin/deploy db status      # app side (DB_HOST, the local replica) and the database droplet
bin/deploy backups        # the unit's result, R2 copies, and the WAL archive
bin/deploy db upgrade     # push this checkout's deploy/ and recreate Postgres when its pin or settings changed
```

`db status` prints the role, the age of the last archived WAL segment, the last pgBackRest backup with its WAL range,
volume use and the replica's lag. `bin/deploy backups` and `bin/deploy db status` exit 1 when the last archived segment
is older than 5 minutes, the archiver's last attempt failed, or pgBackRest has no backup. The heartbeat makes an idle
database archive a segment every minute, so a segment older than 5 minutes means a real problem. `db upgrade` accepts
only the same PostgreSQL major version; recreating the container takes a few seconds without a database.

### Backups of the database host

| Part | What |
| --- | --- |
| WAL and backups | pgBackRest 2.60 from the Postgres image, repository on R2 under `pgbackrest/` in the bucket `scarlet-web-backups` (the nightly prune only touches `daily/` and `weekly/`). `archive_mode=on`, `archive_timeout=60`, repository encryption `aes-256-cbc`, 2 full backups kept |
| Schedule | Full on Sunday 02:00 UTC, differential Monday to Saturday 02:00 UTC (`scarlet-pgbackrest-full.timer`, `scarlet-pgbackrest-diff.timer`) |
| Nightly dump | `deploy/backup.sh` runs on the database droplet now: `scarlet-backup.timer` at 03:15 UTC, `daily/` and `weekly/` in R2 as before. The age key is still the only way to read them |
| Settings | In `/srv/scarlet-db/etc/pgbackrest.conf`; nothing is set with `ALTER SYSTEM`, so a replica seeded from this database carries none of it |
| Volume | DigitalOcean droplet backups do not cover volumes and are off on `scarlet-db`. Grow the volume online in the control panel, then `sudo resize2fs /dev/disk/by-id/scsi-0DO_Volume_scarlet-db-data` |

### Restore to a point in time

Into a scratch container on the database droplet (this is what was proven; it reads the repository and changes nothing):

```sh
ssh ops@scarlet-db
cd /srv/scarlet-db
img=$(deploy/dc-db config --images | grep timescaledb-ha)
sudo install -d -o 1000 -g 1000 -m 700 /srv/scarlet-db/scratch
docker run --rm --user 1000:1000 -v /srv/scarlet-db/scratch:/home/postgres/pgdata -v /srv/scarlet-db/etc:/etc/scarlet-db:ro \
  -e PGBACKREST_CONFIG=/etc/scarlet-db/pgbackrest.conf --entrypoint pgbackrest "$img" \
  --stanza=scarlet --type=time "--target=2026-11-21 22:58:35.200430+00" --target-action=promote --delta restore
docker run -d --name scratch-pg --user 1000:1000 --network host -v /srv/scarlet-db/scratch:/home/postgres/pgdata -v /srv/scarlet-db/etc:/etc/scarlet-db:ro \
  -e PGBACKREST_CONFIG=/etc/scarlet-db/pgbackrest.conf --entrypoint postgres "$img" -D /home/postgres/pgdata/data -p 5433 \
  -c listen_addresses=127.0.0.1 -c archive_mode=off -c max_connections=80 -c shared_buffers=128MB
docker exec scratch-pg psql -p 5433 -U postgres -d scarlet -c 'select count(*) from parts'
docker rm -f scratch-pg; sudo rm -rf /srv/scarlet-db/scratch
```

`max_connections` must be at least the primary's (80), or recovery stops. To replace the live database, stop the
container, empty `/mnt/scarlet-db-data/pgdata/data`, run the same `restore` (without `--target` for the newest state,
and without `--delta`), start it with `deploy/dc-db up -d`, then run `pgbackrest check` and take a full backup. That
last procedure has not been rehearsed.

### Lose the droplet

- The volume is intact: create a new droplet and attach `scarlet-db-data` to it in the control panel (or create it with
  `node deploy/provision.mjs db` after detaching the volume and naming it in the script), run `bin/deploy db-server`,
  then `deploy/dc-db up -d`. No data is lost. Update `DB_HOST` in the app droplet's `.env`; the firewall follows the tag.
- The volume is gone: restore from pgBackRest as above. Data since the last archived WAL segment (about a minute) is lost.
- Both: restore the newest `daily/` dump as in "Restore from R2".

None of the three has been rehearsed.

### What is proven

On throwaway droplets `scarlet-load-app` and `scarlet-load-db` (since destroyed), with a copy of production restored from
the R2 dump of 2026-11-21 19:50 UTC and the real `bin/deploy` commands:

- `pgbackrest check` passed on the new primary; the first full backup was 137 MB.
- A point-in-time restore into a scratch container matched `count(*)` and `bit_xor` over `parts` at the target time
  (1815 rows, `bit_xor` 2251567104037075678) and differed from the table after it (1835 rows, 4593370470403114045).
- A loop of 10 reads and 1 signed write per second (`deploy/db-probe.mjs`: `/release-notes`, `/stats` and
  `POST /api/v1/installs/link-code`) ran through each move. The first rehearsal had 0 failed reads in 5,188 and writes
  failing for 3 to 4 seconds. The second, on the code as shipped, had 2 failed reads in 5,004 (both "socket hang up",
  1 ms apart, at the nginx switch) and 4 failed writes of 500 in a 3.2 second window; `db-move rollback` had 0 failed
  reads in 4,048 and 4 failed writes of 404. A normal `bin/deploy` against the remote database: 0 failures in 3,502
  requests, and the replica container was not recreated.
- The app's connections reach Postgres from the app droplet's VPC address with TLS on; Postgres listens on the VPC
  address and loopback only.

### Managed database

If self-hosting stops being worth it, Tiger Cloud (Timescale's managed service) runs the same extensions from about $30
to $36 a month at this size. Migrations would have to run without superuser rights first.

## Map art

The map tiles are not in git or in the image. They live on the droplet at `/srv/scarlet-web/maps` (mounted read-only
at `/maps`, `MAPS_ROOT=/maps`), so a deploy never touches them. Build them as DEV.md says (`tools/map-tiles`), then:

```sh
bin/deploy maps <folder with manifest.json>     # normally storage/app/maps of a dev checkout
```

nginx serves `/map-tiles/` straight from that folder (mounted into `web` at `/var/www/map-tiles`): no PHP and no
cookie, so Cloudflare caches the tiles. The site reads `manifest.json` for the current version, so a new build is live
once it is copied. Never commit the tiles.

## Edge cache

Cloudflare caches three kinds of answer, each only for a visitor with no credentials. A request with an `Authorization`
header, a `scarlet_session` cookie or a `remember_web_*` cookie is never cached and never served from cache; the rules
and the origin use the same test.

| What | Origin sends | Edge result |
| --- | --- | --- |
| `/map-tiles/` | nginx from disk, `public, immutable`, no cookie | cached for a year |
| `/api/v1/public/*` | `public, max-age=300, s-maxage=300` (`private` with an `Authorization` header) | cached 5 minutes |
| The public pages (`routes/pages.php`, `/`, `/demo`) | `App\Http\Middleware\EdgeCacheable`: no session, no cookie, `s-maxage=60` | cached 1 minute |

`node deploy/cloudflare.mjs cache-rules` writes the two Cache Rules (Free plan: `http.cookie` and header names work in
them) and `status` lists them. Their edge TTL is `bypass_by_default`: the origin's `Cache-Control` decides, so an
answer with no `public` marker, an error or a `Set-Cookie` is not stored. A character who switches their page to private
stays visible in the edge for up to a minute.

## Setting up again from nothing

Needs the secrets folder (`SCARLET_SECRETS`, default `E:/Dev/secrets/scarlet-web` on Windows): `do-token`,
`cloudflare-token`, `tailscale-authkey`, `ops_ed25519` and `ops_ed25519.pub`, and for the database host the R2 files
(`r2-access-key-id`, `r2-secret-access-key`, `r2-endpoint`) and `pgbackrest-passphrase`.

1. `node deploy/provision.mjs` uploads the ops key, creates the tag and the firewall, then the droplet from
   `deploy/cloud-init.yaml` (ops user, key-only ssh, unattended upgrades, Docker, Tailscale, swap). It touches only
   resources named `scarlet-web`.
2. The same script signs the new node in the tailnet when Tailnet Lock is on, from a machine that is a trusted signing node.
3. `bin/deploy bootstrap` makes the server folder and `.env` (its secrets are generated on the droplet), points the
   DNS records at it, gets an Origin CA certificate for the apex and `*.scarletweb.io` (the key and CSR are made on
   the droplet; only the CSR leaves it), deploys main, checks the certificate serves, switches the zone to Full
   (strict), then turns on Authenticated Origin Pulls. Turning origin pulls on first, or strict first, makes every
   request fail, which is why the order is fixed.
4. The database droplet: `node deploy/provision.mjs db`, `bin/deploy r2`, `bin/deploy db-server`, `bin/deploy db-move`
   ("Database host"). Without it the app runs on the `postgres` container of its own droplet.

## Cost

| Part | Per month |
| --- | --- |
| App droplet `s-2vcpu-4gb` | $24, plus 20% for DigitalOcean backups ($4.80) |
| Database droplet `s-1vcpu-2gb` | $12 |
| Volume `scarlet-db-data`, 50 GB | $5 |
| Cloudflare Free plan, R2 | nothing at this size |

The database host adds $17 a month. Prices are from DigitalOcean's size list, read on 2026-11-21.

## What the owner registers

Sign-in needs two OAuth clients, one per provider. Their ids and secrets go in `/srv/scarlet-web/.env` on the server
(`bin/deploy ssh`, edit, then `bin/deploy` the current release again: the new containers read the file), never in the repo. They are
empty today, so both buttons on `/login` are disabled. `<domain>` is `scarletweb.io`, `APP_URL` is
`https://scarletweb.io`, and `SESSION_SECURE_COOKIE` is on. Scarlet asks each provider for the `openid` scope only and
keeps the provider name and the provider's subject id.

### Google

1. Google Cloud console, choose or create a project, APIs and Services, OAuth consent screen: user type External,
   app name and support contact as the owner chooses, scopes: add none beyond the default `openid`.
2. Credentials, Create credentials, OAuth client ID, application type **Web application**.
3. Authorized redirect URIs, production: `https://<domain>/auth/google/callback`.
4. Authorized redirect URIs, local development: `http://127.0.0.1:<port>/auth/google/callback`, with the port that
   `bin/dev status` prints for the checkout (8470 to 8569, see [DEV.md](../DEV.md)). Add one URI per port in use.
5. Environment: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`. The redirect URI is built from the request host; set
   `GOOGLE_REDIRECT_URI` only to override it. Google gets a PKCE challenge (S256) and a `state`.

### Battle.net

1. Battle.net developer portal (community.developer.battle.net), API Access, Create Client.
2. Redirect URLs, production: `https://<domain>/auth/battlenet/callback`.
3. Redirect URLs, local development: `http://127.0.0.1:<port>/auth/battlenet/callback`, with the same port rule.
4. Scopes: `openid` only. Service URL: `https://<domain>`. Intended use as the owner describes the site.
5. Environment: `BATTLENET_CLIENT_ID`, `BATTLENET_CLIENT_SECRET`, `BATTLENET_REGION`: `us`, `eu`, `kr`, `tw` or `cn`
   (default `us`). The region picks the authorize and token host (`<region>.battle.net`, `battlenet.com.cn` for `cn`),
   and a Battle.net account logs in through one region, so pick the one the players use. Battle.net gets a `state`
   check and no PKCE: its published OpenID metadata lists no code challenge method.

### Local development without client ids

`SCARLET_DEV_LOGIN=true` with `APP_ENV=local` adds a "Development sign-in" button to `/login`, which signs in as one
fixed identity through the same Action as the callbacks. The route is not registered in any other environment and the
Action refuses to run there, so production shows no such button. A provider with no client id shows its button
disabled, and `/auth/<provider>/redirect` answers `503` with "Sign in with <provider> is not set up on this server."

### What is proven

Proven without client ids, in a running app: the redirect to each provider's authorization URL (`scope=openid`, a
`state`, Google's PKCE challenge, the redirect URI), the disabled buttons, and everything after the provider answers
(account and identity rows, linking, unlinking, the account page) through the development sign-in.

Proven on https://scarletweb.io on 2026-11-21: a real round trip with Google and with Battle.net (region `us`, through
`us.battle.net`), each ending signed in on `/account` with both identities linked to one account. Not proven: that
either provider accepts an `http://127.0.0.1:<port>` redirect URL for a local client.

## Version numbers and release notes

The page footer and `/release-notes` read two files the image build writes while it has the full git history
(`docker/prod.Dockerfile`, the step that mounts the history for `app:version --strict` and
`app:release-notes:backfill --all --output=storage/app/release-notes.json`). Neither is in git. `deploy/rollout.sh`
runs `php artisan app:release-notes:publish --from-file=storage/app/release-notes.json` after `migrate` and then
`cache:clear`. The import reads no git, rejects a snapshot from another build, and keeps each release's date and every
account's read state when it runs again. Flat mode is not used: it needs a commit of the generated file after every
commit. Git hooks are not installed (`app:version:install-hooks`): PHP exists only in containers, so a host hook would
fail on Windows and on the ship machine. Without `version.json` the site shows the `VERSION` file with build `0`.

## The static website

The landing page and the demo character page move into the app unchanged. Until the app has a public home, the
static pages keep serving from GitHub Pages on the `gh-pages` branch of this repo (a copy is in `static-site/`).
Retiring them is part of the same decision: now that the app is live, replace the `gh-pages` pages with a redirect to
`https://scarletweb.io`, then delete `static-site/`.
