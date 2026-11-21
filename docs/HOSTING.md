# Hosting

scarletweb.io runs on one DigitalOcean droplet behind Cloudflare. The code that builds, ships and runs it is in
`bin/deploy`, `deploy/` and `docker/prod.Dockerfile`; this file says what it does and how to operate it.

## Layout

| Part | What |
| --- | --- |
| Droplet | `scarlet-web`, region nyc3, `s-2vcpu-4gb` (2 vCPU, 4 GB RAM, 80 GB disk), Ubuntu 24.04, 2 GB swap, DigitalOcean backups and monitoring on |
| Cloudflare | Zone `scarletweb.io` on the Free plan. Proxied `A` and `AAAA` records for the apex and `www`. SSL mode Full (strict), Authenticated Origin Pulls on, Always Use HTTPS on, minimum TLS 1.2, HSTS off |
| Containers | `web` (nginx), `app-blue` or `app-green` (php-fpm, one serves), `horizon`, `schedule`, `postgres` (TimescaleDB 2.32 with the Toolkit, PostgreSQL 18), `redis`. `deploy/compose.yaml` |
| Access | Tailscale only. `ssh ops@scarlet-web` with the server's own key. The DigitalOcean web console is the fallback |
| Files on the droplet | `/srv/scarlet-web`: `.env` (mode 600), `tls/`, `maps/`, `backups/`, `deploy/`; `/etc/scarlet-web/r2.env` (R2 credentials, root only) |

