# Scarlet web app and API

The API the Scarlet app uploads to, the database that rolls uploads up, and the web app that shows them. Design:
[docs/DESIGN.md](docs/DESIGN.md). Read it before changing anything; change it first when the design changes.
Local setup: [DEV.md](DEV.md).

## Stack

PHP `^8.5`, Laravel `^13`, pnpm + Vite + Tailwind 4, Livewire 4 + Blade + Alpine, PostgreSQL 18 + TimescaleDB +
Redis, Horizon on Redis, Sanctum, Socialite. `part/` is the Rust crate `scarlet-part`: the part format, the sharing
groups and the decoder. `static-site/` is the current static website; leave it alone until a page is carried into
the app. Pint, PHPStan and Vite skip it. Never push to `gh-pages`.

## Rules

- Code rules are the owner's IRIS house rules: controllers authorize, validate with a Form Request, call one Action
  and respond. Actions live under `app/Actions`, one public `handle()`, domain inputs and results, the transaction
  inside. Eloquent first. Jobs are bounded and safe to run twice. Transactions are short and database-only. UTC.
  No `else`. Domain identity strings are constants or enums on their owner.
- No unit or feature tests: no PHPUnit, no Pest, no `tests/` folder. The only tests are the end-to-end checks in
  `e2e/`, which drive the real app over HTTP. Verify with `composer lint:check`, `composer analyse` and those.
- The look is the Scarlet site's. Pages are built from the comps in `docs/comps/` and reviewed against them.
- Dependencies: the newest release that has been public for at least 7 days, never a younger one. pnpm enforces it
  (`minimumReleaseAge` in `pnpm-workspace.yaml`); for composer, cargo and Docker images check the release date and
  pin. `scripts/deps-age` lists locked packages and pinned images younger than 7 days, and `composer check` runs it.
- Never commit a key or secret. Never put a real player name in the repo, a doc, a screenshot or a commit message;
  examples use `Name-Surname`. Do not name other websites or data services, except AHledger, whose auction prices
  the site imports and credits as its terms ask (owner, 2026-11-23).
- Game art, models, icons and names come from the game's own files, never from stand-in art: no drawn icons, no
  invented models, no classic-era names written from memory. Forever renames some spells and lacks others, so read the
  client's own tables. `tools/scene-art/README.md` says how the art tools read them. What is built from them stays out
  of git, as Production says of map art. This repo is public: never name the owner's local game-file tooling, its
  folders or its paths in a file, a comment or a commit message.
- Change only the surface the report or design names. A component several pages share changes for all of them: list
  its users, make the change opt-in for the page named (a prop or a data attribute), and check one other user in the
  running app before shipping.
- Never open a visible window or take focus on medaviper, where the owner plays while agents work. Browsers run
  headless; the e2e lane's `hasNoWindow` check stops a browser whose window handle is not 0.
- A backslash in a script passed inline through the Bash tool on medaviper can arrive changed: a doubled backslash
  arrives as one, so an escape meant as text is read by the script's language as a control character (Python reads
  `\0` as a NUL). On 2026-11-22 this put a NUL into `scripts/deploy/ring-entry.sh`, which shipped and made git show the
  file as binary while `sh -n` still passed. Write any script or patch that holds a backslash with the file Write tool,
  then run it. Scan an edited script for control bytes, and read a diff with `git diff --text` when git says Bin.
- The beta UI rebuild is on the branch `feat/beta-ui-parity-rebuild` and stays off main until the owner says in so many
  words to ship the rebuild (held since 2026-11-23). Push that branch only: no `ship`, no merge into main, no deploy of
  it. Approval of how one part looks is not approval to ship. The ship that lands the rebuild deletes this bullet.
- `docs/groups.tsv` is the one source for sharing groups. `scarlet-part check-groups` must pass after any change to
  it or to Scarlet's FORMAT.md.
  A new line code ships in this order: Scarlet's `docs/FORMAT.md` (Medalink/scarlet) first, then its row here at once,
  then the `scarlet-part` revision in scarlet-app. The `part-groups` gate runs on changes that touch `part/**` or
  `docs/groups.tsv`, reads FORMAT.md on Medalink/scarlet main and fails both ways: a code in no group, and a group code
  FORMAT.md lacks (the `none` row too). A row shipped first fails it, and until the row lands every ship here that
  touches `part/` or `docs/groups.tsv` fails. Production can lag main when the live ring skips a deploy, so the app
  copes with `unknown_group`.
