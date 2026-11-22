# Hosting

scarletweb.io runs on two DigitalOcean droplets: the app droplet behind Cloudflare, and the database droplet that holds
PostgreSQL on a block volume. The code that builds, ships and runs it is in `bin/deploy`, `deploy/` and
`docker/prod.Dockerfile`; this file says what it does and how to operate it.

## Layout

| Part | What |
| --- | --- |
| App droplet | `scarlet-web`, region nyc3, `s-2vcpu-4gb` (2 vCPU, 4 GB RAM, 80 GB disk), Ubuntu 24.04, 2 GB swap, DigitalOcean backups and monitoring on |
| Cloudflare | Zone `scarletweb.io` on the Free plan. Proxied `A` and `AAAA` records for the apex and `www`. SSL mode Full (strict), Authenticated Origin Pulls on, Always Use HTTPS on, minimum TLS 1.2, HSTS off |
| Containers | `web` (nginx), `app-blue` or `app-green` (php-fpm, one serves), `horizon-a`, `-b` or `-c` (the newest one works the queues), `schedule`, `registry` (image layers, loopback only), `redis` (queue and Horizon), `redis-cache` (cache and sessions). `deploy/compose.yaml`. Its `postgres` service is the database until `bin/deploy db-move` runs, then a replica for 48 hours |
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
  the cloud firewall is the guard, and nothing else is published: php-fpm listens on `127.0.0.1:9000` and both Redis
  instances are on the compose network only.
- The cloud firewall `scarlet-web-db` (tag `scarlet-db`) lets in tcp 5432 from droplets with the tag `scarlet-app` and
  nothing else: no 22, no public 5432. The app droplet has the tags `scarlet-web` and `scarlet-app`; the database droplet
  has only `scarlet-db`, so the public firewall does not apply to it and a `scarlet-load-*` droplet is not a source.
  Postgres runs on the host network and listens on the VPC address of the database droplet and loopback only; `status`
  reports STALE when either address stops listening (it is the check for a late VPC interface after a reboot, which
  was not tested by rebooting). `pg_hba.conf` accepts the app droplet's VPC address with `hostssl` and
  `scram-sha-256`. The app sets `DB_SSLMODE=verify-ca` with `DB_SSLROOTCERT=/etc/scarlet-dbtls/db-ca.crt`, a copy of the
  database droplet's self-signed `server.crt` that `bin/deploy db-server` puts in `/srv/scarlet-web/dbtls`; without that
  file `set-env` falls back to `require`, which encrypts and does not verify. The VPC `default-nyc3` is shared with other
  projects, which is why the firewall names a tag and not the network.
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

## Hosts and roles

`deploy/hosts.tsv` lists the hosts, one line each: the tailnet name, the roles, and optional settings.

```
scarlet-web	web ingest scheduler
scarlet-db	db
```

| Role | What the host runs |
| --- | --- |
| `web` | nginx and the blue/green php-fpm slots, the maps, the origin certificate |
| `ingest` | Horizon with `supervisor-ingest`, which works the `ingest` queue first and the `backfill` queue when `ingest` is empty |
| `scheduler` | `schedule`, Redis (both instances), the registry and the migrations of a release, and Horizon with `supervisor-rollups`, `-armory` and `-default` |
| `db` | Postgres alone (`bin/deploy db-server`) |

Exactly one host has `scheduler` and exactly one has `db`; `bin/deploy` refuses any other inventory before it touches a
host. The scheduler's entries also use `onOneServer()`. Settings are `KEY=value` pairs separated by commas, written into
that host's `.env` on each release: `SCARLET_INGEST_PROCESSES` (default 2, the supervisor's `maxProcesses`),
`SCARLET_ROLLUPS_PROCESSES`, `SCARLET_ARMORY_PROCESSES` and `SCARLET_DEFAULT_PROCESSES`. Horizon starts only the
supervisors of the host's roles (`SCARLET_ROLES`, which `rollout.sh` keeps in `release.env`); a host that sets none runs all
three roles, which is the one-droplet stack.

