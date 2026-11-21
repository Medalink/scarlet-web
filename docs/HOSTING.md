# Hosting options

Nothing is deployed. This file lists what a public deploy needs and what each option costs a month, so the owner can
pick one. Prices are list prices read in November 2026 and are to be checked on the provider's page before buying.

## What runs

- The Laravel app: web requests, and a Horizon worker for decoding and rollup refreshes.
- `scarlet-part`, the decoder binary, next to the worker.
- PostgreSQL 18 with TimescaleDB (community edition: continuous aggregates and columnstore) and the TimescaleDB
  Toolkit (percentile and distinct-count sketches).
- Redis for queues, cache and rate limits.

Expected size at the start is small: one character's whole history is a few megabytes of parts.

## Options

| Option | What | About a month |
| --- | --- | --- |
| A. Laravel Cloud + managed TimescaleDB | App and worker on Laravel Cloud (Starter plan, one small web instance, one small worker, its Redis). Database on Tiger Cloud, the smallest paid service | $5 plan + about $12 to $15 compute + about $30 database: **about $50** |
| B. Laravel Cloud only | As A, with Laravel Cloud's own serverless Postgres | **about $20 to $25**, only if check 1 below passes |
| C. One server | A 4 vCPU, 8 GB server running the app, TimescaleDB and Redis in containers, backups to object storage | **about $10 to $25** |

## Checks before choosing

1. Laravel Cloud's serverless Postgres must offer TimescaleDB with continuous aggregates and the Toolkit. Its docs name
   pgvector and "a wide range of extensions" but no list. On a Laravel Cloud database run
   `select name, default_version from pg_available_extensions where name like 'timescaledb%';` and
   `create materialized view t with (timescaledb.continuous) as ...` on a hypertable. If either fails, option B is out.
2. Laravel Cloud must offer PHP 8.5 as a runtime. Not confirmed from its docs.
3. The worker must be able to run a bundled Linux binary (`scarlet-part`, static, a few MB).

## Recommendation

Option A. The owner already runs Laravel apps on Laravel Cloud, and a managed TimescaleDB means backups, upgrades and
the extensions are someone else's work. If check 1 passes, move to B and save the $30. Option C is the cheapest and
is fully under the owner's control, but the owner then runs the database.

## What the owner registers

Sign-in needs two OAuth clients, one per provider. Their ids and secrets go in the server's environment, never in the
repo. `<domain>` is the public host name, `APP_URL` is `https://<domain>`, and `SESSION_SECURE_COOKIE` defaults to on
when `APP_URL` starts with `https://`. Scarlet asks each provider for the `openid` scope only and keeps the provider
name and the provider's subject id.

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
Action refuses to run there. A provider with no client id shows its button disabled, and `/auth/<provider>/redirect`
answers `503` with "Sign in with <provider> is not set up on this server."

### What is proven

Proven without client ids, in a running app: the redirect to each provider's authorization URL (`scope=openid`, a
`state`, Google's PKCE challenge, the redirect URI), the disabled buttons, and everything after the provider answers
(account and identity rows, linking, unlinking, the account page) through the development sign-in. Not proven: a real
round trip with Google or Battle.net (the code exchange and the subject id each returns), that either provider accepts
an `http://127.0.0.1:<port>` redirect URL for a local client, and the Battle.net region hosts. Check these with the
first real client ids before announcing sign-in.

## The static website

The landing page and the demo character page move into the app unchanged. Until the app has a public home, the
static pages keep serving from GitHub Pages on the `gh-pages` branch of this repo (a copy is in `static-site/`).
Retiring them is part of the same decision: once the app is live, replace the `gh-pages` pages with a redirect to
the new address, then delete `static-site/`.
