# Scarlet web app and API

The API the Scarlet app uploads to, the database that rolls uploads up, and the web app that shows them. Design:
[docs/DESIGN.md](docs/DESIGN.md). Read it before changing anything; change it first when the design changes.

## Stack

PHP `^8.5`, Laravel `^13`, pnpm + Vite + Tailwind 4, Livewire 4 + Blade + Alpine, PostgreSQL 18 + TimescaleDB +
Redis, Horizon. `part/` is the Rust crate `scarlet-part`: the part format, the sharing groups and the decoder.

## Rules

- Code rules are the owner's IRIS house rules: controllers authorize, validate with a Form Request, call one Action
  and respond. Actions live under `app/Actions`, one public `handle()`, domain inputs and results, the transaction
  inside. Eloquent first. Jobs are bounded and safe to run twice. Transactions are short and database-only. UTC.
  No `else`. Domain identity strings are constants or enums on their owner.
- No tests: no PHPUnit, no Pest, no `tests/` folder, no test scripts. Verify with `composer lint:check`,
  `composer analyse` and the real app.
- The look is the Scarlet site's. Pages are built from the comps in `docs/comps/` and reviewed against them.
- Dependencies: the newest release that has been public for at least 7 days, never a younger one. pnpm enforces it
  (`minimumReleaseAge` in `pnpm-workspace.yaml`); for composer, cargo and Docker images check the release date and
  pin. `scripts/deps-age` lists locked packages younger than 7 days.
- Never commit a key or secret. Never put a real player name in the repo, a doc, a screenshot or a commit message;
  examples use `Name-Surname`. Do not name other websites or data services.
- `docs/groups.tsv` is the one source for sharing groups. `scarlet-part check-groups` must pass after any change to
  it or to Scarlet's FORMAT.md.
- No CI. Checks run locally and in the owner's flow.