Backfill work (`rulesets:refile`, `ingest:redecode`, `talents:backfill`) queues `IngestSession` on `backfill`. The
scheduler runs `ingest:redecode --outdated` every ten minutes, which queues the next 1000 sessions an older decoder
wrote whenever the backfill queue is empty, so a decoder that changed its rows rebuilds every stored session by itself. The ingest supervisor takes `ingest` before `backfill`, so a backlog of re-decodes
waits behind live uploads. A job in the backfill queue has its own identity, so a live upload of the same session is
queued as well and a lock on the session (`IngestSession::handle`, kept in the queue's Redis) makes two decodes of one
session wait for each other on any host.

### Add a host

1. Create the droplet: `node deploy/provision.mjs web --name scarlet-app-2` (the next name is `scarlet-app-<x>`). It has the
   tags `scarlet-web` and `scarlet-app`, so the database firewall accepts it.
2. Add its line to `deploy/hosts.tsv`, for example `scarlet-app-2	ingest	SCARLET_INGEST_PROCESSES=4`, and ship.
3. `bin/deploy` does the rest before the first rollout (`bin/deploy hosts prepare` does it without a release):
   - `node deploy/provision.mjs redis --droplet scarlet-web` creates the firewall `scarlet-web-redis` (tcp 6379 and 6380
     from the tag `scarlet-app`, to the droplet tagged `scarlet-redis`);
   - the scheduler host publishes Redis on its VPC address only (`deploy/compose.redis-vpc.yaml`, which `deploy/dc` adds when
     `state/redis-vpc.env` exists): 6379 for the queue, 6380 for the cache and sessions, both with the Redis password.
     This recreates the two Redis containers once (each saves its snapshot on stop and loads it again);
   - the database host's `pg_hba.conf` gets the VPC address of every app host (`deploy/db-hba.sh`, then
     `pg_reload_conf()`);
   - the new host gets the primary's `.env` with `REDIS_HOST` and `REDIS_CACHE_HOST` set to the scheduler host's VPC address,
     the database certificate and, with the `web` role, the origin certificate;
   - the image arrives through the scheduler host's registry (a second `ssh -R`), or as `docker save` when the registry is
     not answering.
4. A second `web` host also needs a Cloudflare load balancer or a second origin address; `bin/deploy dns` writes only the
   scheduler host's address.

A host needs the database on its own host (`bin/deploy db-move`) first. With one app host nothing above runs: no firewall,
no published Redis port, the same containers.

### What is proven

On throwaway droplets `scarlet-load-roles-app` (`web ingest scheduler`), `-db` and `-ingest` (since destroyed; the rehearsal
used `deploy/provision.mjs ... --env load --set roles` and a hosts file with one more line):

- `bin/deploy` with the third line prepared the new host itself (firewall, published Redis, `pg_hba.conf`, `.env`) and rolled
  out: the app host ran `app-*`, `web`, `horizon-*`, `schedule`, `redis`, `redis-cache`, `registry`; the ingest host ran one
  `horizon-*` and nothing else. `horizon:supervisors` listed `supervisor-ingest` (2 workers on `ingest,backfill`) for the
  ingest host's master, and all four supervisors for the app host's.
- 800 `IngestSession` jobs queued from the app host: 576 ran on the app host and 224 on the ingest host, none failed. After a
  reboot of the app host (Redis came back on its VPC address), 400 more split 260 and 140.
- A second `schedule` started by hand on the ingest host next to the first: at 03:30:00 `horizon:snapshot` ran on one,
  `ingest:sweep` and `rulesets:detect` on the other, and each said it skipped the entries the first had taken.
- Ports 6379, 6380 and 5432 on the app host's public address were closed.
- An inventory with no scheduler host, two scheduler hosts, an unknown role, a duplicate host or a database host with other
  roles is refused before any host is touched.

Not exercised: a second `web` host (nginx, blue/green slots and the certificate copy are written, a Cloudflare origin for
it is not), the registry route to a second host (the registry push timed out in the rehearsal, so images went as
`docker save` through the fallback), and a backfill backlog behind live uploads.

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

## Deploy ring

Every ship to `main` deploys by itself. The flow's `live` ring (roster, `flows/scarlet-web.toml`) runs
`scripts/deploy/ring.sh` on medaware at the shipped commit:

| Stage | What it does | When it fails |
|---|---|---|
| `deploy` | Builds the image, pushes its new layers to the droplet's registry and runs `deploy/rollout.sh`, as `bin/deploy` does | The old release keeps serving |
| `pages` | Reads the release that serves and requests `/up`, `/stats`, `/leaderboards`, `/rulesets`, `/talents`, `/map`, `/search` and the public API with a `v=<release>` parameter no cache has seen, so each answer is rendered by the new release | The ring rolls back |
| `rollback` | `rollout.sh --rollback`, only when the failed commit is the release that serves | The failure is posted with the log |

