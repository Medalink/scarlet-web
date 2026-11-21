# Scarlet web app and API: design

Scarlet records what a WoW Forever character does. The addon writes the lines, the desktop app keeps them on the
player's PC. This repo is the part that is shared: an API the app uploads to, a database that rolls the uploads up, and
a web app that shows the results.

This file is the design. Build nothing that contradicts it; change it first. Numbers marked *measured* come from
[MEASUREMENTS.md](MEASUREMENTS.md). The line format is Scarlet's
[FORMAT.md](https://github.com/Medalink/scarlet/blob/main/docs/FORMAT.md) and
[ADDONS.md](https://github.com/Medalink/scarlet/blob/main/docs/ADDONS.md).

## Decisions

| Question | Decision | Why |
| --- | --- | --- |
| Stack | Laravel 13 on PHP 8.5, Livewire 4 + Blade + Alpine, PostgreSQL 18 + TimescaleDB, Redis, Horizon | The owner's house stack. One app serves the API and the pages |
| Upload unit | A *part*: one session of one character, one sharing group | A session never changes once it ends, so its parts are immutable and a hash names them for good |
| Identity of a part | SHA-256 of its text | Nothing is sent or stored twice, whatever PC or reinstall it comes from |
| Filtering | In the app, before hashing | The hash covers exactly what is shared. A group that is off is never built, so the server cannot see it |
| Wire compression | One zstd frame per request over many parts | Parts are small; compressing them together keeps the ratio of a whole file |
| Decoding | A small Rust tool, `scarlet-part`, that the queue job runs | Stock PHP has no zstd. The same crate builds the parts in the app, so the format lives in one place |
| Upload credential | An Ed25519 key per install, every request signed | No password, no secret on the wire, and the server stores only public keys |
| Accounts | Sign in with Google or Battle.net. The account owns installs and characters | Private views and control from the browser. Only the provider's subject id is stored |
| Linking the app | The app shows a code and opens the browser; the player confirms while signed in | No local listener, no token handed to the app, works from another device |
| Other players' names | Pseudonyms everywhere except the Social group, and never public unless that player made their own character public | A name belongs to its owner |
| Repo | One Laravel app in this repo is the website and the API. The static pages keep serving from the `gh-pages` branch until the public deploy | Sign-in, search and private views need a server, and the owner wants the API to be part of the web |

## Words

- **Install**: one copy of the Scarlet app on one PC, with its own key.
- **Account**: a signed-in person. Owns installs and the characters they upload.
- **Character**: `Name-Surname`. WoW Forever has no realms.
- **Session**: from an `S` line to its `ES` (login or reload to logout or reload). Keyed by its start in epoch seconds.
- **Sharing group**: a set of line codes a player can stop sharing. Every code is in exactly one group
  ([groups.tsv](groups.tsv)).
- **Part**: the lines of one group in one session.
- **Ruleset**: how a character plays, read at the start of each session (see Rulesets).

## Sharing groups

`docs/groups.tsv` is the one source: `key`, `label`, `codes`. `scarlet-part check-groups <FORMAT.md>` fails when a code
is in no group or in two, and the app, the decoder and the settings page all read the same table.

| Key | Label | What it holds |
| --- | --- | --- |
| `core` | Character and sessions | Sessions, levels, the zones you enter, the experience preset, war mode, time played. Always sent while uploading is on: every chart needs the session, the level and the zone |
| `xp` | Experience and levelling | Experience gains and their sources, rested state, AFK, camps |
| `quests` | Quests | Accepts, turn-ins, objectives, rewards, gossip choices, scenarios |
| `combat` | Combat, kills and deaths | Kills, deaths and their recap, fights, bosses, casts, auras, damage totals |
| `pvp` | PvP and duels | Honor, player kills, battlegrounds, duels |
| `movement` | Movement and map paths | Path points, distance, flights, mounts, teleports, waypoints |
| `world` | Creatures and places seen | Creatures and objects you met and the map cell you stood in, NPC windows, vignettes |
| `loot` | Loot, items and drop rates | What you looted and from what, the loot rule, rolls, bag changes |
| `economy` | Gold, vendors, auctions, mail and trade | Money, vendors, the auction house, mail, trades, the bank, currencies |
| `professions` | Professions and crafting | Skills, crafts, recipes, orders |
| `build` | Character build and gear | Stats, gear, talents, spells, action bars, pets |
| `social` | Social and the players you meet | Groups, guild, friends, players seen, chat counts |
| `collections` | Collections, achievements, Legacy and reputation | Legacy Challenges and points, mounts, pets, reputation, statistics |
| `client` | Game client and addons loaded | Client checks, addon list, UI errors, screenshots, loading screens |
| `addons` | Other addons | The whole addon stream of ADDONS.md, every integration |
| `names` | Names and item facts | Not a switch. A name line (`N`, `SN`, `IN`, `II` and the like) is sent only in a session where a sent line uses that id |

Rules:

1. Uploading is off by default. Turning it on turns every group on. Each group except `core` has its own switch.
2. An off group is never built into a part, so it never leaves the PC. Recording and the local app are not affected.
3. Turning a group off asks one question: keep what the server already holds for it, or remove it. Removal is one
   request and is final.
4. "Delete everything I uploaded" removes every part, every decoded row and the characters themselves.
5. Turning a group back on sends its parts from the local history. Nothing else is re-sent, because the other parts'
   hashes did not change.

### Other players' names

Lines in many groups name another player (`LT`, `DP`, `PK`, `TR`, `ML`, `QA` and others; the field list is in
[player-fields.tsv](player-fields.tsv)). The app replaces each such name with a pseudonym before hashing:
`~` followed by 10 base32 characters (RFC 4648 lower-case alphabet `a-z2-7`, the first 50 bits) of
`HMAC-SHA256(install name key, lower-case name)`. The character's own name is left as it is. The name key is a second
secret of the install and is never sent. The same player gets the same pseudonym in every part from that install, so
"who got which drop" still works without the name. Guild names of other players are treated the same way.

Names appear in clear only in `social` parts. With `social` on, each social part also carries the pseudonyms that
session used, as `pn` lines (`<dt>\tpn\t<pseudonym>\t<Name-Surname>`; lower-case codes are written by the app, never
by the addon), so the uploader's own private view can show names. Turning `social` off and removing it leaves the
server with pseudonyms only.

## Parts

```
scarlet-part\t1\t<Name-Surname>\t<session start, epoch s>\t<group key>
<dt>\t<code>\t<field>\t<field>...
```

- UTF-8, `\n` line ends, fields as in FORMAT.md. `dt` is milliseconds since the previous line of this part, counted
  from the session start for the first line. Lines of one part keep their recorded order.
- The addon stream's part uses group `addons` and lines `<dt>\t<key>\t<code>\t<fields>`.
- A session with no line in a group has no part for it. A `social` part that has only `pn` lines exists.
- The part's id is the SHA-256 of this text, 32 bytes, written as lower-case hex.
- How lines become sessions: all payload lines of a character are put in time order (a stable sort, ties keep the
  recorded order). A session runs from an `S` line to its `ES`, or to the line before the next `S`. Lines between an
  `ES` and the next `S` are that login's own first lines (durability, pet, client checks) and join the next session;
  lines after the last `ES` join the last session; lines before any `S` form a session keyed by the first line's time.
  The key is the `S` time in epoch seconds, raised by one second until it is unique for the character. `dt` can be
  negative when a recorded line is earlier than the one before it.
- A line whose code is in no group is never sent. The `names` group needs no switch: a name line (`name-refs.tsv`,
  rows with shape `def`) goes into the session's `names` part when a line of a group that is on references its id
  (rows of every other shape say which field holds which kind of id). `pn` lines come first in a `social` part, `dt` 0.
- Parts are listed in order of session, then group (the order of `groups.tsv`). Nothing depends on hash order.
- A session still open when the app exports is not uploaded until a later session of that character exists or its
  `ES` line is present. A part therefore never changes. If a recovered chunk later adds lines to an old session, its
  parts get new ids and replace the old ones in the next sync.

The companion builds parts from its payloads (`out/<Name-Surname>/payloads/*.scl` and `addons/payloads/*.scl`), which
stay as they are. `scarlet-part` is a Rust crate in this repo (`part/`). The companion depends on it by git revision;
the server runs its binary.

## Upload protocol

All upload requests are JSON or binary over HTTPS under `/api/v1/upload`, signed by the install (see Security).

1. **Compare.** `POST /sync` with the character, the group keys that are on, the number of parts the app holds for it
   and a set digest: the XOR of all its part ids. The server answers `{"match": true}` when its own count and XOR are
   the same. A day with nothing new costs this one request.
2. **Manifest.** On a mismatch the app sends `POST /manifest`: the character and every part as
   `id, session, group, raw bytes`, zstd-compressed. The server answers with the ids it needs and deletes the parts it
   holds that the manifest no longer lists (a group turned off with removal, a replaced session).
3. **Send.** `POST /parts`, `Content-Type: application/vnd.scarlet.parts+zstd`: one zstd frame (level 19, window up
   to 8 MiB) over the needed parts, each as a 4-byte little-endian length and the part text. At most 4 MiB compressed
   and 64 MiB decompressed per request, 20,000 parts; the app splits larger sets. The server recomputes each id,
   refuses a part whose id was not asked for, stores the rest and queues decoding. The answer lists stored ids.
4. Requests are idempotent. A repeated `/parts` stores nothing new and answers the same.

What the server already has is known two ways: the app remembers the ids the server acknowledged
(`out/upload-state.json`), and the set digest catches every case where that memory is wrong.

Server-side removal:

- `DELETE /groups/{key}` removes that group's parts and decoded rows for every character of the install's owner and
  marks the group refused, so a later sync with that group still on is answered with `group_refused` and the app
  switches it off. `PUT /groups/{key}` from the app clears the refusal.
- `DELETE /` removes everything the owner uploaded.

The same two removals are on the account page of the web app.

## Data model

Three layers. Only the first is the truth; the other two can be rebuilt from it.

**1. Parts, stored once.** `parts(id bytea primary key, character_id, session_start, group_key, lines, raw_bytes,
body bytea)`. `body` is the part text, zstd-compressed by `scarlet-part`, kept out of line and never recompressed by
PostgreSQL. A part belongs to one character; the same id uploaded again is a no-op.

**2. Decoded facts, TimescaleDB hypertables.** One decode of a session reads all of its parts, merges them by time,
carries the context forward the way FORMAT.md describes (zone, level, instance, group, loot rule) and writes rows.
Decoding a session first deletes that session's rows, so it can run any number of times. Every fact row carries
`at, character_id, session_start, ruleset_id, level, zone_id`.

| Table | From | Group | Row |
| --- | --- | --- | --- |
| `sessions` (plain) | `S`, `ES`, `L`, `GR`, `PB`, `XD`, `GA`, `CD`, `TP` | core | one per session: start, end, played, class, race, faction, levels, ruleset |
| `play_spans` | `S`, `ES`, `L`, `Z`, `AFK` | core | a stretch at one level in one zone: seconds, AFK seconds, instance type |
| `level_ups` | `L` | core | level reached, played seconds spent in the level before it |
| `xp_gains` | `X` | xp | amount, source (`k` kill, `d` dungeon kill, `q` quest, `e` exploring, `o` other), source id |
| `kills` | `K` | combat | npc, by |
| `deaths` | `D`, `DL`, `DR` | combat | map position, killer kind, killer creature, killer level, spell |
| `dungeon_runs` | `Z`, `E`, `B`, `D` | combat | instance, seconds, bosses, deaths, group size (the run rule of OVERVIEW.md) |
| `quest_events` | `Q` | quests | quest, accepted, turned in or abandoned, xp, copper |
| `money_changes` | `M`, `S` | economy | earned, spent, balance (transfers between own characters count as neither) |
| `loot_opens` | `LO` | loot | source kind and id, first read or not, loot rule, threshold, group size |
| `loot_drops` | `LO`, `O` | loot | source, item, quantity, loot rule, group size |
| `skill_ups` | `SK`, `SL` | professions | skill, rank |
| `crafts` | `CR` | professions | item, quantity, recipe |
| `path_points` | `P` | movement | map, x, y |
| `creature_sightings` | `NS` | world | npc, level, classification, type, map, cell |

Dictionaries, plain tables: `names(kind, id, name)` from the name lines, `items` from `II`, `zones`, `rulesets`.
A name becomes the shown one for an id when most uploads agree on it.

Codes with no table yet stay in their parts. Adding a table later is a new decoder version and a re-decode from the
stored parts, with nothing re-uploaded.

**3. Rollups, continuous aggregates.** Each exists twice where both are needed: by character for character pages, by
ruleset for the public dashboards. Global numbers are the ruleset rows summed. Ruleset rollups also keep a
hyperloglog of characters, so a page can refuse to show a bucket that fewer than `SCARLET_MIN_CHARACTERS` characters
contributed to (5 on a public deploy, 1 in development).

| Aggregate | Bucket | Keys | Values |
| --- | --- | --- | --- |
| `xp_daily_character`, `xp_daily_ruleset` | 1 day | level, source | xp, gains |
| `play_hourly_character`, `play_hourly_ruleset` | 1 hour | level, zone | seconds |
| `level_time_ruleset` | 1 day | level | characters, seconds as a percentile sketch (median, quartiles) |
| `kills_daily_character`, `kills_daily_ruleset` | 1 day | npc | kills |
| `deaths_daily_character`, `deaths_daily_ruleset` | 1 day | level, zone, killer | deaths |
| `dungeons_daily_ruleset` | 1 day | instance | runs, seconds sketch, deaths |
| `quests_daily_ruleset` | 1 day | quest | turn-ins, xp |
| `money_daily_character`, `money_daily_ruleset` | 1 day | level | earned, spent |
| `drops_daily`, `opens_daily` | 1 day | source, item, loot rule | drops, quantity, opens (drop rate is drops over first opens) |
| `skills_daily_ruleset` | 1 day | skill | skill-ups, highest rank |
| `heat_daily_ruleset`, `heat_daily_character` | 1 day | map, 100 x 100 cell | points |

Aggregates refresh on a policy and once more, for the affected time range, after a decode or a removal. Removing a
group or everything deletes the fact rows; TimescaleDB records the change and the refresh takes the contribution out
of the rollups.

### Rulesets

A ruleset is the set of choices that make two characters comparable. It is read at the start of each session:

| Signal | Line | Values |
| --- | --- | --- |
| Experience preset | `GR` preset | the client's preset number |
| Hardcore | `GR` hardcore | on, off |
| War mode | `PB` warModeActive | on (PvP), off (PvE), unknown |
| Experience gain | `XD` | on, off |
| Game mode | `GA` | the client's mode number |

`rulesets(id, preset, hardcore, war_mode, xp_off, game_mode)` has one row per combination seen. Class, race and
faction are filters on a page, not part of the ruleset. The `S` line's server fields are empty on Forever and are not
used. The loot rule (`LM`) is a property of a drop, not of a character.

## Security

**Install keys.** On first use the app makes an Ed25519 key pair and a name key and keeps them in the Windows
credential store. `POST /api/v1/installs` registers the public key. Every upload request carries

```
Scarlet-Install: <install id>
Scarlet-Time: <epoch seconds>
Scarlet-Signature: base64(Ed25519(method \n path \n time \n SHA-256(body) hex))
```

The server refuses a time more than 300 seconds off and remembers signatures for that long, so a request cannot be
replayed. Bearer tokens were the alternative; a signature never puts a secret on the wire and a database leak yields
nothing that can upload.

**Who owns a character.** No check can prove it: the recording is a file on the player's PC, and anything shown or
typed in game ends up in that same file, so a claim code in game proves nothing more than the upload itself. The
design says so plainly instead of pretending:

- The first install to upload a name holds it. A second install can upload the same name only when both belong to one
  account.
- A character page is private until its owner makes it public.
- Each decode runs plausibility checks (time order, session length against the clock, experience against the level
  table, level gains against played time). A character that fails is kept but left out of leaderboards and rollups
  until the data stops failing.
- A report link on public pages; a held name can be released by the account that holds it or by the operator.

**Limits.** Registration: 5 installs per IP per hour. Upload: 4 MiB per request, 64 MiB decompressed, 200 MiB per
install per day, 50 characters per install, and the zstd window is capped so a small body cannot expand without
bound. Requests are rate limited per install and per IP with Laravel's limiter. Revoking an install on the account
page stops its key at once.

**Accounts.** Sign-in is Laravel Socialite with Google and Battle.net, authorization code with PKCE and a `state`
check. Stored per identity: provider and the provider's subject id. Not stored: email, name, picture, BattleTag. No
feature needs an email address, so the scopes ask for none (`openid` only). An account can hold both providers.
Sessions are Laravel's own, cookie based. Client ids and secrets come from the environment.

**Linking the app.** In Settings the player presses "Link to my account". The app asks the API for a link code
(a signed request), shows it and opens `/link?code=XXXX-XXXX` in the browser. The player signs in and confirms; the
install now belongs to the account. The app polls `GET /api/v1/installs/me` until it sees the link. The code lives 10
minutes and works once. The app holds no account token: its key stays the only credential.

Uploading works without an account. An account adds the private views, the public switch per character and control
from the browser.

## Privacy

| Data | Public | Signed-in owner | Notes |
| --- | --- | --- | --- |
| Global and per-ruleset rollups | yes | yes | Every upload counts. Buckets under the character threshold are not shown |
| Creatures, items, zones, quests, drop rates | yes | yes | Game facts, with no character attached |
| A character's page | only when its owner switched it to public | yes | Off by default |
| Leaderboards | public characters that pass the plausibility checks | | |
| Gold, mail, trades, auctions of a character | no, even on a public page, unless the owner also switches "show economy" on | yes | |
| Other players' names | only a name whose own character is public | yes, with `social` on | Otherwise "a player" |
| Guild names of other players | no | yes, with `social` on | |
| Raw parts | no | download of their own | |

## API

Versioned by path: `/api/v1`. A breaking change is `/api/v2`; fields are only ever added inside a version.

- **Upload**, signed by an install: `POST /installs`, `GET /installs/me`, `POST /installs/link-code`,
  `POST /upload/sync`, `POST /upload/manifest`, `POST /upload/parts`, `GET /upload/status`,
  `PUT|DELETE /upload/groups/{key}`, `DELETE /upload`.
- **Public**, read-only JSON, no sign-in: `GET /public/stats`, `/public/rulesets`, `/public/rulesets/{id}` and its
  series (`/levels`, `/xp`, `/deaths`, `/dungeons`, `/professions`, `/gold`, `/activity`), `/public/leaderboards/{board}`,
  `/public/search?q=`, `/public/characters/{name}` and its series, `/public/creatures/{id}`, `/public/items/{id}`,
  `/public/zones/{id}`, `/public/quests/{id}`, `/public/maps/{id}/heat`. `Access-Control-Allow-Origin: *`,
  `Cache-Control: public, max-age=300` with an `ETag`, cursor pagination (`?cursor=`, `next_cursor` in the answer),
  60 requests a minute per IP, 600 with a free API key (a Sanctum token made on the account page, sent as a bearer
  token). The reference is the `/developers` page, written from the same route table.

The signed-in pages read the same Actions as the public API, with the owner's private data added.

## Pages

Every page keeps the look of the Scarlet site: the dark survey sheet, scarlet, Sofia Sans Extra Condensed for
headings, Sofia Sans for text, Martian Mono for numbers, hairlines, large figures and the map style. Comps are in
`docs/comps/`; a page is built from its comp and reviewed against it.

| Path | Page |
| --- | --- |
| `/` | The landing page, carried over as it is |
| `/demo` | The demo character, carried over |
| `/stats` | Global dashboard: characters and hours, levelling curve, time per level, XP sources by level, deaths by level and top killers, dungeons, professions, gold by level, play-time heatmap by hour and weekday |
| `/rulesets`, `/rulesets/{slug}` | The same dashboard per ruleset, and rulesets side by side |
| `/leaderboards` | Fastest to a level, fewest deaths, most quests, hardcore survivors, per ruleset |
| `/search` | Characters that are public, creatures, items, zones, quests |
| `/c/{name}` | A character: overview, levels, sessions, deaths, dungeons, map paths. Public when its owner says so; the owner sees the private parts too |
| `/creatures/{id}`, `/items/{id}`, `/zones/{slug}`, `/quests/{id}` | Result pages: kills and killers, drop rates by loot rule, where it is, levels |
| `/maps/{id}` | Heat from paths and deaths on a zone's map |
| `/developers` | The public API reference |
| `/login`, `/link` | Sign in, link the app |
| `/account` | Characters and their public switches, installs and revoking them, sharing groups, removals, API keys |

## Hosting

Nothing here is deployed. See [HOSTING.md](HOSTING.md) for the options, their monthly cost and what the owner has to
decide and register.

## Ground rules for the code

Laravel code follows the IRIS house rules (`AGENTS.md`): controllers authorize, validate with a Form Request and call
one Action; Eloquent first; bounded jobs; short database-only transactions; UTC. No tests of any kind. Checks are
Pint, Larastan with the house rules, and the real app. PHP 8.5. Every dependency is the newest release that is at
least 7 days old.

Local development runs in wslc containers only ([DEV.md](../DEV.md)): the app, Horizon, PostgreSQL with TimescaleDB
and Redis, with a private database per worktree. Changes land through the owner's continuous flow with `ship`; there
are no pull requests and no CI. The license is GPL-3.0-only, as for the rest of Scarlet.

The landing page says what is true once uploading exists: Scarlet keeps the recording on the player's PC, and
uploading is optional and off until the player turns it on.