- No CI. Checks run locally and in the owner's flow.

## Architecture

- Controllers authorize → validate (Form Request) → Action → respond. Livewire actions are HTTP requests: authorize
  and validate in the action.
- Write-path operations are single-purpose Actions under `app/Actions/`: one public `handle()`, domain inputs and
  results, the transaction boundary inside.
- Models own persistence and local domain behavior. Eloquent-first, no repository wrappers. Select needed columns,
  eager-load rendered relations, aggregate in SQL, and enforce durable invariants with unique indexes and foreign
  keys.
- Events are past-tense facts; one reaction per listener; queue slow ones; publish after commit.
- Jobs own one bounded unit and assume duplicate delivery: stable identity plus constraints, not locks alone. Pass
  IDs and scalars, never secrets or object graphs. Every job implements `App\Contracts\BoundedJob`.
- Transactions are short and database-only.
- App code reads configuration through `config()`; only `config/` calls `env()`.

## SQL and data

PostgreSQL 18 + TimescaleDB only. Prefer Eloquent; raw SQL needs a stated reason. Use bindings for values.
`upsert()` `uniqueBy` must match a real unique index. Detect constraint failures by exception class. Normalize
identity strings on write. Migrations are forward-only (no `down()`), expand safely online, and are never edited
once applied: correct the schema with a new migration. Statements that cannot run in a transaction (continuous
aggregates) set `$withinTransaction = false`.

## Review bar

One responsibility per class. KISS/YAGNI: the smallest Laravel-native design, no speculative extension points or
single-implementation base classes; interfaces only at real integration boundaries. Expected failures get explicit
outcomes; never swallow `Throwable`. Store instants in UTC, localize at display boundaries. Delete code the change
made unused.

## Verification

1. `bin/composer lint` formats; `bin/composer check` runs `lint:check`, `analyse` and `deps:age` and must pass.
2. Run the real app: `bin/dev start`, then `node e2e/run.mjs --url http://127.0.0.1:<port>`.
3. Every command runs in a wslc container through `bin/composer`, `bin/artisan`, `bin/pnpm`, `bin/php`, `bin/psql`.
   Nothing runs on Windows PHP. From PowerShell, `bin\psql` runs `bin/psql.cmd`; every script in `bin/` has one.
4. e2e on Windows against `bin/dev` can fail without a regression. After a rebase that brings migrations or rollup
   code, Horizon still runs the old code and the profession and camps checks fail: run `bin/artisan migrate --force`,
   then `bin/dev stop && bin/dev start`. A server that kept the default of 5 registrations per IP per hour
   (`config/scarlet.php`; DEV.md, Checks before a ship, says to raise `SCARLET_RATE_REGISTRATION_PER_IP`) fails every
   upload check on a second run in the hour: raise the limits as DEV.md says, or run `bin/artisan cache:clear` (dev
   only) before the run. `e2e/boot.mjs`, which gives a run a clean database, works only on Linux; `ship` runs it on
   medaware.

## Landing changes

This repo is in the owner's continuous flow. Every change lands with `ship`, run on medaware from a `ws` worktree
(`ws open scarlet-web --for <workload>`): the PHP 8.5 gates run there, and a ship from another machine stops at the
first gate. From another machine, `ship --land` hands the commits to the lander on medaware, which runs the gates there
and lands them; run it as a background task and wait for its result. Open no pull request and run no `git push` by hand.

The commit message is the ship note: a plain title that names the outcome, one to three sentences on what changed
for the user and what stays the same, then `What changes` (concrete identifiers, old to new), `Unchanged on purpose`
(what was left alone) and `Verification` (what ran, with counts, and what did not run and why).

## Production

