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
| Wire compression | One zstd frame per request over many parts | Parts are small; compressing them together keeps the ratio of a whole file. *Measured:* a 125-hour history (148 sessions) is one request of 1.2 MB, a typical new session 2.8 KB, about 10 KB per hour played; stored alone the same parts take 25 percent more |
| Decoding | A small Rust tool, `scarlet-part`, that the queue job runs | Stock PHP has no zstd. The same crate builds the parts in the app, so the format lives in one place. *Measured:* the bare line-parsing loop takes 62 ms in PHP and 30 ms in Rust on 258,000 lines; the full decode of a 125-hour history takes 121 ms in Rust |
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

A killing blow (`DR`) whose source the game did not identify (no killer kind) still names it in field 3. When that
name has a player's `First-Last` shape, it is replaced like a player's, so a player who lands an unidentified killing
blow never reaches the server under their own name.

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
  lines after the last `ES` are held back and join the next session that starts, so a closed session never changes (the
  builder counts them in `Stats.held_back`); lines of a character with no `S` at all form one session keyed by the first
  line's time, which never closes.
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
4. Requests are idempotent. A repeated `/parts` stores nothing new and answers the same. It also accepts a part the
   server already holds even when the last manifest did not ask for it, so a retry after a lost answer works.

The wire details are in [API.md](API.md). Where the first implementation settled something this page left open:

- The manifest is one zstd frame of JSON with its own content type, `application/vnd.scarlet.manifest+zstd`. It is
  inflated with `scarlet-part inflate`, which enforces the same window and size limits as a batch.
- A batch is opened with `scarlet-part unbatch --stored`, which checks every id against the part text and writes each
  part in its storage form (zstd 19 alone), so PHP stores the bytes it is given and never recompresses.
- A manifest lists at most 200,000 parts; `sync` and `status` are bounded the same way.
- The XOR set digest is computed in PostgreSQL (`bit_xor` over four 64-bit words of the id), not by reading ids.
- Decoding is one queued job per (character, session) on the `ingest` queue, unique until it starts, so ten parts of
  one session uploaded in two requests decode once. A per-character lock serializes the jobs of one character.

What the server already has is known two ways: the app remembers the ids the server acknowledged
(`out/upload-state.json`), and the set digest catches every case where that memory is wrong.

Server-side removal:

- `DELETE /groups/{key}` removes that group's parts and decoded rows for every character of the install's owner and
  marks the group refused, so a later sync with that group still on is answered with `group_refused` and the app
  switches it off. `PUT /groups/{key}` from the app clears the refusal.
- `DELETE /` removes everything the owner uploaded.
- Both removals lock the owner's character rows, lowest id first, before they delete anything: the same lock a decode
  takes, so a removal waits for a running decode instead of deadlocking with it on the fact rows.

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
| `sessions` (plain) | `S`, `ES`, `L`, `GR`, `RU`, `PB`, `XD`, `GA`, `CD`, `TP` | core | one per session: start, end, played, class, race, faction, levels, ruleset |
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
| `talent_changes` | `TA` | build | a point picked, refunded or swapped: node, ranks, the ordinal of the point in the build, the respec it belongs to |
| `talent_snapshots` | `TG`, `TA` | build | the build as ranks, tab points, build hash and loadout, one row per change of it |
| `talent_respecs` | `TA`, `TQ` | build | refunds that came together: points moved, cost, hash before and after |

Dictionaries, plain tables: `names(kind, id, name)` from the name lines, `items` from `II`, `zones`, `rulesets`,
`talent_trees` and `talent_nodes` (the class tree layouts of `TV` and `TD`, one per tree, client build and layout hash;
`uploads` counts the sessions that sent a layout, and the layout with the most uploads is the one drawn).
A name becomes the shown one for an id when most uploads agree on it.

The talent tables are the build group's. Removing the group deletes their rows and clears `characters.spec_tab_id`,
`talent_points` and `build_hash`, which copy the character's latest snapshot. `php artisan talents:backfill` queues a
fresh decode of every session that has a build part; it fills the talent tables from parts uploaded before the decoder
knew them, and is safe to run twice.

How the tables are built (decisions the first implementation made):

- **Keys.** `session_start` is the session key in epoch seconds (`bigint`), `at` is `timestamptz`. A character is found
  by `name_key`, its lower-case name, so two spellings are one character. A ruleset is found by `rulesets.key`, its five
  signals joined as text (`-` for a missing one). Fact tables have no foreign keys: their rows are deleted by character
  and session, and a check per inserted row would cost more than it protects.
