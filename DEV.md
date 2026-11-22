# Local development

Everything runs in wslc containers: the app, Horizon, the scheduler, PostgreSQL 18 with TimescaleDB, and Redis.
Windows has no PHP, no Composer and no Node for this repo, and there is no Herd or Sail. Commands go through the
`bin/` wrappers, which run them in the checkout's tools container. Git Bash runs them as they are. PowerShell and cmd
run the `.cmd` beside each one, which starts the same script in Git for Windows' bash, so `bin\psql` works there too.

## Needs

- wslc: `wslc info` works (`wsl --update` in an elevated PowerShell installs it).
- PowerShell 7 (`pwsh`) for `bin/dev`.
- Free ports: 5440 (PostgreSQL), 6390 (Redis) and one web port per worktree from 8470.

## First time

```sh
bin/dev image        # builds scarlet-web-dev:latest, about 2 minutes
bin/dev bootstrap    # starts PostgreSQL and Redis, makes .env, installs dependencies,
                     # creates this worktree's database, builds the assets, runs the migrations
bin/dev start        # web, Horizon and the scheduler, each in its own container
bin/dev status       # containers, the web port and the database name
```

Then open `http://127.0.0.1:<port>` (the port `bin/dev start` prints; the first worktree usually gets 8470 to 8569
by a hash of its folder name; `bin/dev start -Port 8480` picks one). `/up` is the health check.

## Everyday commands

| Command | What it does |
| --- | --- |
| `bin/composer check` | `lint:check`, `analyse` and `deps:age`; must pass before every ship |
| `bin/composer lint` | formats with Pint |
| `bin/artisan migrate` | runs the migrations against this worktree's database |
| `bin/pnpm run build` | builds the assets into `public/build`; rebuild after changing CSS or JS |
| `bin/php scripts/dev/worktree-db.php status` | the databases of all worktrees; also `provision`, `reset`, `drop` |
| `bin/psql -d scarlet_web_wt_<folder>` | psql in this worktree's database |
| `bin/dev start`, `stop`, `down`, `remove` | start or stop `web`, `horizon`, `schedule`; `down` stops them and the tools container; `remove` deletes the containers (volumes stay) |
| `bin/dev infra up`, `infra down` | the shared PostgreSQL and Redis; `down` keeps their data |
| `bin/run <command>` | any other command in the tools container |

## What runs where

| Container | Image | Role |
| --- | --- | --- |
| `scarlet-web-pg` | `timescale/timescaledb-ha`, PostgreSQL 18.6, TimescaleDB 2.32.0 (community licence), toolkit 1.26.0 | the one database server; published on `127.0.0.1:5440`, user `postgres`, no password, loopback only |
| `scarlet-web-redis` | `redis` 8.10.3 | queues, cache, sessions; published on `127.0.0.1:6390` |
| `scarlet-web-wt-<folder>` | `scarlet-web-dev:latest` | tools: composer, artisan, pnpm, Pint, PHPStan |
| `scarlet-web-wt-<folder>-web` | same | `php artisan serve` on the web port |
| `scarlet-web-wt-<folder>-horizon` | same | `php artisan horizon` |
| `scarlet-web-wt-<folder>-schedule` | same | `php artisan schedule:work` |

`<folder>` is the checkout folder without a leading `scarlet-api-` or `scarlet-web-`: the checkout `scarlet-api-app`
is `app`, and the main checkout `scarlet-web` is `main`. A linked worktree whose own folder is `scarlet-web` (a
worktree of several repos keeps each repo under its name) takes the folder above it instead, so it never shares
`main`'s containers. Each worktree has its own containers, its own `vendor/` and
`node_modules/` volumes (`scarlet-web-wt-<folder>-vendor`, `-node_modules`), its own Redis, cache, Horizon and
session prefixes (`scarlet_web_wt_<folder>_...`) and its own database `scarlet_web_wt_<folder>`, a copy of
`template1` that already has TimescaleDB. The containers share the network `scarlet-web`, so the app reaches the
servers as `scarlet-web-pg:5432` and `scarlet-web-redis:6379`; `.env.example` has those names.

The image has PHP 8.5 with pdo_pgsql, redis, pcntl, posix, sodium, intl and zip, Composer, Node and pnpm. It runs
Horizon because the Linux build has pcntl and posix. The checkout is bind-mounted at `/app`; `vendor/` and
`node_modules/` live on volumes because the bind mount is slow for thousands of small files.

## Where the state lives

