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

Sign-in needs two OAuth clients. Their ids and secrets go in the environment, never in the repo.

- **Google** (Google Cloud console, APIs and Services, Credentials, OAuth client ID, type "Web application").
  Authorized redirect URI: `https://<domain>/auth/google/callback`. Consent screen scopes: `openid` only.
  Environment: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`.
- **Battle.net** (Battle.net developer portal, Create Client). Redirect URL:
  `https://<domain>/auth/battlenet/callback`. Scope: `openid` only.
  Environment: `BATTLENET_CLIENT_ID`, `BATTLENET_CLIENT_SECRET`, `BATTLENET_REGION` (default `us`).

For local development the redirect URLs are the same paths on the local host name in [DEV.md](../DEV.md).

## The static website

The landing page and the demo character page move into the app unchanged. Until the app has a public home, the
static pages keep serving from GitHub Pages on the `gh-pages` branch of this repo (a copy is in `static-site/`).
Retiring them is part of the same decision: once the app is live, replace the `gh-pages` pages with a redirect to
the new address, then delete `static-site/`.