- **`counted`.** Every fact row carries `counted`, false when its session has a plausibility flag. The ruleset rollups
  read `counted` rows only; the character rollups read all rows, so an owner sees their own flagged data. This replaces
  "a character that fails stays out of the rollups": the unit is the session, and a replaced session that no longer
  fails counts again on its next decode. The character keeps `plausibility` (`ok` or `flagged`, flagged while any of its
  sessions is) for the leaderboards.
- **Names.** `names` holds one row per candidate name of an id with `votes`, the number of sessions that reported it;
  the shown name is the one with most votes. A session adds its votes once (`sessions.names_counted`), so a second decode
  changes nothing.
- **Time per level.** A level's time spans sessions, so it cannot come from a daily rollup of `play_spans`. Ingest
  rebuilds `character_levels` (a hypertable on the time the character first stood at the level) for the character after
  each session: one row per level with `seconds` at that level, `before_seconds` (the sum of the lower levels, which is
  the time to reach it), `completed` (a higher level exists) and the `ruleset_id` and `counted` of its first span.
  `level_time_ruleset` is a continuous aggregate over it.
- **Dungeon runs across sessions.** The decoder's rows are stored as written. A run that carries on the last run of the
  session before (ROWS.md, joining sessions) has `continues_previous` set; ingest sets it for the first run of the
  session it decodes and again for the first run of the next session, so the upload order does not matter. Counts of
  runs skip those rows, sums of time and bosses include them.
- **Nulls.** A counter the decoder could not know (its group was not sent, ROWS.md "Missing groups") is null in
  `sessions` and `play_spans.afk_ms`, and is never summed as zero.

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
| `world_heat_daily_ruleset`, `world_heat_daily_character` | 1 day | world cell (768 x 512) | points |
| `world_death_heat_daily_ruleset`, `world_death_heat_daily_character` | 1 day | world cell | deaths |
| `world_sighting_heat_daily_ruleset` | 1 day | npc, world cell | sightings |

Reads go through one Action per question in `app/Actions/Rollups/` (`LevellingCurve`, `TimePerLevel`, `XpSourcesByLevel`,
`DeathsByLevel`, `TopKillers`, `DeathZones`, `DungeonRuns`, `Professions`, `GoldByLevel`, `PlayTimeByWeekday`,
`PathHeatCells`, `DeathHeatCells`, `CreatureKills`, `DropRates`, `ItemSources`, `QuestTurnIns`, `Totals`). Each takes an
optional ruleset (none sums every ruleset) and leaves out any group that fewer than `config('scarlet.min_characters')`
characters contributed to; `Totals` returns null for such a total. Pages and the public API call these and never query a
fact table. The drop aggregates carry no ruleset, so `DropRates` and `ItemSources` take none. Heat cells are zone-map
cells keyed by uiMap id; the world map of "Maps" reads the `world_*` rollups.

Aggregates refresh on a policy and once more, for the affected time range, after a decode or a removal. Removing a
group or everything deletes the fact rows; TimescaleDB records the change and the refresh takes the contribution out
of the rollups.

### Rulesets

WoW Forever has five rulesets. A ruleset is a permanent choice for each character; play is realmless, in regional
populations with layers, and names are First-Last.

| Ruleset | What it is |
| --- | --- |
| Normal | Quests, dungeons and monsters. Open-world PvP only if you opt in. Both factions. |
| PvP | Open-world combat outside sanctuaries. One faction per player. |
| Roleplay | Stricter naming and behaviour rules, a PvE focus and outdoor zones separate from non-RP players. Both factions. |
| Roleplay PvP | Roleplay rules plus open-world faction PvP. |
| Hardcore | One life; fallen characters can transfer out. Planned, not out yet: the site shows it disabled. |

The game has no call that returns the ruleset, and war mode alone cannot tell Normal with PvP opted in from PvP. The
ruleset of a character is found outside the recording, in this order:

1. **The Forever Armory.** `https://worldofwarcraft.blizzard.com/en-us/forever/<region>/armory/character/<first-last>`
   embeds the character's profile with `"ruleset":{"name":"Normal","rulesetId":2,"slug":"normal"}`. A queued job
   (`rulesets:detect`, every ten minutes) asks for each character that has no answer yet, one request at a time with a
   two second pause, under a User-Agent that names scarletweb.io. The answer is permanent, so a character that has one
   is never asked again. A character the Armory does not hold (it answers 404 or 500) is asked again after a day, and
   weekly after a week of misses. `RulesetKind::fromArmory()` maps the slug to a kind; a slug it does not know is kept
   raw in `characters.armory_slug` and the character stays "Not identified". The region is `scarlet.armory.region`
   (`us`).
2. **The home realm.** A ruleset is a kind of realm. The realm id inside a character's GUID (`RU` server, kept as
   `characters.home_realm_id` from its latest session that has one) names the realm, and `realms(id, kind, name,
   source)` says which ruleset it is. Two realms are seeded as Normal, the ones the first uploaders were seen on. A
   realm learns its kind from the Armory's answer for any character that lives on it, so one answer files every
   character of that realm, and a disagreement between two answers keeps the first and is logged.

A character with neither answer is "Not identified". The ruleset row a session is filed under carries the kind of its
character: `rulesets.kind` is part of the row's key (`|knormal`) and slug (`-kn`, `-kp`, `-kr`, `-krp`, `-kh`), so
rows that differ only in kind are two rows. A slug from before the kind was part of the identity still opens the row
that has sessions under it. When a character's kind becomes known, `RefileSessions` decodes each of its sessions again
(the ingest files it under the right row and refreshes the rollups); `rulesets:refile` does the same by hand, and
daily. Only the hardcore flag of a session names a ruleset by itself (`RulesetKind::of()`).

| Signal | Line | Values |
| --- | --- | --- |
| Experience preset | `GR` preset | the client's preset number (0 Classic, 1 Enhanced); not a ruleset |
| Hardcore | `GR` hardcore | on, off |
| War mode | `PB` warModeActive | on, off, unknown; not a ruleset |
| Experience gain | `XD` | on, off |
| Game mode | `GA` | the client's mode number |
| Game mode record | `RU` record | the record id the realm picker switches by |

`rulesets(id, preset, hardcore, war_mode, xp_off, game_mode, mode_record, kind)` has one row per combination seen and
kind of character; a row without a record or a kind keeps the key it had before either was known. Class, race and faction are filters on a page, not part of
the ruleset. The `S` line's server fields are empty on Forever and are not used; `RU` carries the server number from
the character's GUID, which becomes `characters.home_realm_id`. The loot rule (`LM`) is a property of a
drop, not of a character.

## Security

**Install keys.** On first use the app makes an Ed25519 key pair and a name key and keeps them in the Windows
credential store. `POST /api/v1/installs` registers the public key. Every upload request carries

```
Scarlet-Install: <install id>
Scarlet-Time: <epoch seconds>
Scarlet-Signature: base64(Ed25519(method \n path \n time \n SHA-256(body) hex))
```

The server refuses a time more than 300 seconds off and remembers signatures for 600 seconds (a time up to 300 s
ahead is valid for up to 600 s), so a request cannot be replayed. Ed25519 is deterministic: the same request signed in
the same second repeats its signature and counts as a replay, so a retry signs again with the new time. Bearer tokens were the alternative; a signature never puts a secret on the wire and a database leak yields
nothing that can upload.

**Who owns a character.** No check can prove it: the recording is a file on the player's PC, and anything shown or
typed in game ends up in that same file, so a claim code in game proves nothing more than the upload itself. The
design says so plainly instead of pretending:

- The first install to upload a name holds it. A second install can upload the same name only when both belong to one
  account.