The ring uses a key of its own, `~/.config/scarlet-web/ring_ed25519` on medaware. Its line in
`~ops/.ssh/authorized_keys2` on the droplet forces `/usr/local/sbin/scarlet-deploy-entry` (`scripts/deploy/ring-entry.sh`)
and allows one port forward, to the loopback registry. The entry takes nine verbs (lock, unlock, registry, pull,
rollout, rollback, release, sums, status) and a release must be 12 hex digits. The key gets no shell, cannot write a
file, cannot install a deploy folder and is not on the database host. The ops key stays on medaviper.

What the ring cannot do, and `bin/deploy` with the ops key still does:

- **A change to a file a rollout reads on the droplet**: `deploy/rollout.sh`, `deploy/dc`, `deploy/compose.yaml`,
  `deploy/compose.redis-vpc.yaml` and `deploy/nginx/`. The ring compares those at the commit with the droplet's
  (`sums`). When they differ it prints `skip:` and deploys nothing, for that commit and every later one, until
  `bin/deploy` runs once. A change to any other file under `deploy/` (provisioning, backups, the database host, load
  tools) does not stop it, because a rollout never runs them.
- Everything else `bin/deploy` lists: the server setup, TLS, DNS, cache rules, backups, maps and the database host.

Set up or rotate the key:

```sh
ssh medaware 'mkdir -p -m 700 ~/.config/scarlet-web && ssh-keygen -q -t ed25519 -N "" -C scarlet-ring@medaware -f ~/.config/scarlet-web/ring_ed25519 && cat ~/.config/scarlet-web/ring_ed25519.pub' > ring.pub
bin/deploy ring-key ring.pub      # with the ops key: installs the entry and replaces authorized_keys2
```

To stop the ring: `flow freeze scarlet-web --ring live`, or empty `~ops/.ssh/authorized_keys2` on the droplet.

## Logs and shells

```sh
bin/deploy logs web            # nginx access log: visitor address, Cloudflare peer, status, time
bin/deploy logs app-blue       # php-fpm and Laravel (stderr) of one slot; `bin/deploy status` shows which serves
bin/deploy logs horizon-a     # or -b, -c: `bin/deploy status` shows which runs
bin/deploy ssh                 # a shell on the droplet; deploy/dc is docker compose for the stack
bin/deploy ssh 'cd /srv/scarlet-web && deploy/dc exec horizon-a php artisan horizon:status'
```

The Laravel log is also in the `logs` volume (`storage/logs/laravel.log` in the app containers). Docker keeps three
10 MB files per container. Every Laravel log record is one line (`App\Support\Logging\OneLineFormatter`):
`SCARLET-LOG level=ERROR at=<time> class=<exception class> file=<path without line> msg=<message> | trace <frames> | ctx <json>`.
A 4xx `ApiException` answer is logged at `info` (`error` for a 5xx one), so it never reaches the error watch.

## Error watch

The flow engine on medaware follows the droplet's logs and starts one agent run for each new kind of error
(roster `flows/scarlet-web.toml`, watch `logs`; `flows/README.md`, "Watches"; `flows/agent.md`, `scarlet-web`).

- **Stream.** `ssh scarlet-logs@scarlet-web` with `~/.config/scarlet-web/logs_ed25519` on medaware. The user's only line in
  `/home/scarlet-logs/.ssh/authorized_keys` (root-owned) is `restrict,command="sudo -n /usr/local/sbin/scarlet-log-stream"`
  with the key in `deploy/log-stream.pub`. `/etc/sudoers.d/scarlet-logs` lets that user run that one path as root, and
  `/etc/ssh/sshd_config.d/20-scarlet-logs.conf` adds the user to `AllowUsers`. The key has no shell, no port forward and
  no pty, and the script (`deploy/log-stream.sh`) takes no argument.
- **Output.** First `SCARLET-RELEASE <release>`, repeated when `release.env` changes. Then the `SCARLET-LOG` lines of
  the `logs` volume's `laravel.log`, `SCARLET-5XX <status> <method> <route>` for each nginx access line that answered 5xx,
  and the other output of the app slots, Horizon, the scheduler and nginx's error log. `SCARLET-HEARTBEAT` every 30
  seconds.
- **Install.** `bin/deploy server` runs `deploy/install-log-user.sh` (from `server-setup.sh`), so a rebuilt droplet gets
  the user back with the same key. Rotate the key: make a new one on medaware, replace `deploy/log-stream.pub`, ship, run
  `bin/deploy server`.