Request path: browser, Cloudflare, nginx on the droplet (port 443, checked against Cloudflare's client certificate),
php-fpm. nginx runs on the host network so it sees the real peer address. It trusts `CF-Connecting-IP` only from
Cloudflare's published ranges (`deploy/nginx/cloudflare-ips.conf`), so Laravel's `$request->ip()` and the rate limits
see the visitor, not Cloudflare. `www` redirects to the apex in nginx.

### Access and exposure

- The DigitalOcean cloud firewall `scarlet-web` (tag `scarlet-web`) lets in tcp 80 and 443 from Cloudflare's IPv4 and
  IPv6 ranges and nothing else. There is no inbound 22. Outbound is open. Docker publishes ports around `ufw`, so
  the cloud firewall is the guard, and nothing else is published: php-fpm listens on `127.0.0.1:9000` and PostgreSQL
  and Redis are on the compose network only.
- To open ssh for a while, add a tcp 22 rule for one address to the `scarlet-web` firewall and remove it after.
- sshd allows the user `ops` with a key only; root login and passwords are off. `unattended-upgrades` is on and may
  reboot at 04:30 UTC; every container restarts by itself.
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
`app:release-notes:backfill` with the git history mounted for that one step so no `.git` is in the image, then pipes
`docker save | zstd | ssh | docker load` to the droplet. On the droplet `deploy/rollout.sh`:

1. runs `migrate --force`, `maps:load-rects`, `app:release-notes:publish` and `cache:clear` with the new image while
   the old release still serves;
2. starts the new release as `app-blue` or `app-green`, whichever is idle, and waits for three answers in a row from
   `/up` and `/release-notes` through nginx's own configuration (loopback ports 8091 and 8092);
3. copies the release's built assets into `/srv/scarlet-web/public`, where every release's assets stay, so a page
   from the old release still finds its files;
4. rewrites nginx's upstream (`/srv/scarlet-web/state/upstream.conf`) and runs `nginx -s reload`, not a restart: nginx
   finishes the requests it has;
5. waits 5 seconds, then sends the old php-fpm SIGQUIT, which finishes its requests, and removes it;
6. stops Horizon and the scheduler gracefully (running jobs finish; SIGTERM is Horizon's own terminate) and starts them
   on the new release. Queued jobs wait in Redis meanwhile.

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
bin/deploy logs horizon
bin/deploy ssh                 # a shell on the droplet; deploy/dc is docker compose for the stack
bin/deploy ssh 'cd /srv/scarlet-web && deploy/dc exec horizon php artisan horizon:status'
```

The Laravel log is also in the `logs` volume (`storage/logs/laravel.log` in the app containers). Docker keeps three
10 MB files per container.

## Backups

- DigitalOcean backs up the whole droplet daily and keeps 7 days (the droplet's Backups tab restores or rebuilds
  from one). It is the same DigitalOcean account as the droplet.
- `scarlet-backup.timer` runs `deploy/backup.sh` at 03:15 UTC. It dumps the `scarlet` database (`pg_dump -Fc`) into
  `/srv/scarlet-web/backups` (local copies kept 7 days), encrypts the dump with age, copies it to Cloudflare R2 and
  prunes R2. `bin/deploy backup` starts the same unit now and exits 1 when it fails.
- `bin/deploy backups` (also the last lines of `bin/deploy status`) prints the unit's last result and the newest local
  and R2 copies with size and age. It exits 1 when the last run failed, R2 cannot be read, or the newest `daily/`
  copy is older than 26 hours.

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
deploy/dc stop app-blue app-green horizon schedule web
deploy/dc exec -T postgres psql -U postgres -d postgres -c 'drop database scarlet' -c 'create database scarlet'
deploy/dc exec -T postgres psql -U postgres -d scarlet -c 'create extension if not exists timescaledb' \
  -c 'create extension if not exists timescaledb_toolkit' -c 'select timescaledb_pre_restore()'
deploy/dc exec -T postgres pg_restore -U postgres -d scarlet --no-owner < backups/scarlet-<stamp>.dump
# from R2: bin/deploy ssh 'cd /srv/scarlet-web && deploy/dc exec -T postgres pg_restore -U postgres -d scarlet --no-owner' < scarlet.dump
deploy/dc exec -T postgres psql -U postgres -d scarlet -c 'select timescaledb_post_restore()'
deploy/dc up -d web horizon schedule
cat state/active   # then: deploy/dc up -d app-blue (or app-green), the slot named there
```

`pg_dump` prints a warning about circular foreign keys on `continuous_agg`; the restore above ran clean from such a dump
into a scratch database (61 tables, 26 continuous aggregates, 15 hypertables).

## Map art

The map tiles are not in git or in the image. They live on the droplet at `/srv/scarlet-web/maps` (mounted read-only
at `/maps`, `MAPS_ROOT=/maps`), so a deploy never touches them. Build them as DEV.md says (`tools/map-tiles`), then:

```sh
bin/deploy maps <folder with manifest.json>     # normally storage/app/maps of a dev checkout
```

The site reads `manifest.json` for the current version, so a new build is live once it is copied. Never commit the
tiles.

## Setting up again from nothing

Needs the secrets folder (`SCARLET_SECRETS`, default `E:/Dev/secrets/scarlet-web` on Windows): `do-token`,
`cloudflare-token`, `tailscale-authkey`, `ops_ed25519` and `ops_ed25519.pub`.

1. `node deploy/provision.mjs` uploads the ops key, creates the tag and the firewall, then the droplet from
   `deploy/cloud-init.yaml` (ops user, key-only ssh, unattended upgrades, Docker, Tailscale, swap). It touches only
   resources named `scarlet-web`.
2. Sign the new node in the tailnet if Tailnet Lock is on.
3. `bin/deploy bootstrap` makes the server folder and `.env` (its secrets are generated on the droplet), points the
   DNS records at it, gets an Origin CA certificate for the apex and `*.scarletweb.io` (the key and CSR are made on
   the droplet; only the CSR leaves it), deploys main, checks the certificate serves, switches the zone to Full
   (strict), then turns on Authenticated Origin Pulls. Turning origin pulls on first, or strict first, makes every
   request fail, which is why the order is fixed.

## Cost

Droplet `s-2vcpu-4gb`: $24 a month, plus the backups (a share of the droplet price) and nothing for Cloudflare's Free
plan. Check DigitalOcean's pricing page; prices here were read in November 2026.

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