scarletweb.io runs on one DigitalOcean droplet behind Cloudflare. A ship deploys: the flow's `live` ring rolls every
commit that lands on main and touches what the image or the rollout reads out to the droplet and checks the public
pages; a commit that touches only e2e, docs or other tooling is skipped (the paths list on the ring in roster's
`flows/scarlet-web.toml`). `flow status scarlet-web` shows the ring
`passed` or `failed` at your SHA; wait for it before you report the change live. Run `bin/deploy` only for what the ring
cannot do (server setup, the database host) or for a manual rollback; it takes the same deploy lock the ring uses.
A change to a file a rollout reads (`deploy/rollout.sh`, `deploy/dc`, the compose files, `deploy/nginx/`) ships like any
other and never stops the ring, but it can reach the droplet after its release has rolled out, so that release must
also run on the copy before it. [docs/HOSTING.md](docs/HOSTING.md) has the layout, the ring, rollback,
logs, backups and restore. Secrets live on the server and in the owner's secrets folder, never in the repo. Map art is
never committed: after you build art, run `bin/deploy maps storage/app/maps`, which adds it to the art store on
medaware and sends it to the droplet without the ops key. `ship` refuses a commit while the store lacks art the commit
draws, and every ring deploy sends the droplet what it lacks of the store (docs/HOSTING.md, Map art).

## Local development

wslc containers only; DEV.md has the steps. The app is at `http://127.0.0.1:<port>`; `bin/dev status` prints the
port. Each worktree has its own containers, `vendor/` and `node_modules/` volumes, Redis prefix and database
`scarlet_web_wt_<folder>`. Horizon runs in its own container.

<!-- house-rules:start -->
## Analyzer house rules

Generated by `composer guidance` from `#[HouseRule]` attributes. `composer analyse` fails on a stale block (`guidance.stale`).

| Identifier | Mode | Rule | Fix |
| --- | --- | --- | --- |
| `scarlet.actionHttp` | Zero | Actions accept domain inputs and return domain results. | move Request, Component and Response handling to the HTTP boundary. |
| `scarlet.actionShape` | Zero | Actions expose handle(). | move other public operations into separate Actions. |
| `scarlet.blockingInTransaction` | Zero | transactions contain database work only. | move blocking calls outside DB::transaction(). |
| `scarlet.cacheKey` | Zero | cache keys are not versioned. | remove the version segment and tolerate old payloads until TTL expiry. |
| `scarlet.cacheKeyLiteral` | Zero | cache keys have an owner. | obtain the cache key from its domain owner. |
| `scarlet.env` | Zero | app code reads configuration. | move env() to config/ and use config(). |
| `scarlet.fileLength` | Zero | files stay within 500 lines. | split the file by responsibility. |
| `scarlet.identityLiteral` | Zero | domain identities use their owner constant or enum. | replace the identity literal with its owner constant or enum case. |
| `scarlet.jobCollaborator` | Zero | queued constructors carry serializable input. | inject collaborators into handle() instead. |
| `scarlet.livewireQuery` | Zero | Livewire delegates domain reads. | move the query into a read Action. |
| `scarlet.livewireWrite` | Zero | Livewire delegates domain writes. | move the write and its invariants into an Action. |
| `scarlet.lockKey` | Zero | lock keys have an owner. | obtain the lock key from its domain owner. |
| `scarlet.migrationDown` | Zero | new migrations are forward-only. | remove down() and correct schema with a new migration. |
| `scarlet.migrationUnsafe` | Zero | migrations expand safely online. | use nullable or defaulted additions; defer contract changes and build indexes outside a transaction. |
| `scarlet.noElse` | Zero | no else or elseif. | return, throw, continue or break on the invalid case first. |
| `scarlet.queueHookSignature` | Zero | queued methods named like Laravel queue hooks keep their signature. | rename the method or match the signature. |
| `scarlet.rawSqlInterpolation` | Zero | SQL values use bindings. | replace interpolated SQL with placeholders and parameter bindings. |
| `scarlet.serviceShape` | Zero | services are integration surfaces. | one public method is an Action, move it to app/Actions. |
| `scarlet.unboundedJob` | Zero | queued jobs declare bounded execution. | implement App\Contracts\BoundedJob with public int $timeout, public int $tries and public int $memoryMb. |
| `scarlet.unlockedId` | Zero | component identifiers are locked. | add #[Locked] and authorize the identifier in each action. |

Facts the analysis knows:

- PHPStan runs Larastan at level 8 with no baseline file.
<!-- house-rules:end -->