All of it is on E:. wslc keeps images, volumes and container layers in one disk image, `storage.vhdx`, under
`%LOCALAPPDATA%\wslc`, which is a junction to `E:\Dev\from-c\Users\<you>\AppData\Local\wslc`. Check that after a
fresh Windows install with `E:\Dev\tools\evict-c\evict-c.ps1` (report mode). The named volumes are
`scarlet-web-pg-data` (the databases), `scarlet-web-redis-data`, `scarlet-web-cache` (Composer's cache, shared) and
the two per worktree. `.env`, `storage/` and `public/build` are in the checkout. `bin/dev infra down` keeps all of
it; `wslc volume remove <name>` deletes a volume for good.

## Version numbers

`VERSION` holds the version; the build number counts commits since it last changed. To cut a version, change `VERSION`
in a ship of its own. Do not run `app:version:set` (it tags) or `app:version:install-hooks`.

`storage/app/version.json` and `storage/app/release-notes.json` are generated, ignored and read by the footer and
`/release-notes`. To fill them locally, from the checkout the containers can read the `.git` of:

```sh
bin/artisan app:version --strict
bin/artisan app:release-notes:backfill --all --output=storage/app/release-notes.json
bin/artisan app:release-notes:publish --from-file=storage/app/release-notes.json
```

In a `ws` worktree the `.git` file names a Windows path the container cannot read, so `--strict` fails (without it the
numbers are zero and the commit is `unknown`). Point git at a bare copy of the history instead: `git init --bare
<folder>`, `git fetch <worktree> +HEAD:refs/heads/main` into it, and run the commands with `GIT_DIR` set to that folder
as seen from the container. Commit first: the snapshot is the committed history, and the import refuses a snapshot
from another build.

## Dependencies

The rule: every package and image is the newest release that has been public for at least 7 days.

- pnpm enforces it (`minimumReleaseAge: 10080` in `pnpm-workspace.yaml`).
- Composer has no such setting. `composer.json` pins every direct dependency to an exact version, and its `conflict`
  list holds `>=x.y.z` for each transitive package whose newest release was younger than 7 days when it was added.
  When `scripts/deps-age` reports a package, add a `conflict` line for the reported version, run
  `bin/composer update`, and remove the line once that version is old enough.
- A package from a `vcs` repository in `composer.json` is not on Packagist. `scripts/deps-age` reads its release time
  from the `time` the lock file records for the tag, so the 7-day rule covers it too.
- Images: `docker/pins.tsv` and the `FROM` lines of `docker/dev.Dockerfile` name each image as `name:tag@sha256:digest`
  with the date it was pushed. Compare tags with Docker Hub's `tag_last_pushed`.
- `bin/composer deps:age` (or `bin/php scripts/deps-age`) checks `composer.lock`, `pnpm-lock.yaml`,
  `tools/map-tiles/pnpm-lock.yaml`, `part/Cargo.lock` when it exists and the images, asks Packagist, npm, crates.io and Docker Hub, and caches the answers under
  `storage/deps-age/`. It fails on a package younger than 7 days, and on a registry that does not answer.

Two things the Dockerfile installs without a lockfile: pnpm 12.21.0 (2026-11-13) and phpredis 6.3.0 (2025-11-06).

## Database extensions

The migration `2026_11_20_000000_enable_database_extensions` enables `timescaledb`, `timescaledb_toolkit` and
`pg_trgm`. The image creates the first two in `template1`, so every new database already has them and the migration
only records the fact. A continuous aggregate cannot be created inside a transaction: its migration sets
`public $withinTransaction = false`.

## Map tiles

The world map's art is not in git. `tools/map-tiles` reads an installed copy of the OpenMap addon and writes the
tile pyramid of docs/DESIGN.md, Maps. It runs on the dev PC with Node 24 and pnpm, not in the containers, and
needs about 2 GB of free memory.

```sh
cd tools/map-tiles
pnpm install --frozen-lockfile
node build.mjs "<WoW folder>/_forever_/Interface/AddOns/OpenMap"     # about 20 seconds
```

It writes `storage/app/maps/<version>/{game,survey}/<z>/<x>/<y>.webp` and `manifest.json` (also copied to
`storage/app/maps/manifest.json`, the file the site reads to find the current version). `<version>` is a hash of the
art and the tool's settings, so a URL never changes its bytes. `--out <folder>` writes elsewhere; `--force` builds a
version that is already there. For OpenMap v0.8.11 that is 1,106 tiles per look in zoom 0 to 6 and about 13 MiB.

The `maps` disk is `storage/app/maps` by default. The containers bind-mount the checkout at `/app`, so the folder is
already there and nothing is mounted or copied. `MAPS_ROOT` points the disk at another folder inside the container.
The site serves `/map-tiles/manifest.json` (cached 5 minutes) and `/map-tiles/{version}/{look}/{z}/{x}/{y}.webp`
(cached a year, `immutable`); `/design/map-viewer` shows the viewer over invented data in the local environment.
In production the disk is object storage: `MAPS_DISK_DRIVER=s3` with `MAPS_ACCESS_KEY_ID`, `MAPS_SECRET_ACCESS_KEY`,
`MAPS_REGION`, `MAPS_BUCKET`, `MAPS_ENDPOINT` and `MAPS_USE_PATH_STYLE_ENDPOINT`, and `league/flysystem-aws-s3-v3`
in composer, which is not installed yet. Upload the folder that `node build.mjs` wrote.

The zone rectangles are `docs/map-rects.tsv`, derived from the game's map tables with the same arithmetic OpenMap
uses (`node rects.mjs <folder of the UiMap and UiMapAssignment tables as CSV>`). `bin/artisan maps:load-rects` loads
the file into `map_rects`; it can run again at any time. `node check-rects.mjs <OpenMap folder> <tables folder>
<events.csv>...` compares the table with recorded flight points and border crossings.

## Troubleshooting

- `The image scarlet-web-dev:latest does not exist`: run `bin/dev image`.
- A container does not resolve `scarlet-web-pg`: `bin/dev infra up` creates the network and the servers.
- PostgreSQL does not answer on `127.0.0.1:5440`: `wslc logs scarlet-web-pg`.
- Start over for this worktree: `bin/dev remove`, `wslc volume remove scarlet-web-wt-<folder>-vendor
  scarlet-web-wt-<folder>-node_modules`, `bin/php scripts/dev/worktree-db.php drop`, then `bin/dev bootstrap`.