- A character page is public from its first upload; its owner can make it private. Gold and trades stay hidden
  unless the owner switches "show economy" on.
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
  `/public/zones/{id}`, `/public/quests/{id}`, `/public/map/heat`. `Access-Control-Allow-Origin: *`,
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
| `/c/{name}` | A character: overview, levels, sessions, deaths, dungeons, map paths, talents (the latest build of each talent group drawn on its tree, or listed by tab while no layout is stored; the order the points were taken in; respecs; talent facts: characters with the same build at the same level, respec count and gold, the least picked talent among the class and spec, the level of the first build with 31 points in one tree, the longest and median wait to spend a point, and picks refunded within ten minutes, each with its n). Public when its owner says so; the owner sees the private parts too |
| `/talents`, `/talents/{class}/{spec}` | Public. Every spec with the characters that play it, and one spec: pick rate by node (a heat on the real tree when a layout is stored, a table always), the most common order of points, builds by level bracket, and builds and outcomes (the ten most held builds with the level-ups, median time to level, deaths and dungeon runs that happened while each was held, from one snapshot to the next; `?level=10-19` to `60`; `ruleset` and `server` as on `/stats`). Each character counts once, by its latest counted build read inside the bracket, and every figure prints its n. `/stats` carries a Talents block with the most played specs |
| `/creatures/{id}`, `/items/{id}`, `/zones/{id}`, `/quests/{id}` | Result pages: kills and killers, drop rates by loot rule, where it is, levels |
| `/map` | The world map: one seamless map, zoomable down to a zone, with heat from paths, deaths and kills (see Maps) |
| `/developers` | The public API reference |
| `/download` | Public. The newest build of the Windows app: the installer as the main button, the addon zip, `SHA256SUMS.txt` and the GitHub releases page. The build is read server side from the GitHub releases of `Medalink/scarlet-app` (the newest stable release with an installer, else the newest pre-release; drafts and `beta-channel` are skipped) and cached for 10 minutes, so a page view never reaches GitHub. When GitHub does not answer, the last good answer is shown, and before there is one a link to the releases page. The landing page and the header link to it |
| `/release-notes` | Public. The running build (version, build number, commit, lines, files, and the lines added and removed by the last commit, this build and all time), a rail of released versions, and one release with its changes grouped by area. Filters by New, Improved or Fixed are query parameters (`?v=0.1.0&only=fixed`), so the page needs no script. The footer of every page shows `v<version>.<build>` and links here |
| `/login`, `/link` | Sign in, link the app |
| `/account` | Characters and their public switches, installs and revoking them, recent uploads and when each install last contacted the site, sharing groups, removals, API keys |
| `/account/characters` | Private, signed in only. The account's characters side by side: totals, a table of the characters, play time by day stacked by character (last 30 days), gold with copper moved between your own characters kept apart, time two characters were online together (sessions overlapping by 60 s or more), time in each level against the median of the character's ruleset, and profession ranks. Alts exist only here and in the owner bar of `/c/{name}`, which links the account's other characters. No public page, board, rollup or API response groups, dedupes or weights by account: each character is its own player there, and `characters.account_id` and `first_install_id` are hidden from the model's serialization |

A dungeon is `/zones/{id}` with the negative instance id (`/zones/-90`).

### Versions and release notes

The package `medalink/laravel-app-version` (a `vcs` repository in `composer.json`, pinned to the newest tag that is at
least 7 days old) turns git history into a version and release notes.

- The version is the `VERSION` file. The build number is the count of commits since `VERSION` last changed, so a
  version is cut by changing `VERSION` in a ship of its own (`0.1.0` to `0.2.0`). Nothing is tagged: `ship` pushes
  no tags and the lander rewrites SHAs, so a tag would point at a commit that is not on `main`. Judge's lean was
  `app:version:set` on every ship; it commits and tags, which this flow cannot keep.
- One release note per version, compiled from commit subjects (`release_notes.unit = commit`, because changes land
  without pull requests). Subjects are public text: `config/app-version.php` drops anything that names a player,
  another site or a key, and work on the repo's own tooling; `detail_rewrites` holds the hand-written sentences for
  subjects that read badly in past voice. A commit subject should say what a visitor can now do, starting with a verb.
- The build writes `storage/app/version.json` and `storage/app/release-notes.json` while it has the full history, and
  the deploy publishes the second into `release_notes` after the migrations (docs/HOSTING.md). The running app never
  reads git.
- A signed-in account sees a strip under the header while its read state is behind the current release: "Updates since
  you last logged in", the release's lines, Dismiss (hides the strip) and Mark as read (the account has read
  everything listed). State is `release_note_reads` keyed by account id.
- A release lists commits as changes, not as a commit list: the build panel on `/release-notes` carries the commit,
  the commit count and the lines changed.

### Where the built pages differ from the comps

- **Ruleset names** are the five of WoW Forever: Normal, PvP, Roleplay, Roleplay PvP and Hardcore. A row that no
  signal or realm names is "Not identified", listed last on /rulesets and left out of the filters unless it is the one
  on show. Hardcore is not out yet, so the filters and /rulesets show it as "Hardcore (not out yet)", greyed out and
  without a link, until a hardcore ruleset has data. Two rows of one name are told apart by a count, "(2)". The
  address of a row (`p1-std-pve-g1-kn`) is its signals and the kind of its characters; an address without the kind
  opens the row that has sessions under it. Rows with no sessions are not listed.