- **Check.** `ssh -i ~/.config/scarlet-web/logs_ed25519 scarlet-logs@scarlet-web` from medaware prints the release line and
  follows the logs; `ssh ... id` prints the stream, not a user id.

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
reaches it over the nyc3 VPC (`DB_HOST` is the droplet's private address, `DB_SSLMODE=verify-ca`). Redis stays on the app
droplet. `deploy/compose.db.yaml` is the stack, `deploy/db-host.sh` runs on the database droplet and
`deploy/db-cutover.sh` on the app droplet; `bin/deploy` calls both through `deploy/db-move.sh`.

### Install the database host

```sh
node deploy/provision.mjs db   # volume, firewall scarlet-web-db, droplet; signs the new node in the tailnet
bin/deploy r2                  # R2 credentials on the app droplet and the database droplet
bin/deploy db-server           # folders, TLS key pair, pg_hba.conf, pgbackrest.conf, age and rclone, systemd units
```

`provision.mjs` tags the droplet `scarlet-db` (the tag the firewall targets) and the volume `scarlet-web`, formats and
mounts the volume in cloud-init, and runs `tailscale lock sign` for the new node, because the machine that provisions is
a trusted signing node. `node deploy/provision.mjs retag` moves an older install to the tags above. `etc/pgbackrest.conf`
is mode 600 and owned by uid 1000, the postgres user inside the container, which has to read it; the host's `r2.env`
is root-only because only root reads it, and `ops` has sudo either way. `bin/deploy db-server` makes the pgBackRest passphrase in
`E:\Dev\secrets\scarlet-web\pgbackrest-passphrase` when it is missing. **Copy that file to a second place you control**:
without it the WAL archive and the backups under `pgbackrest/` in the bucket cannot be read. It is also in
`/srv/scarlet-db/etc/pgbackrest.conf` on the database droplet (mode 600). The data folder is bound from
`/mnt/scarlet-db-data/pgdata` and never created by Docker, and `deploy/dc-db` refuses to start the container when the
volume is not mounted, so a missing volume cannot turn into a database on the root disk.

### Move the database

```sh
bin/deploy db-move
```

It holds the deploy lock for the whole move (the deploy lock also covers `rollback`, `db-server`, `db upgrade` and the
`db-move` subcommands) and runs these steps, which `deploy/db-move.sh` documents in its header. Step 4 runs detached on
the app droplet and is polled, so a dropped ssh session does not stop it; if it ends in an unexpected state the command
reads both Postgres servers and the slots, prints which one writes and the command that finishes or undoes the move, and
keeps the lock when it cannot read one side:

1. The app droplet lets the database droplet copy it: the role `replicator`, one `pg_hba.conf` line for the compose
   network, the slot `scarlet_db_move`, and a relay container (nginx `stream`) on its Tailscale address, port 15432.
   The live firewall is not touched, and the old Postgres container is not recreated.
2. The database droplet copies it with `pg_basebackup` and follows it as a standby.
3. `.env` gets `DB_HOST` and `DB_SSLMODE=require`; the idle slot starts with it and passes its health check (reads
   work on the standby); Horizon and the scheduler stop (queued jobs wait in Redis).
4. The old Postgres goes read-only (`ALTER SYSTEM`, not a database setting, so nothing read-only replicates), writers
   every transaction that began before the setting reached new sessions is given 3 seconds and then ended, the
   standby replays to the last WAL position and is promoted,
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
- The production move on 2026-11-22 (01:00 UTC), with the loop running against https://scarletweb.io: 0 failed reads in
  6,340; 5 failed writes of 634 in a 4.2 second window; the cutover reported the old Postgres read-only for 11.3 seconds.
  A normal deploy by another lead ran against the new database afterwards.
- The app's connections reach Postgres from the app droplet's VPC address with TLS on; Postgres listens on the VPC
  address and loopback only.

### Managed database

If self-hosting stops being worth it, Tiger Cloud (Timescale's managed service) runs the same extensions from about $30
to $36 a month at this size. Migrations would have to run without superuser rights first.

## Redis

Two Redis instances run on the app droplet, so a full cache cannot stop the queue. With a second app host
([Hosts and roles](#hosts-and-roles)) both also listen on the droplet's VPC address (6379 and 6380) behind the firewall
`scarlet-web-redis`; with one app host they listen on the compose network only.

| Instance | Holds | Memory cap and policy |
| --- | --- | --- |
| `redis` | The queues, Horizon's records (failed jobs, metrics, supervisors), job and scheduler locks, and the `durable` cache store: the pending rollup range, the replay check of upload signatures and the re-decode cursor (`QueueOutdatedSessions`). Connection `default`, database 0, key prefix `scarlet_` | 256 MB, `noeviction`. A full instance refuses writes and says so, it never drops a job |
| `redis-cache` | The cache and the sessions of visitors. Connection `cache` (database 1) and connection `session` (database 2), the same host. Settings `REDIS_CACHE_HOST`, `REDIS_CACHE_DB`, `REDIS_SESSION_DB`; compose sets the host for every app container, and without it both fall back to `REDIS_HOST`, which is how a development checkout runs on one instance | 192 MB, `allkeys-lru`. A full instance drops the least recently used keys: an evicted cache entry is built again, an evicted session signs one visitor out |

Both save a snapshot after 60 seconds with one change and keep it in their own volume (`redisdata`, `rediscachedata`),
so the 04:30 reboot does not sign anyone out. The caps add up to 448 MB on a droplet with 4 GB, 1.3 GB in use before the
change. `INFO memory`, `INFO keyspace` and `CONFIG GET maxmemory*` show an instance:

```sh
bin/deploy ssh 'cd /srv/scarlet-web && deploy/dc exec redis-cache redis-cli info keyspace'
```

A cache entry that must not be evicted uses `Cache::store('durable')`. A lock never lands on `redis-cache`: every store
sets `lock_connection` to `default`. Rate-limit counters are cache entries, so under memory pressure a counter can reset
early. The upload manifest list (`WantedParts`) is a cache entry too: an install that lost it sends its manifest again.

### Parts that were never decoded

`ingest:sweep` runs every 5 minutes (`routes/console.php`). It finds parts stored more than 5 minutes ago, still without
`decoded_at`, and no older than 72 hours, and queues `IngestSession` for their sessions, 500 at most and the oldest
first. A job that is still queued is skipped by its identity (`ShouldBeUniqueUntilProcessing`, 15 minutes), so a slow
queue is not queued twice, and a job lost together with its lock is found within 15 minutes of the loss at the latest.
The query reads the partial index `parts_undecoded_created_at_index`, which holds only the parts that wait. A decode
marks the parts it read as decoded, also when the session holds no rows to store (the empty case), and a part stored
while a decode ran stays marked as waiting. Settings are in `config/scarlet.php` (`ingest.sweep_*`). Parts older than 72
hours are left to `php artisan ingest:redecode`. `horizon:snapshot` runs every 5 minutes, which Horizon's metrics pages
need.

### What is proven

- On a development checkout with a second Redis (`allkeys-lru`): a session of three parts whose job and queue were
  deleted was decoded by the scheduled sweep, and sessions and cache keys appeared only in the second instance.
- On production, after the deploy of 2026-11-22: `CONFIG GET maxmemory*` gave 256 MB `noeviction` on `redis` and 192 MB
  `allkeys-lru` on `redis-cache`. A request to `/login` put one session in `redis-cache` database 2, and `redis`
  database 0 held only Horizon, queue and lock keys. A real session with its three parts set back to undecoded was
  decoded again by the 01:55 sweep with identical rows (`played_ms` 23000, 7 lines, same checksum of the session row).
  Nobody flushed the queue's Redis there, since that deletes Horizon's failed jobs and the schedule locks.
- A job lost together with its identity lock waits for the lock (15 minutes) before the next sweep queues it.

### Change the caps

Edit `--maxmemory` in `deploy/compose.yaml` and run `bin/deploy`; `up -d --wait` recreates the instance, which loads its
snapshot. After the first deploy with two instances the old cache keys are still in database 1 of `redis` and the old
sessions in its database 0 until they expire (two hours for sessions). `redis-cli -n 1 flushdb` on `redis` clears the
cache keys; never flush database 0 there, it holds the queue.

## Storage policies

`php artisan scarlet:storage-policies` makes the hypertables follow the `storage` section of `config/scarlet.php`.
`deploy/rollout.sh` runs it after the migrations of every deploy. A second run changes nothing, and a changed setting
changes only what differs. It prints one line per change.

| Setting (environment) | Default | Effect |
| --- | --- | --- |
| `chunk_dense` (`SCARLET_CHUNK_DENSE`) | 1 day | Chunk interval of `path_points` and `creature_sightings`. Applies to chunks created after the change |
| `chunk_sparse` (`SCARLET_CHUNK_SPARSE`) | 7 days | Chunk interval of every other hypertable, `character_levels` included |
| `compress_after` (`SCARLET_COMPRESS_AFTER`) | 7 days | Compression policy of the 18 fact hypertables. `character_levels` is never compressed: ingest deletes and rebuilds all of a character's rows each time |
| `segmentby`, `orderby` | `character_id`, `session_start, at` | Compression settings. A character's pages read by character, and a re-decode deletes by character and session, which the batch metadata of `session_start` answers without decompressing other sessions |
| `refresh_start_offset` (`SCARLET_REFRESH_START_OFFSET`) | 3 days | How far back the 15-minute refresh policy of each of the 28 aggregates reaches. At least 2 days (two buckets) and shorter than `compress_after`, so the policy never reads a compressed chunk. The command refuses anything else |
| `refresh_slice_days` (`SCARLET_REFRESH_SLICE_DAYS`) | 7 | `RefreshAggregates` refreshes a range in slices of this many days |
| `retention` (`SCARLET_STORAGE_RETENTION`) | unset | Refused when set |

Retention is a one-way door. Dropping chunks of a fact table loses rows that only decoding every session again brings
back, and an aggregate whose policy still read back to the start would then delete its own history. The command
sets no retention policy and removes none; it stops, before changing anything, when `storage.retention` is set or a
`policy_retention` job exists on any table.

What changed in the code around it:

- A refresh policy reaches 3 days back. Older ranges are refreshed by the writer that changed them: every writer
  of fact rows already calls `RefreshRollups` or `RefreshAggregates` over its own range (decode, group and character
  removal, `maps:place-points`, `rulesets:merge`, `levels:rebuild`). A policy that no longer reaches back is why they
  must; a writer added later has to do the same.
- `RefreshAggregates` cuts a range to the days that have chunks and refreshes it in slices, so `rulesets:merge`'s
  range from 1970 to 2100 costs the days that hold rows. A refresh still recomputes only what the invalidation log
  marks, and reads the compressed chunks of those days.
- TimescaleDB stops a transaction that updates more than 100,000 rows of compressed chunks. `rulesets:merge` and
  `maps:place-points` lift that limit for their own transactions (`DecompressionLimit`). A re-decode deletes by
  character and session and inserts new rows; both work on a compressed chunk, which becomes partly compressed and
  is compressed again by the next run of its compression policy.

Check what is applied:

```sh
bin/deploy ssh 'cd /srv/scarlet-web && deploy/dc exec -T app-blue php artisan scarlet:storage-policies'   # the slot `status` names
psql -c "select job_id, hypertable_name, config from timescaledb_information.jobs where proc_name in ('policy_compression', 'policy_refresh_continuous_aggregate') order by 1"
psql -c "select * from hypertable_compression_stats('path_points')"
```

Sizes, ratios and the re-decode check are in [MEASUREMENTS.md](MEASUREMENTS.md#storage-policies).

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
| `/api/v1/public/*` | `App\Http\Middleware\PublicApi`: `public, max-age=300, s-maxage=300`, 30 for the answers that name characters (`private` with an `Authorization` header) | cached 5 minutes, or 30 seconds |
| The public pages (`routes/pages.php`, `/`, `/demo`) | `App\Http\Middleware\EdgeCacheable`: no session, no cookie, `s-maxage=60` | cached 1 minute |

`node deploy/cloudflare.mjs cache-rules` writes the two Cache Rules (Free plan: `http.cookie` and header names work in
them) and `status` lists them. Their edge TTL is `bypass_by_default`: the origin's `Cache-Control` decides, so an
answer with no `public` marker, an error or a `Set-Cookie` is not stored. A character who switches their page to private
stays visible in the edge for up to a minute, and in the API for up to a minute (30 seconds in the origin's answer
cache, 30 at the edge).

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

## Load environment

`bin/load up` builds `scarlet-load-app`, `scarlet-load-db` (with its own volume) and `scarlet-load-gen` at production sizes
from this checkout, with the rate limits lifted and its own R2 prefixes, and `bin/load down` removes them, their volume,
and firewalls (about $0.13 an hour while up). It logs each droplet out of Tailscale before deleting it, but the CLI cannot
remove the node, so delete `scarlet-load-*` nodes in the Tailscale admin console. Run it only for a test. Every command pins
`DEPLOY_HOST` and `DEPLOY_DB_HOST` to `scarlet-load-*`, so none of them reaches production. `tools/loadgen` is the traffic
source; what it measured and how to rerun it is in `docs/MEASUREMENTS.md`, "Load tests".

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