- **Class filter**: leaderboards filter by class (`characters.class`). The dashboards and result pages do not: no
  aggregate carries the class.
- **Reach**: "Characters that reached each level" in place of the comp's wording; the chart counts characters.
- **Dungeon table**: "Bosses a run" replaces "Median level"; "Skills" replaces "Professions"; the gold chart is the mean
  earned and spent per character, not the median carried.
- **Map layers**: the third layer is creatures met; kills carry no position. The map has no Hours/Days toggle.
- **Aggregates without a ruleset**: item and drop-rate figures cover every ruleset, as the drop aggregates have none.
- **Not built**: Played with and the progress bar on the character page (the decoder yields no group membership), Distance
  by how you moved (no per-mode distance), "How it kills" and "Rolls in groups" on creature and item pages.
- **Distance** is in world widths; a step longer than 0.05 of the world is a teleport and is not counted.
- **Phone header**: Stats and Map stay in the landing and demo header on a phone; the other pages drop out first.
- **View transitions**: the `@view-transition` opt-in is also inline in the head, ahead of every stylesheet. Chromium
  re-checks the opt-in as each sheet loads, and with the rule only in site.css a click was cancelled with an unhandled
  rejection whenever another sheet arrived first.

## Maps

Every map on the site is one viewer over one world: the stitched Azeroth of the OpenMap addon, a rectangle of
1002 x 668 world units (3:2) where a point is `u, v` from 0 to 1 across and down. A zone page, a character's paths
and a creature's sightings are the same viewer opened on a rectangle of that world.

**Art.** OpenMap bakes every zone's art into world tiles (`Media/Textures/Forever/MapZones/<i>_<j>.blp`: 512 px,
of which 510 are the tile's own and the rest its neighbours' edge, 16 px per world unit; `Data/Forever/ZoneArt.lua`
lists the tiles). `tools/map-tiles` reads an installed copy of the addon and writes a web tile pyramid: 256 px WebP
tiles, `{look}/{z}/{x}/{y}.webp`, from the full detail down to one tile for the whole world. The images are not in
git. They live on the app's `maps` disk (local storage in development, object storage in production) and are served
with a one-year cache under a path that carries the build's version.

**Two looks.** *Game* is the art as baked. *Survey* is the site's dark sheet, built from the same tiles at build
time: luminance mapped onto the sheet's greys, the sea darkest, so scarlet data reads on it the way it does on the
landing page. The viewer remembers the choice. Survey is the default.

**Credit.** Every page that shows the art says "Map art © Blizzard Entertainment. Map by OpenMap." under the
map, with OpenMap linked. Data or code copied from OpenMap into this repo carries its MIT notice
(`tools/map-tiles/OPENMAP-LICENSE.txt`).

**Where a recorded point is.** The recording gives a zone map and a position on it (`uiMapID`, x, y). The world
position needs that zone's rectangle on the world, which the game computes. `map_rects(ui_map_id, u0, v0, u1, v1)`
holds one row per zone map, loaded from `docs/map-rects.tsv`. Ingest stores `u, v` next to the zone position on
`path_points`, `deaths` and `creature_sightings` (null for a map that is not on the world, such as a dungeon), and
the heat rollups are keyed by a world cell: `cell_i = floor(u * 768)` and `cell_j = floor(v * 512)`, a grid of 768 by
512. The world is 3:2 (16032 by 10688 px at the top zoom), so those cells are square: 20.875 px at the top zoom, about
1.3 of the world's 1002 by 668 units a side. A point with no `u, v` is in no cell. `bin/artisan maps:place-points` fills
the nulls of rows whose map has a rectangle, in windows of seven days, then refreshes the world heat; it finds nothing
to do on a second run, so it can run whenever rectangles arrive, and nothing is uploaded again. Kills carry no
position, so the map's third layer is the creatures met (`creature_sightings`), not kills.

**The viewer** is the site's own canvas module, with no map library: tiles by zoom, drag and wheel and pinch to move,
plus and minus keys, a heat layer of world cells in the heat palette, death crosses, a character's paths as scarlet
lines, zone names from the dictionary at the zooms where they fit, and a link from a zone's name to its page. It
loads only the tiles in view. Dungeons and other maps that are not on the world keep the plain sheet with their own
x, y.

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
