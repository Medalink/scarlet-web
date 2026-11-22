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
| Other addons' counts | Each additive statement another addon makes is an addon claim, decoded into its own table and reconciled per character and kind against Scarlet's own rows | History from before Scarlet and from moments it missed joins the totals, and nothing is counted twice (Addon counts) |
| Repo | One Laravel app in this repo is the website and the API. The static pages keep serving from the `gh-pages` branch until the public deploy | Sign-in, search and private views need a server, and the owner wants the API to be part of the web |

## Words

- **Install**: one copy of the Scarlet app on one PC, with its own key.
- **Account**: a signed-in person. Owns installs and the characters they upload.
- **Character**: `Name-Surname`. WoW Forever has no realms. Pages show the first name and surname apart, `Name Surname`
  (`Format::characterName`); URLs, the API and the database keep `Name-Surname`. Search finds a character by either.
- **Session**: from an `S` line to its `ES` (login or reload to logout or reload). Keyed by its start in epoch seconds.
- **Sharing group**: a set of line codes a player can stop sharing. Every code is in exactly one group
  ([groups.tsv](groups.tsv)).
- **Part**: the lines of one group in one session.
- **Ruleset**: how a character plays, read at the start of each session (see Rulesets).
- **Origin**: where a number comes from: `scarlet` (its own lines), `backport` (the one-time Warchive import of
  2026-11, sessions with no build), or an addon key (`fl`, `ff`, `mc`, ...).
- **Addon claim**: one additive statement another addon makes, from a `YC` line (Addon counts). Not to be confused
  with `part_claims`, which records the installs that listed a part.
- **Store**: tells two histories of one addon apart, and is only a value the addon never rewrites while it keeps its
  records (Mastercraft's `life.since`). Session claims have none: a session is its character and its end.

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
| `imports` | Imported history from other addons | Gold balances, sales, purchases and other income and expense read from other addons' saved variables; owner-only, never in a board |
| `fishing` | The Fishing Log | Records, journal, fishing sessions, bounties and the spot leaderboards the companion read from The Fishing Log's saved variables; other anglers as pseudonyms |
| `counts` | Counts from other addons | Casts and catches, kills, runs and other totals other addons counted, added to Scarlet's own without counting anything twice (Addon counts). Includes The Fishing Log's and ForeverFishing's session totals; turning off `fishing` does not remove them |
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

Names appear in clear only in `social` parts and, with `social` on, in the `pn` lines that open a `fishing` part (The Fishing Log, below). With `social` on, each social part also carries the pseudonyms that
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

### Imported history

The `imports` group carries gold balances, sales, purchases and other income and expense that the companion read from
other addons' saved variables (Scarlet's FORMAT.md, "Imported history"). They are a third kind of record beside lifetime
and observed numbers and stay apart from both:

- One part per UTC day, whose session key is that day's start in epoch seconds. `DecodeSession` sends these parts to
  `scarlet-part decode --imports` and `StoreImportedRecords` replaces the day's rows in `imported_records`; the session
  decoder skips the group, so no session, fact row, rollup or board ever sees them.
- `imported_records` has no public reader. `ShowImportedHistory` feeds the signed-in owner's character page and the
  account's characters page, both labelled with the source. Removing the group or the character deletes its rows.

### The Fishing Log

The `fishing` group carries what the companion read from The Fishing Log's saved variables (Scarlet's FORMAT.md, "The
Fishing Log"): the character's records, journal, fishing sessions and bounties, and its game account's shared record
book, bounty boards, anglers met and surveys.

- One part per UTC day, as imports. Every line is a snapshot with the time of what it describes, so a record that is
  beaten or a count that grows changes its day's part, which replaces the old one in the next sync.
- Other anglers' names (holders, standings, anglers met, champions) become pseudonyms by player-fields.tsv, the
  character's own name stays. With `social` on, a fishing part starts with a `pn` line (`dt` 0) for each pseudonym it
  uses, as a social part does, and they join the character's `pseudonyms`.
- `DecodeSession` sends a key's `fishing` parts and `addons` parts to `scarlet-part decode --fishing`, and
  `StoreFishingLines` replaces the key's rows in `fishing_lines`: one row per `J` line, and one per `fl C` catch of the
  addon stream (code `fl.C`), with `code`, `at` (epoch seconds) and the line's fields by name in `fields` (jsonb). The
  session decoder skips the group. A decode with no such part clears the key's rows, so a removed part takes its rows
  with it.
- The account's record book reaches the server once per character of the account and once per uploader who heard it;
  a board takes the heaviest weight per spot, species, scope and season.
- Nothing reads `fishing_lines` yet. Removing the `fishing` group deletes its `J` rows, removing `addons` its catches.

## Upload protocol

All upload requests are JSON or binary over HTTPS under `/api/v1/upload`, signed by the install (see Security).

1. **Compare.** `POST /sync` with the character, the group keys that are on, the number of parts the app holds for it
   and a set digest: the XOR of all its part ids. The server answers `{"match": true}` when its own count and XOR are
   the same. A day with nothing new costs this one request.
2. **Manifest.** On a mismatch the app sends `POST /manifest`: the character and every part as
   `id, session, group, raw bytes`, zstd-compressed. The server answers with the ids it needs, records that this install
   lists the parts it already holds, and drops this install's claim on the parts the manifest no longer lists (a group
   turned off with removal, a replaced session). A part is deleted when no install claims it. A character played on two
   PCs keeps what each PC uploaded, and each install's `/sync` compares only the parts it claims.
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

## Addon counts

Other addons on the player's PC have counted what Scarlet counts: The Fishing Log, ForeverFishing and Mastercraft
count casts and catches, NaowhForever kills, ForeverDungeonJournal runs, RXPGuides experience by level. The owner's
goal (2026-11-22): every Scarlet user's addon data is "imported and part of our total scarlet data on the web and in
the app", "marked as addon data but rolled into our datasets", and "we do not duplicate data". This section replaces
the data rule of 2026-11-21 for addon claims; imported history (`imports`) keeps that rule, so it is still never added
to an observed number.

### What is summed

An addon claim is one additive statement: a scope (this character, or the characters of a game account), a kind, a
window (an event, a session, a day, a level, or a lifetime `[since, as of]`), dimensions (zone, item, npc) and
amounts in Scarlet's metric names. A non-additive fact (a record weight, a first catch, an attunement step) is not a
claim: it stays in its addon's group and is shown beside, never summed.

Only a field this table maps becomes an amount. Each row was measured against Scarlet's own rows on the owner's data
(2026-11-22, 12 sessions since 2026-11-20 19:36 UTC, 366 channel ends); a new integration adds its rows with its own
measurement before it ships.

| Origin | Field | Becomes | Measured against Scarlet |
| --- | --- | --- | --- |
| `fl` | session `outcomes.casts` | `casts` | equal in every session (326 of 326). Counts every fishing channel end, including the short cancelled channels Scarlet drops |
| `fl` | session `outcomes.landed` | `landed` (`gathers.looted`) | equal in every session (295 of 295) |
| `fl` | session `outcomes.unknown` | `unknown` | equal to Scarlet's dropped cancelled channels plus its unlooted rows (31 = 23 + 8) |
| `fl` | session `count` plus the sum of the session's `spots[].junk` | `items` (`gather_items.quantity`) | 320 against 316, +1.3%. A session's `spots[].count` repeats the session's own `count` and is never summed |
| `fl` | a session with no `outcomes` | `items` only | a catch started it: the player stopped the session between bite and loot |
| `fl` | `early`, `escaped`, `cancelled`, `duration`, `elapsed`, weights, `value`, `catchSkill`, journal counts | nothing | messages, not casts; `cancelled` never fires on this client; weights are random rolls; the lifetime tables equal the sum of the sessions |
| `ff` | session `casts`, `successfulCasts`, `catches` | `casts`, `landed`, `items` | as `fl`, plus one cast per cast a reload interrupted; no overlap with Scarlet, unmeasured |
| `ff` | `lifetime`, `journal`, `fishingLocations`, `activeSeconds` | nothing | the lifetime equals the sessions on the owner's data (it also counts sessions with no catch, which are not saved); rod-equipped time is not fishing time |
| `mc` | `fishing.life.casts`, `catches`, `gained` | `casts`, `landed`, `items` as an account `life` claim, reference only | 1.2%, 0.9% and 1.3% below the sum of `fl` and `ff` over its span |
| `mc` | `life.escapes`, `sessions`, `seconds`, `history` | nothing | `escapes` never fires; sessions and seconds follow its own rules; `history` is gold and skill |

### Lines, parts and the archive

The companion writes claims as `YC` lines (Scarlet's FORMAT.md, "Addon counts"). It keeps an archive of every claim
it has read per character, so an addon that wipes or caps its history (The Fishing Log's fresh start, a reinstall)
takes nothing back. A claim's key is (origin, store, scope, window, kind, dims, window end, and window start for a
lifetime). It never holds a part id or an install, so the same claim from two PCs is one claim. An account-scope
claim names no character and no account: it is carried with the same text by every exported character of that game
account folder.

`YC` lines travel in one `counts` part per character and UTC day of the window's end, keyed like `imports` parts.
`DecodeSession` sends them to `scarlet-part decode --counts`; the session decoder skips the group. A code the decoder
does not know is counted and skipped, never fatal to the day. The server's decoder ships before an app writes a new
kind, and `groups.tsv` before FORMAT.md lists a new code.

`counts` is a group of its own and does not follow the group of the dataset a claim feeds: decode routes a part by its
group, day parts (`imports`, `fishing`, `counts`) apart from session parts, and one code sits in one group. Each switch
removes its own lines. Turning off Professions removes Scarlet's gathers; the addon's fishing counts stay until
Counts from other addons is turned off. After either removal the character is reconciled again, so a claim that
Scarlet's rows had covered can count once more, never twice.

### Tables

- `addon_claims`: one row per claim key per character: `character_id`, `origin`, `store`, `scope` (`c` or `a`),
  `window`, `kind`, `from_at`, `to_at` (epoch seconds),
  `dims` and `amounts` (jsonb), and, written by reconcile only, `status`, `counted` (jsonb), `evidence` (jsonb),
  `ruleset_id`, `counted_ok`, `level` and `zone_id` (null when unknown, read as an unknown bucket, never guessed),
  `reconcile_version`.
  An account claim has one row per character whose parts carry it. Those characters are its scope, and the account
  page and rule 7 read it once per key.
- `addon_claim_parts(claim_id, part_id, amounts)`: the parts that carry a claim, each with the amounts it carried.
  Decoding a part replaces its rows here and inserts claims whose key is new; a claim goes when no part carries it.
  When carrying parts disagree, the claim takes the amounts of the part whose id sorts highest, and the audit lists
  the claim. Decode reads no other table and writes no fact table or rollup, so Scarlet's own numbers cannot move.
- `addon_days(character_id, day_key, decoder_version, reconcile_version)`: the scheduled outdated pass re-decodes and
  re-reconciles these as it does sessions.

### Reconcile

`reconcile(character, kind)` is one function in `scarlet-part`, run by the server and by the app. It reads rows, not
parts: the character's claims of the kind and its Scarlet rows of the kind (for fishing, `gathers` and
`gather_items`), counted or not, and gives every claim exactly one status with its counted amounts and its evidence.
Every counted amount is floored at 0 and rounded half up; a ratio whose denominator is 0 is 0. The server runs it under the character lock after any decode of
the character, any removal, a ruleset re-file and a plausibility change.

| Status | Counted | When |
| --- | --- | --- |
| `counted` | whole | no Scarlet row and no higher-ranked claim falls in its window |
| `residual` | part | Scarlet's rows cover part of its window |
| `superseded` | 0 | Scarlet's rows, a higher-ranked origin, the same claim on another character, or another claim of its origin cover it; the evidence names which |
| `held` | 0 | an event with no matching Scarlet row inside a Scarlet session that recorded rows of its kind |
| `rejected` | 0 | it fails its bounds: landed above casts, `casts - unknown` above the window's seconds / 2 + 1, a window in the future or before the addon existed |
| `reference` | 0 | an account-scope claim: shown on the account page and checked as a ceiling, never added to a character |

Rules, in order:

1. **Clock.** Scarlet stamps with the PC's clock. `fl` session times and `mc` use the same clock and need no offset
   (within 2.5 s on the owner's data). `ff` stamps with the game server's clock; the distance is that PC's clock
   error, unmeasured on the owner's data. Until Scarlet's `S` line carries the server time, an `ff` claim counts only
   when no Scarlet row of the kind lies within 24 hours of its window. Nearer than that it is `superseded` when rows
   are assigned to it and `held` when none are, never `residual`. An event origin takes its offset per character from
   the pairs that match, never a constant per origin.
2. **Events** match one to one with an unused Scarlet row of the same key (npc, item) within the origin's tolerance.
   Matched is `superseded` with the row it matched.
3. **Dated claims against Scarlet.** Each Scarlet row is assigned, per origin, by its `at` (the cast start) to the
   claim whose window contains it. A row in no window goes to the claim whose nearer edge is closest, within 60 s; at
   equal distance, to the claim that starts later. With no row assigned, the claim is `counted`. Otherwise, for
   fishing:
   - casts: the claim's `casts - unknown` is at most the rows Scarlet keeps when it saw the whole window, because
     `unknown` holds the cancelled channels Scarlet drops. Counted casts = `casts` x the share of `casts - unknown`
     that the assigned rows do not cover.
   - landed: counted = `landed` minus the assigned rows whose `looted` is true. A row whose `looted` is not true
     subtracts no landed, whatever the reason; the decoder writes it false in the sessions of 2026-11-20 that read
     no loot windows, so their landed and items stay with the claim.
   - items: counted = `items` x counted landed / `landed`. Items follow landed so that a catch booked across a session
     seam is not added on both sides.
   - A claim without `casts` (a session a catch started) holds items of the last cast of the claim before it. It
     counts them when no Scarlet row is assigned to it or to the claim of its origin that ends within 60 s before it
     starts; otherwise it is `superseded` with that row as evidence.
   - `superseded` when every counted amount is 0, else `residual`. The evidence holds the assigned rows (count, first
     and last time, looted, items) and the difference between claimed and counted per metric.
   Another kind maps its own anchor metric here in the same way before it ships.
4. **Between characters.** A character-scope dated claim that equals a claim of another character of the same
   account in origin, kind, window, `from`, `to` and amounts is one record read from two folders (a restored backup,
   a copied folder). It counts on the character with the lowest id and is `superseded` on the others, with that claim
   as evidence. A decode or removal that adds or removes such a claim reconciles the other characters too, locked
   lowest id first.
5. **Between origins.** A fixed rank per kind (fishing: `fl` above `ff`). A claim whose window overlaps a counted or
   residual claim of a higher rank by more than 60 s is `superseded` whole. Two claims of one origin and one
   character whose windows overlap, or whose ends are equal, are one, whatever their stores: the later end wins, then
   the later start, then the store that sorts last. The others are `superseded` with its id.
6. **Lifetime.** No mapped origin has a character-scope lifetime. A `life` claim with scope `c` is `held` until an
   origin maps one and this rule is written with its measurement. The undated term is empty until then.
7. **Account scope** is `reference`. Per store, the audit compares its latest read with Scarlet's rows plus the
   counted claim amounts of its characters inside `[from, to]`, and lists a sum more than 2.5% above it as a note,
   never a violation (Mastercraft ran 1.2% below the sum of the per-character addons; a character also played on
   another PC, or with Mastercraft off, honestly exceeds it).

On the owner's fishing data this gives Scarlet's 343 casts, 295 landed and 316 items, plus 0 casts, 39 landed and 40
items from the two Fishing Log sessions before Scarlet read loot windows: 343, 334 and 356. With a Scarlet session
withheld, its claim counts whole; releasing the session moves every total by exactly the stored differences
(*measured*: the arithmetic of rule 3 run over R0's assignment of rows to the 12 sessions). Where only an addon saw a
trip, its casts include the short cancelled channels Scarlet drops: 23 of 366 (6%) on the owner's data.

### Reading

- Scarlet's fact tables and rollups do not change. A total that includes addon data is three terms, each one query:
  Scarlet, addon dated (by the UTC day of the window's end) and addon undated (lifetime). A chart with a time axis has
  no undated term.
- Rates, medians, hour of day, heat, trips, waits, lures, poles and skill-ups per cast read Scarlet's rows only, and a
  page that shows them beside a total that includes addon data says which is which.
- Leaderboards read Scarlet's rows only, until the owner decides otherwise.
- A public figure split by origin is shown only when each side passes the character threshold; a player's own pages
  always show the split.

### Checks

`php artisan addons:audit {character?}` runs these as SQL against the real database, prints per character and kind
the table that explains the total (one line per status, with claims, amounts and the top evidence), and fails on any
violation. It also runs on a schedule and logs breaches.

1. Every claim has exactly one status; claimed = counted + superseded + held + rejected + the stored differences.
2. 0 <= counted <= claimed; `superseded`, `held`, `rejected` and `reference` count 0.
3. Every `superseded` and `residual` claim names its evidence.
4. No Scarlet row is assigned to two claims of one origin.
5. No two counted or residual dated claims of one character overlap by more than 60 s across origins, or at all
   within one origin.
6. Every page total equals Scarlet + addon dated + addon undated, and every breakdown sums to it with the unknown
   bucket.
7. Scarlet's row counts and rollup sums are the same before and after each ship of this work.
8. The account notes of rule 7 are listed (a note, not a violation).
9. No text field of a claim holds a `First-Last` name other than the character's own.
10. No public bucket shows a per-origin figure from fewer characters than the threshold.
11. No dated claim is counted or residual on two characters of one account with the same origin, kind, `from`, `to`
    and amounts.

The rest is proven on a copy of the owner's data under an invented name, through the real companion and the real
upload, before any page reads a claim: importing twice changes no row, addon parts first or sessions first give the
same hash, a wiped or truncated addon file changes nothing, a withheld Scarlet session moves totals by the stored
differences, a group removed and restored gives the same hash, two installs of one account give no claim twice, and a
Scarlet-only rate is the same before and after.

## Data model

Three layers. Only the first is the truth; the other two can be rebuilt from it.

**1. Parts, stored once.** `parts(id bytea primary key, character_id, session_start, group_key, lines, raw_bytes,
body bytea)`. `body` is the part text, zstd-compressed by `scarlet-part`, kept out of line and never recompressed by
PostgreSQL. A part belongs to one character; the same id uploaded again is a no-op. `part_claims(part_id, install_id)`
holds the installs that listed each part (rows go with the part or the install).

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
| `kills` | `K`, `LM`, `GP` | combat | npc, by, and the loot rule and group size in force (decoder version 10; null without a `loot` part) |
| `deaths` | `D`, `DL`, `DR` | combat | map position, killer kind, killer creature and its id, killer level, spell, the killing blow's event and environmental type |
| `death_hits` | `D`, `DR` | combat | one hit of a death's recap, joined to its death on character, session and time: position, spell, amount, source, event, environmental type, school, health, seconds before, absorbed, resisted, blocked, flags. Came with decoder version 8 |
| `dungeon_runs` | `Z`, `E`, `B`, `D` | combat | instance, seconds, bosses, deaths, group size (the run rule of OVERVIEW.md) |
| `quest_events` | `Q` | quests | quest, accepted, turned in or abandoned, xp, copper |
| `money_changes` | `M`, `S` | economy | earned, spent, balance (transfers between own characters count as neither) |
| `loot_opens` | `LO`, `LM`, `C`, `CH`, `P` | loot | source kind and id, first read or not, loot rule, threshold, group size, what the window was read for (loot, skin, herb, ore, fish, bag or other), the item entries, the map position (decoder version 10) |
| `loot_drops` | `LO`, `O`, `C`, `CH` | loot | source, item, quantity, loot rule, group size, what the window was read for. A window has one row per item, its stacks added (decoder version 10) |
| `boss_loot` | `EL` | loot | an item someone in the uploader's group received from a boss encounter: encounter id, item, quantity, receiver (a pseudonym outside the social group) and class. The notice reaches every member of the group, so each uploader has a row and a page counts a drop once: by encounter, item and a 10 minute bucket, taking the largest count any one uploader has |
| `skill_ups` | `SK`, `SL` | professions | skill, rank |
| `crafts` | `CR`, `CB`, `RD`, `TS`, `SL`, `CS`, `G` | professions | item, quantity, recipe, profession, the recipe's difficulty, the skill before and whether it went up, the gap since the last craft of the profession. A lure or weapon enchant (a `CR` with no `CB` for the main hand item) is not a craft (decoder version 9) |
| `gathers` | `C`, `CH`, `LO`, `SL`, `F`, `G`, `P` | professions | one gathering attempt (fishing, herbalism, mining, skinning): seconds, whether the loot window came and its source, the skill before and whether it went up, the gap since the last attempt of the kind, the lure and pole of a fishing cast, whether the character was in combat, the map position (decoder version 9). A fishing cast cancelled under 2 s writes no row |
| `gather_items` | `LO`, `O`, `C`, `CH`, `SL`, `P` | professions | one item a gather gave, and the items of a caught container opened later (decoder version 9) |
| `path_points` | `P` | movement | map, x, y |
| `movement_stretches` | `W` | movement | mode (`f` running, `w` walking, `m` mounted, `s` swimming, `a` flying), yards, seconds, jumps; `jumpAt` is not kept |
| `creature_sightings` | `NS` | world | npc, level, classification, type, map, cell |
| `emotes` | `EM` | social | token, one row per use |
| `auction_prices` | `AQ` | economy | a price the auction house showed: kind (`c` commodity search, `i` item search, `b` browse row), item, copper price, quantity, listings, item level, suffix, and the realm of the session. The item page reads the lowest search price per UTC day for each ruleset and realm, and a browse row only on a day with no search; no sellers or bidders are stored |
| `camp_stays` | `A`, `C`, `D`, `AFK`, `CR`, `P` | combat | one stay by a campfire (the aura Campfire Nearby): seconds, AFK, sat and tent seconds, whether Camp Benefits came, the campfire, tent and chair the character placed, crafts, a death within 300 s after, the map position. `CP` is not read: it counts the hour-long Camp Benefits buff too and overstates the time at camp (Medalink/scarlet#2) |
| `camp_benefits` | `A`, `D`, `X` | combat | one Camp Benefits aura or benefit buff held: spell, seconds, whether it was already on at the session start, whether a death took it; on a Camp Benefits row also the experience and deaths while it was held (decoder version 7; `xp` is null without an `xp` part) |
| `quest_baselines` | `QB` | quests | one read of the quests the client says the character completed: the total and the ids. The latest read's total is `characters.quests_completed`, which the lifetime quests board ranks |
| `title_baselines` | `KT` | collections | one read of the titles the character knows: total and ids. The latest total is `characters.titles_known` |
| `explored_baselines` | `EB` | world | one read of the explored overlays of each zone map. The latest count of maps with something uncovered is `characters.zones_explored` |
| `instance_locks` | `IS` | combat | one instance or world boss lock line: reset time, bosses done, locked, extended. The character page lists the latest line of each lock whose reset is still ahead |
| `talent_changes` | `TA` | build | a point picked, refunded or swapped: node, ranks, the ordinal of the point in the build, the respec it belongs to |
| `talent_snapshots` | `TG`, `TA` | build | the build as ranks, tab points, build hash and loadout, one row per change of it |
| `talent_respecs` | `TA`, `TQ` | build | refunds that came together: points moved, cost, hash before and after |
| `talent_group_spans` | `TG` | build | the talent group that was active: one row when a session's first build is read (why `s`) and when the active group changes (why `w`); a span ends at the next row of its session or the session's end, which is where the play time per spec group comes from |
| `hardcore_deaths` | `HD` | social | the server's notice that a hardcore character died: who, the level of the dead character, the notice text. The notice reaches every uploader online, so each uploader has a row and a page counts a death once, by who (the notice text when it names nobody), the level and the minute |
| `who_results` | `WH` | social | one row of a /who result: name, level, class file, race, zone name, guild. Stored for later pages |
| `inspected_gear` | `IG` | social | the gear of a player the uploader inspected: name and the `slot:itemID:enchantID:suffixID` list. Stored for later pages |
| `battleground_matches` | `BM` | pvp | a match that ended: instance, zone name, rated, arena, the uploader's team, the winning side, duration, result, whether it affects rating |
| `battleground_scores` | `BO` | social | another player's scoreboard row, read after the match: name, faction, class file, killing blows, honorable kills, deaths, honor, damage, healing, the objective stats. `match_at` is the end of the match (the last `BM 0` before the row), so one uploader's rows of one match share it |

Game reference tables, the same for every uploader, keep the newest row per id: `recipes` and `recipe_reagents`
(`RQ`: what a recipe makes and needs) and `spells` (`SI`, `SD`: icon id, cast time, ranges, costs, passive, description).

Dictionaries, plain tables: `names(kind, id, name)` from the name lines, `items` from `II`, `zones`, `rulesets`,
`talent_trees` and `talent_nodes` (the class tree layouts of `TV` and `TD`, one per tree, client build and layout hash;
`uploads` counts the sessions that sent a layout, and the layout with the most uploads is the one drawn).
A name becomes the shown one for an id when most uploads agree on it.

The talent tables are the build group's. Removing the group deletes their rows and clears `characters.spec_tab_id`,
`talent_points` and `build_hash`, which copy the character's latest snapshot. `php artisan talents:backfill` queues a
fresh decode of every session that has a build part; it fills the talent tables from parts uploaded before the decoder
knew them, and is safe to run twice. The camp tables need no command: they came with decoder version 3, and version 7 added `duration`, `xp` and `deaths` to
`camp_benefits`, so the scheduled re-decode fills them from the stored parts. `gathers` and `gather_items`, and the
profession columns of `crafts`, came with version 9 the same way, with no command to run. Their group is `professions`,
but a gather is read from combat and loot lines too: removing `combat` or `loot` nulls the columns it fills (`in_combat`;
`looted`, `source_id`, `again`) and leaves the rows.

How the tables are built (decisions the first implementation made):

- **Keys.** `session_start` is the session key in epoch seconds (`bigint`), `at` is `timestamptz`. A character is found
  by `name_key`, its lower-case name, so two spellings are one character. A ruleset is found by `rulesets.key`, its five
  signals joined as text (`-` for a missing one). Fact tables have no foreign keys: their rows are deleted by character
  and session, and a check per inserted row would cost more than it protects.
- **`counted`.** Every fact row carries `counted`, false when its session has a plausibility flag. The ruleset rollups
  read `counted` rows only; the character rollups read all rows, so an owner sees their own flagged data, except
  `kills_daily_character`, which reads `counted` rows only so a character page's most-killed list agrees with the page's
  other figures, and `camp_stays_daily_character`, which feeds a public board. This replaces
  "a character that fails stays out of the rollups": the unit is the session, and a replaced session that no longer
  fails counts again on its next decode. The character keeps `plausibility` (`ok` or `flagged`, flagged while any of its
  sessions is) for the leaderboards.
- **Names.** `names` holds one row per candidate name of an id with `votes`, the number of sessions that reported it;
  the shown name is the one with most votes. A session adds its votes once (`sessions.names_counted`), so a second decode
  changes nothing.
- **Time per level.** A level's time spans sessions, so it cannot come from a daily rollup of `play_spans`. Ingest
  rebuilds `character_levels` (a hypertable on the time the character first stood at the level) for the character after
  each session: one row per level whose start Scarlet saw, with `seconds` at that level, `before_seconds` (the game's
  /played at the ding into it, the time to reach it), `completed` (the ding out was seen and no unrecorded play lies
  between the two dings) and the `ruleset_id` and `counted` of its first span. The level a character was first seen at
  has no row unless it is level 1 from creation. `level_time_ruleset` is a continuous aggregate over it.

### Mid-life characters

A character can start uploading at any level. Every number is either lifetime (what the game reports for the whole
life: /played, level, money, statistic totals) or observed (what Scarlet counted since it first saw the character:
kills, deaths, gold earned and spent, quests, XP by source, time in a level, talent pick order). A label never mixes the
two, and an observed number says "since <first seen date>" wherever a lifetime total could be assumed.

- **Continuity.** `MarkSessionJoins` sets `sessions.joins` (`first`, `continuous`, `gap`, `unknown`) from the client's
  /played at each session's start and end (`counter_start_ms`, `counter_end_ms`) against the end of the session before,
  with `scarlet.continuity_seconds` of slack. `gap` carries `unrecorded_ms`. A session without counters is `unknown`
  unless the wall clock between the two sessions is within the slack.
- **Complete levels.** A level counts for durations, medians and the time-to-level boards only when its ding in and its
  ding out were seen and no `gap` or `unknown` join lies between them. Decoder `L` repeats of the same level are not
  dings: `level_ups.ding` is false for them. Time to reach level X is the lifetime /played at the ding into X.
  Money rows carry `level_complete`, set by ingest from the character's rebuilt level rows: a level the character was
  first seen at, the level it is still in, and any level with unrecorded play in it are not complete, and their money
  stays out of the public gold-by-level means (`money_daily_ruleset`). The character's own gold rollup reads every
  row, like `counted`.
- **Characters.** `played_known`, `recorded_seconds`, `first_level` and `whole_life` (first session from level 1, no
  broken join) are set by `SummarizeCharacter`. `highest-without-dying` lists whole-life characters only; the other
  boards say "since" on the date a character was first recorded.
- **Reaching a level.** A character's level is the game's own number, so a character at level N has reached every
  level from 1 to N whether or not Scarlet saw the dings. "Characters that reached each level", the ruleset table's
  "Reached 60", the hardcore survival chart, the characters at the level cap and the highest level read
  `max(sessions.level_end)` per character (`ReachedLevels`), never `character_levels`. Only durations and rates need
  complete observed intervals. The "At the level cap" block (`AtLevelCap`) counts what Scarlet recorded at the cap since
  it first saw each character: play time, deaths, dungeon runs and the money rows of the character's own purse, which are
  read from `money_changes` because `money_daily_ruleset` leaves out a level that is not complete and the level a
  character is first seen at is never complete. Every figure carries the number of characters it rests on.
- **Talents.** Order and unspent-point facts use picks seen live between two seen dings. Talents held at the first
  snapshot have no order and no level.
- **Re-decode.** Each session row keeps the `decoder_version` that wrote its rows. Every ten minutes the scheduler
  runs `ingest:redecode --outdated`, which queues the next 1000 sessions with an older or no version once the
  backfill queue is empty, so production rebuilds its stored rows by itself after a decoder change. A cursor in the
  durable cache walks the sessions in key order, so a session that keeps failing cannot hold the rest back.
  `php artisan ingest:redecode` without the option queues every session regardless, then
  `php artisan levels:rebuild` recomputes joins, summaries, levels and the money rows' `level_complete` flags and
  refreshes the aggregates.
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
contributed to (5 on a public deploy, 1 in development). A ruleset view reads `counted` rows only;
`money_daily_ruleset` additionally reads `level_complete` rows only, so a character's partial level never enters the
public gold-by-level means, while `money_daily_character` reads every row. `kills_daily_character` also reads
`counted` rows only, like its ruleset twin, because the character page leaves flagged sessions out everywhere else, and
`camp_stays_daily_character` reads `counted` rows only because the board of time by the fire is public.

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
| `drops_daily`, `opens_daily` | 1 day | source, item, loot rule | drops, quantity, opens (drop rate is drops over first opens). Replaced by the two below; dropped once nothing reads them |
| `loot_windows_daily` | 1 day | source, read, loot rule, group size, zone | windows (first reads of the source), item rows, characters |
| `loot_items_daily` | 1 day | source, read, loot rule, group size, zone, item | windows that list the item, quantity, characters |
| `skills_daily_ruleset` | 1 day | skill | skill-ups, highest rank |
| `movement_daily_character`, `movement_daily_ruleset` | 1 day | mode | stretches, yards, seconds, jumps |
| `emotes_daily_character`, `emotes_daily_ruleset` | 1 day | token | uses |
| `heat_daily_ruleset`, `heat_daily_character` | 1 day | map, 100 x 100 cell | points |
| `world_heat_daily_ruleset`, `world_heat_daily_character` | 1 day | world cell (768 x 512) | points |
| `world_heat_fine_daily_ruleset`, `world_heat_fine_daily_character` | 1 day | world cell (1536 x 1024), paths only | points |
| `world_death_heat_daily_ruleset`, `world_death_heat_daily_character` | 1 day | world cell | deaths |
| `world_sighting_heat_daily_ruleset` | 1 day | npc, world cell | sightings |
| `camp_stays_hourly_ruleset` | 1 hour | level, zone, length step (seconds / 30, at most 40), campfire placed | stays, visits (stays that began in the session), seconds, the counts of the contract of `/public/camps` (earned, sat, tented, tent rests, logged out, died after, AFK, crafts, placed tent and chair), seconds as a percentile sketch of the visits |
| `camp_heat_daily_ruleset` | 1 day | map, 100 x 100 cell | visits |
| `camp_benefits_daily_ruleset` | 1 day | spell, level | gains, seconds held, lost to death, experience and the seconds it was known for, deaths |
| `camp_stays_daily_character` | 1 day | character | seconds, visits; read by the board of time by the fire |
| `gathers_hourly_ruleset` | 1 hour | kind, level, zone | attempts, looted, seconds, gap seconds, trips (rows with no gap), in combat, skill-ups, first strikes, looted seconds as a percentile sketch |
| `gathers_daily_shape_ruleset` | 1 day | kind, wait step (seconds / 2, at most 15, looted only), rank band (25) | looted, skill-ups |
| `gathers_daily_source_ruleset` | 1 day | kind, source (the loot window's object or creature) | attempts, looted |
| `gathers_daily_gear_ruleset` | 1 day | kind, lure spell, lure age in whole minutes (rounded up, at most 30), pole item | casts |
| `gathers_heat_daily_ruleset` | 1 day | kind, map, 100 x 100 cell | attempts |
| `gathers_daily_character` | 1 day | kind, character, level | attempts, looted, skill-ups, highest rank; read by the four gathering boards and the rank medians |
| `gather_items_daily_ruleset` | 1 day | kind, item, container item, zone | item rows, quantity, rank as a percentile sketch |
| `crafts_hourly_ruleset` | 1 hour | profession, level, zone | crafts, quantity made, skill-ups, trivial crafts, sittings (rows with no gap), the gap as a percentile sketch |
| `crafts_daily_shape_ruleset` | 1 day | profession, difficulty, rank band (25) | crafts, skill-ups |
| `crafts_daily_recipe_ruleset` | 1 day | profession, recipe, item | crafts, quantity made, skill-ups, rank as a percentile sketch |
| `crafts_daily_character` | 1 day | profession, character, level | crafts, skill-ups, highest rank; read by the eight crafting boards and the rank medians |

Every gathering aggregate except the by-character one carries the characters that contributed, as a hyperloglog.

Reads go through one Action per question in `app/Actions/Rollups/` (`LevellingCurve`, `TimePerLevel`, `XpSourcesByLevel`,
`DeathsByLevel`, `TopKillers`, `DeathZones`, `DungeonRuns`, `Professions`, `GoldByLevel`, `PlayTimeByWeekday`,
`PathHeatCells`, `DeathHeatCells`, `CreatureKills`, `DropRates`, `ItemSources`, `QuestTurnIns`, `TopEmotes`, `Totals`, and for the camps
`CampTotals`, `CampFires`, `CampLengths`, `CampByLevel`, `CampHours`, `CampZones`, `CampHeat`, `CampBenefitsBySpell` and
`CampBuffed`, and for the gatherings the `Gather*` reads from `GatherTotals` to `GatherSchools`). Each takes an
optional ruleset (none sums every ruleset) and leaves out any group that fewer than `config('scarlet.min_characters')`
characters contributed to; `Totals` returns null for such a total. Pages and the public API call these and never query a
fact table. The drop aggregates carry no ruleset, so `DropRates` and `ItemSources` take none. Heat cells are zone-map
cells keyed by uiMap id; the world map of "Maps" reads the `world_*` rollups.

**Camps.** `CampsDashboard` is the one read of `/public/camps` and the camps page, built from the four camp aggregates
and the existing ones; only the board reads the by-character aggregate through `BoardQuery`. A *visit* is a stay that
began in the session (`carried` false): counts are of visits, `seconds` and the AFK seconds are of every stay, and the
median is a percentile sketch over the visits. Camp Benefits `gains` count the auras gained in the session. `buffed`
sets the seconds of Camp Benefits held at each level against the scope's rate there, experience or deaths over played
seconds from `xp_daily_ruleset`, `deaths_daily_ruleset` and `play_hourly_ruleset`; the rates are read whole, since they only
enter the sum, a level nobody played is left out and the sum is scaled up to all the seconds held. A figure that counts visits or gains is
shown by the characters who had one, not by those who only carried a stay or a buff in. A scope under
the character threshold answers zero totals and no rows; a campfire tier or benefit under it counts zero.

**Gathering.** `GatheringDashboard` is the one read of `/public/professions/{slug}` for the four gathering professions
(`Profession::gatherKind()` is the one owner of slug, skill line and kind for all twelve professions with a page; a
crafting slug is answered by `CraftingDashboard`, below). It is built from the seven gathering aggregates through `Gather*` reads under
`app/Actions/Rollups/`, `Leaderboard` for the four boards (`most-fish-caught`, `most-herbs-gathered`, `most-ore-mined`,
`most-creatures-skinned`, looted attempts of public characters, from `gathers_daily_character`), and `play_hourly_ruleset`
for the time played. One figure reads the rows of the last 90 days, since no aggregate carries a median over runs: `GatherTrips` (a trip
is a run of attempts of one session chained by `gap`, from its first cast to the end of its last; a run the session
starts with after a cancelled cast counts as a trip too, so the median can run over more trips than `trips`, which counts
the rows with no gap). The medians of rank are over characters, from `gathers_daily_character`. `seconds` of fishing adds
the gaps between casts, which is the time at the water. A lure counts while its age is at most `GatherKind::LURE_SECONDS`,
which is read when the answer is built, so the aggregate keeps whole minutes. `container_catches` counts the item rows
that were later opened as a container in the scope, so a cast that gave two of them counts twice. `heat` counts attempts
as `visits`, like the camps' heat. A scope under the character threshold answers zero totals and no rows.

**Crafting.** `CraftingDashboard` answers `/public/professions/{slug}` for the eight crafting professions, over the
counted `crafts` rows whose `profession_id` is the trade's skill line (rows with no profession belong to no page). It is
built from the four crafting aggregates through `Craft*` reads under `app/Actions/Rollups/`, `RecipeFacts` for each
recipe's reagents, `Leaderboard` for the profession's board of crafts (`most-alchemy-crafts` to `most-first-aid-crafts`,
from `crafts_daily_character`) and `play_hourly_ruleset` for the characters in the scope. The medians of rank, the
characters at the cap and the `by_level` table are the gathering ones: `RankSummary` and `RankByLevel` read either
profession's by-character aggregate, and `ScopePlay` reads the characters with play. A highest rank is `skill` plus
the rise that followed the craft. One figure reads the rows of the last 90 days, `CraftSittings`: a sitting is a run of
crafts of one profession in one session in which each follows the one before within 120 seconds (`sitting_gap`, null on
the first), from its first craft to its last. `recipes` lists the twelve with the most crafts and the item their crafts
made most often, `reagents` is each uploaded recipe's reagents times its crafts. A trade nobody has recorded, or one under
the character threshold, answers zero totals, `characters` null and empty lists.

**A player's own numbers.** The camps page and the twelve profession pages have a twin over only the characters of the
signed-in account (`/account/camps`, `/account/professions/{slug}`, Pages). It is the same dashboards, reads, views and
scenes with a different scope, so no read, view model or view exists twice. A scope is a ruleset id, a list of them or
none (`PageScope::$ids`, as above), or a `CharacterScope`: character ids within those rulesets. The ids come only from the
signed-in account (`$account->characters()`), never from the request, and a dashboard's `handle()` takes them as its last
argument, where `null` is the public answer and an empty list is an account with no characters, which answers the empty
state and never the public numbers.

`RollupRead` is the one place that turns a scope into a source and a threshold. For a ruleset scope it reads the
aggregate. For a `CharacterScope` it reads the aggregate's own definition, kept by TimescaleDB
(`timescaledb_information.continuous_aggregates.view_definition`), with the fact table it names narrowed to
`character_id = any(...)`, as a subquery under the aggregate's name. The definition is kept for 5 minutes (the catalog read
was 5 ms for each of the 7 aggregates of a gathering page, 28 ms of 94) and `RollupRead` is bound per request. The columns, the sketches and the numbers are the
aggregate's own, over only those characters' rows, and the plan uses the `(character_id, at)` index of the fact table. A
definition that does not name its fact table exactly once after `FROM` is refused with an exception. The reads that go to a
fact table (`GatherTrips`, `CraftSittings`) narrow it through `RollupRead::table()` the same way. No aggregate, migration or
copy of an aggregate's SQL is added for this.

A character scope has no public threshold: a player's own rows are shown from one character, whatever
`SCARLET_MIN_CHARACTERS` says, and every read takes its threshold from `RollupRead::threshold()`. The own board ranks the
account's own characters, private ones included, from the same by-character aggregates. Played time and the scope's
character count follow the scope, so "a played hour" is the player's own hour; `ScopePlay` reads both in one statement,
for a player's own from the counted `play_spans` rows, since a sum and a count do not need the hourly grouping and its
sketches. A player's own pages read `counted` rows only, like the public ones, so their numbers sit beside everyone's; the
character rollups read flagged rows too.

A player's own numbers read the rows as they are now, while everyone's are refreshed aggregates up to an hour behind, so the
two can differ for the current hour or day. `/account/professions` picks its profession from the refreshed by-character
aggregates, so a character that gathered this hour does not move it yet. A character scope skips the threshold's
`having`, which keeps the sketch of characters out of every read that does not select it. Measured on one invented
character with 100,000 gathers and 1,000,000 play spans over a year, the own Fishing page takes 1.0 s with the read cache
cleared (1.6 s before the play reads were merged) and 0.12 s with it warm; with 1,000,000 gathers and 2,000,000 spans it
takes 5.1 s cold and 0.16 s warm. A page reads each answer for a minute, then stale for five.

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

A character with neither answer is "Not identified". A ruleset is its kind and nothing else: `rulesets` has one row
for each kind (key and slug `normal`, `pvp`, `roleplay`, `roleplay-pvp`, `hardcore`) and one for characters whose kind
is not known yet (`unidentified`). Every session of a character is filed under the row of its kind, since the kind is
permanent; a session with the hardcore flag is filed under Hardcore, which is the only signal of a session that names a
ruleset by itself (`RulesetKind::of()`). When a character's kind becomes known, `RefileSessions` decodes each of its
sessions again (the ingest files it under the right row and refreshes the rollups); `rulesets:refile` does the same by
hand, and daily.

The other signals a session reports are data of the session, not of its ruleset. They stay in the session's `extra`
(`gr_preset`, `gr_hardcore`, `war_mode`, `game_mode`, `mode_record`), and `sessions.xp_off` carries experience gain:

| Signal | Line | Values |
| --- | --- | --- |
| Experience preset | `GR` preset | the client's preset number (0 Classic, 1 Enhanced) |
| Hardcore | `GR` hardcore | on, off |
| War mode | `PB` warModeActive | on, off, unknown |
| Experience gain | `XD` | on, off (`sessions.xp_off`) |
| Game mode | `GA` | the client's mode number |
| Game mode record | `RU` record | the record id the realm picker switches by |

Class, race and faction are filters on a page, not part of the ruleset. The `S` line's server fields are empty on
Forever and are not used; `RU` carries the server number from the character's GUID, which becomes
`characters.home_realm_id`. The loot rule (`LM`) is a property of a drop, not of a character. A row's `war_mode` is
its kind's (`RulesetKind::warMode()`: Normal and Roleplay `off`, PvP and Roleplay PvP `on`, Hardcore `unknown`), and
the PvE and PvP server filters of the pages read it, so "PvE" shows Normal and Roleplay and "PvP" shows PvP and
Roleplay PvP; Not identified shows only under "PvE and PvP".

**Rows from before kinds.** Until 2026-11-22 a ruleset was a combination of every signal a session reported, so each
addon version that reported more made a new row and one Normal character ended up under five. Those rows stay in
`rulesets` with `merged_into` naming the row that took their sessions, and each session keeps the row it had in
`sessions.original_ruleset_id`. `rulesets:merge` moves the sessions and every fact row of them to the kind's row, then
refreshes every rollup and checks that row counts and each character's hours, deaths and experience, and the rollup
sums, did not move. It is safe to run twice. `rulesets:merge --undo` puts the sessions back; that is only sound
together with the code from before, since a session decoded again is filed under its kind. An old address (a slug of
signals with or without the kind token, or a row id) opens the page of the row its ruleset was merged into, and
`/rulesets/{slug}` redirects there.

**Experience off.** A character that played with experience off (`sessions.xp_off`) gains nothing in that time, so its
played time would stretch every level after it. `character_levels.xp_off_reach` is true when such a session began at or
before a level's start, and `xp_off_spent` when one began before the level's end. The level-time rollup (levelling
curve, time per level, median to 60), the fastest-to boards and the talent outcomes leave those rows out of the figure
they would skew. Everything else counts the character's play as usual.

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

**Limits.** Registration: 5 installs per address per hour. Upload: 4 MiB per request, 64 MiB decompressed, 200 MiB per
install per day, 50 characters per install, and the zstd window is capped so a small body cannot expand without
bound. Requests are rate limited per install and per address with Laravel's limiter; an IPv6 address counts by its
/64. The public read API allows 60 requests a minute per address, or 600 per account with a key, caps bad keys at 10 a
minute per address, keeps each answer 30 seconds at the origin and cancels a query after 10 seconds (docs/API.md).
Revoking an install on the account page stops its key at once.

**Public pages.** The pages run the same reads as the public API, so a client looping over them would reach the
database through php-fpm's few workers. They carry their own protection: 240 requests a minute per address bucket
(`App\Support\ClientAddress::bucket` — an IPv4 address as it is, an IPv6 address by its /64), counted by Laravel's
limiter on every route of `routes/pages.php`; `statement_timeout` 10 s on every statement a page request runs; and
the answer to an anonymous visitor kept in the cache for the edge TTL under a key that keeps only the query
parameters the pages read, so a junk parameter such as `?x=1` cannot bypass the edge cache and reach the origin.

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
  `/public/camps`, `/public/professions/{slug}`, `/public/search?q=`, `/public/characters/{name}` and its series, `/public/creatures/{id}`, `/public/items/{id}`,
  `/public/spells/{id}`, `/public/encounters/{id}`, `/public/zones/{id}`, `/public/quests/{id}`, `/public/map/heat`. `Access-Control-Allow-Origin: *`,
  `Cache-Control: public, max-age=300` with an `ETag`, cursor pagination (`?cursor=`, `next_cursor` in the answer),
  60 requests a minute per IP, 600 with a free API key (a Sanctum token made on the account page, sent as a bearer
  token). The reference is the `/developers` page, written from the same route table.

The signed-in pages read the same Actions as the public API, with the owner's private data added.

## Pages

Every page keeps the look of the Scarlet site: the dark survey sheet, scarlet, Sofia Sans Extra Condensed for
headings, Sofia Sans for text, Martian Mono for numbers, hairlines, large figures and the map style. Comps are in
`docs/comps/`; a page is built from its comp and reviewed against it. The art and game layer below came after the
comps and is specified here, not in them.

| Path | Page |
| --- | --- |
| `/` | The landing page, carried over as it is |
| `/demo` | The demo character, carried over |
| `/stats` | Global dashboard: characters and hours, levelling curve, time per level, XP sources by level, deaths by level and top killers, dungeons, professions, gold by level, play-time heatmap by hour and weekday |
| `/camps` | Public. How players use the campsite (the campfire, tent and chair that WoW Forever players place and sit by): visits, characters, hours by the fire, the median visit and visits a played hour; the Camp Benefits players leave with and how many a death took; what gets set up; how long a visit lasts in six ranges around the 60 seconds Camp Benefits need; visits and tent rests a played hour by level; when players camp (weekday by hour, in the viewer's time zone); play with Camp Benefits (experience and deaths an hour against the same levels across all play); where players camp (the busiest zone map); zones with the most camps; what players do by the fire; how a visit ends; and the characters with the most time by the fire. `ruleset` and `server` as on `/stats`. The head draws the game's campfire, tent and chair (below) |
| `/professions/{slug}` | Public. One of the twelve professions that have a page: the gathering ones, `fishing`, `herbalism`, `mining` and `skinning`, and the crafting ones, `alchemy`, `blacksmithing`, `enchanting`, `engineering`, `leatherworking`, `tailoring`, `cooking` and `first-aid`; any other slug, `lockpicking` and `poisons` included, is 404. `Profession::withPages()` lists the twelve in nav order and `Profession::scene()` names each one's scene. **Gathering.** The head is the profession's scene (Art and game layer) with three callouts on its props, the twelve professions (the nav lists all of them, gathering first, and wraps at 390 wide), the credit line and a strip of six figures: attempts that give something, the median time of an attempt, looted attempts a played hour, characters who gather, median skill and characters at 300. Modules, each left out when its list is empty: what comes up (item, share of looted attempts, median skill, the zone it came up most in); how an attempt ends (gave, did not, in combat, and for Mining first against later strikes on a vein) with, for Fishing, what is on the pole; how long a bite takes (left out when every attempt is in one step); attempts for a skill point by skill; median skill by character level; when players gather (weekday by hour, in the viewer's time zone); a trip (median length and attempts over the last 90 days, trips of all time); where players gather (the busiest zone map); zones with the most looted attempts; what the clams, crates and trunks hold (how many times each item was found) and the poles in hand (Fishing); what gets skinned (Skinning); and the characters with the most looted attempts. The words change with the kind (cast, pick, strike, skin) and live in `GatheringWords`. A callout whose name or number the answer lacks is left out, a callout that lacks only its share is kept without it, and a scope under the character threshold shows the head, dashes and a note. A chart that is the only one in its row is drawn across the sheet, and a table of items (what comes up, the clams, crates and trunks, the poles) shows the item's own icon, with an empty square for a row that has none only while another row has one (Item icons). `ruleset` and `server` as on `/stats`. Reads the same `GatheringDashboard` as `/public/professions/{slug}`. **Crafting.** The same head, nav, scope filters and empty state, with three callouts keyed `station`, `work` and `stock` on every crafting scene: the most made recipe with its share of crafts; a sitting, with the median crafts in a row and the median seconds apart; and the most used material with its share of materials (shown only while the list is shorter than ten, since a full list may leave materials out), or, while no recipe's reagents are uploaded, a skill point with the crafts one takes and the share of crafts that are trivial. A callout without a name or text is left out, and one without a share is kept without it. The strip of six figures: crafts that give a skill point, recipes crafted, items made, characters who craft (of the characters with play in the scope), median skill and the characters at 300 (a count, since the characters with a rank on record are not the characters who crafted). Modules, each left out when its list is empty and none for a map, since a craft has no position: what gets made (recipe with its reagents under it, what it makes with its icon, crafts, items made, characters, median skill); how hard the recipe was when it was made (the game's Optimal, Medium, Easy and Trivial, with crafts and the share of each that gave a skill point); crafts for a skill point by skill; median skill by character level; when players craft; a sitting (median length and median crafts of the last 90 days, sittings and median seconds between crafts of all time); what the crafts used (with icons); zones with the most crafts; and the characters with the most crafts. The words live in `CraftingPage`, the callouts in `CraftingCallouts`, and everything the two page classes share (the rows of the sheet, the lone-chart width, the week, the zones and the board) in `ProfessionPage` and `ProfessionCharts`. Reads `CraftingDashboard` |
| `/rulesets`, `/rulesets/{slug}` | The same dashboard per ruleset, and rulesets side by side |
| `/hardcore-deaths` | Public. Hardcore deaths by the level the character died at and by day (the last 90 days). Rows are the server's death notices (`hardcore_deaths`); a death counts once however many uploaders saw it, by name (the notice text when it names none), level and minute. Rows of flagged sessions are left out. A notice names the dead character, never a page's owner, so the page shows counts only and no names |
| `/leaderboards` | Fastest to a level, fewest deaths, most quests, most quests completed, hardcore survivors, per ruleset |
| `/search` | Characters that are public, creatures, items, zones, quests |
| `/c/{name}` | A character: overview, levels, sessions, deaths, dungeons, map paths, talents (the latest build of each talent group drawn on its tree, or listed by tab while no layout is stored; the order the points were taken in; respecs; talent facts: characters with the same build at the same level, respec count and gold, the least picked talent among the class and spec, the level of the first build with 31 points in one tree, the longest and median wait to spend a point, and picks refunded within ten minutes, each with its n). A Lifetime chapter, shown when a read is stored, gives the client's own totals of quests completed, titles known and zones explored (the latest `QB`, `KT` and `EB`) and the instance locks that still hold with their reset time (`IS`); a character without those lines shows no chapter. The hero map redraws the walked cells in the order they were first walked (a day at a time, from the daily heat aggregate), with the Level figure counting up and each death appearing at its day; a Replay button runs it again, and a viewer who prefers reduced motion gets the finished map. Skills lead with the profession ranks over played time, placed from the hours played; play time is the last 21 days and the hour of the week, drawn in the viewer's time zone from the hourly aggregate and in UTC without script. Public when its owner says so; the owner sees the private parts too |
| `/c/{name}/battlegrounds` | The scoreboards of a character's ten latest battlegrounds that have `BO` rows (`battleground_scores`): every player the client read, by faction, with killing blows, honorable kills, deaths, honor, damage and healing, under the zone and the result of the match when the `pvp` group holds its `BM` lines (`battleground_matches`). A match is one uploader's rows that share `match_at`; two uploaders of one match each have their own. Visible exactly when the character's page is. Other players' names follow the privacy rule: the owner sees them, a visitor sees a name (linked to its page) only when that player's own character is public, and every other row says "a player". The character page links it when the character has a match with a scoreboard |
| `/talents`, `/talents/{class}/{spec}` | Public. Every spec with the characters that play it, and one spec: pick rate by node (a heat on the real tree when a layout is stored, a table always), the most common order of points, builds by level bracket, and builds and outcomes (the ten most held builds with the level-ups, median time to level, deaths and dungeon runs that happened while each was held, from one snapshot to the next; `?level=10-19` to `60`; `ruleset` and `server` as on `/stats`). Each character counts once, by its latest counted build read inside the bracket, and every figure prints its n. `/stats` carries a Talents block with the most played specs |
| `/creatures/{id}`, `/items/{id}`, `/zones/{id}`, `/quests/{id}` | Result pages: kills and killers, drop rates by loot rule, where it is, levels. An item page also lists the boss encounters that dropped it (`boss_loot`) and the recipes that make or use it (`recipes`, `recipe_reagents`) |
| `/loot` | Planned, public (docs/LOOT.md, phase 3). The loot index: loot windows, corpses looted, sources, items and characters, the sources and items looted most with their rate and range, the zones and the loot rules and group sizes seen. Filters are query parameters. Fishing is one link to `/professions/fishing` |
| `/objects/{id}` | Planned, public (docs/LOOT.md, phase 3). What a chest, herb, vein or other object gives, on the creature template without kills and deaths: the drops, the drop rate by loot rule, where it was opened |
| `/loot/groups` | Planned, public (docs/LOOT.md, phase 4). Loot rules and group size: what a corpse held by loot rule (sources seen under both rules only), what the looter kept, kills an hour by group size, and later the share of the group's loot, rolls and group composition |
| `/spells/{id}` | Public. What the client says a spell is (`spells`: cast time, range, cost, passive, description, icon id) and, for a recipe, what it makes and needs. A spell with a name, facts or a recipe has a page. The talent tables on a character's talents page and on a spec page link each talent's spell here; the drawn tree does not link yet |
| `/encounters/{id}` | Public. The items a boss encounter dropped, from `boss_loot`. A drop is counted once per uploader group: rows of one encounter and item within 10 minutes are one drop, and the uploader who saw most of them gives the count. An encounter has no name in the data, so the page says `Encounter <id>` |
| `/map` | The world map: one seamless map, zoomable down to a zone, with heat from paths, deaths and kills (see Maps) |
| `/developers` | The public API reference |
| `/download` | Public. The newest build of the Windows app: the installer as the main button, the addon zip, `SHA256SUMS.txt` and the GitHub releases page. The build is read server side from the GitHub releases of `Medalink/scarlet-app` (the newest stable release with an installer, else the newest pre-release; drafts and `beta-channel` are skipped) and cached for 10 minutes, so a page view never reaches GitHub. When GitHub does not answer, the last good answer is shown, and before there is one a link to the releases page. The landing page and the header link to it |
| `/release-notes` | Public. The running build (version, build number, commit, lines, files, and the lines added and removed by the last commit, this build and all time), a rail of released versions, and one release with its changes grouped by area. Filters by New, Improved or Fixed are query parameters (`?v=0.1.0&only=fixed`), so the page needs no script. The footer of every page shows `v<version>.<build>` and links here |
| `/login`, `/link` | Sign in, link the app |
| `/account` | Characters and their public switches, installs and revoking them, recent uploads and when each install last contacted the site, sharing groups, removals, API keys |
| `/account/professions`, `/account/professions/{slug}` | Private, signed in only. The twelve profession pages over only the account's characters (A player's own numbers, Rollups): the same view, scene, modules, filters and `ProfessionController` as `/professions/{slug}`, with the head saying whose numbers they are ("1 of your 3 characters") and linking everyone's page, the trades nav staying under `/account`, "you" for "players" in the module headings, "Your characters by catches" for the board, and no figure of characters who work the profession, since the head says it. `noindex`, no canonical link, no meta description, never edge cached (it is not in `routes/pages.php`, and `cache.headers:private;no_store` on the route sends `max-age=0, must-revalidate, no-cache, no-store, private`), not in the sitemap or in search. A route is a player's own when it is declared with `->defaults(ScopeRequest::OWN, true)`, which `ScopeRequest::ownCharacters()` reads; no controller tests a route name. An account with no character, or none with rows, gets the head ("0 of your 3 characters", or "No play recorded for your characters yet") and one line that Scarlet records this once a character of theirs has recorded the profession's attempts. `/account/professions` goes to the profession with the most attempts or crafts, Fishing when there are none, and carries `?ruleset=` and `?server=`. `/account` and `/account/characters` link it |
| `/account/camps` | Private, signed in only. The camps page over only the account's characters, through the same `CampsController`, view and scene as `/camps`, declared on its route with `->defaults(ScopeRequest::OWN, true)` and read through `ScopeRequest::ownCharacters()`, with the title "Your camps", no meta description, the head saying "1 of your 3 characters" (the characters with camp stays of the account's played characters, from `ScopePlay`; "No play recorded for your characters yet" before any has played), linking everyone's camps, "you" for "players" in the module headings, "Your characters by time by the fire" for the board, and the trades nav of the own profession pages with Camps beside the twelve. Private in the same way as `/account/professions`: no index, no canonical, `Cache-Control: private, no-store`. The answer carries `scope_characters` only for a player's own, so the public API answer is unchanged. With no character, or none with a camp stay, one line says Scarlet records this once a character of yours has camped. `/account` and `/account/characters` link it |
| `/account/characters` | Private, signed in only. The account's characters side by side: totals, a table of the characters, play time by day stacked by character (last 30 days), gold with copper moved between your own characters kept apart, time two characters were online together (sessions overlapping by 60 s or more), time in each level against the median of the character's ruleset, and profession ranks. Alts exist only here and in the owner bar of `/c/{name}`, which links the account's other characters. No public page, board, rollup or API response groups, dedupes or weights by account: each character is its own player there, and `characters.account_id` and `first_install_id` are hidden from the model's serialization |

A dungeon is `/zones/{id}` with the negative instance id (`/zones/-90`). Its page has a Map section with the dungeon's floor maps, one floor at a time, while they are on the `maps` disk (Maps, Dungeon maps).

### Search engines and link previews

Every page's head carries a title ending in ` | Scarlet` (pages that name themselves, like the landing page and the
sign-in page, leave it off), a meta description, a canonical link, and Open Graph and Twitter tags with one share
image, `public/share.png`, 1200 by 630. A link shared in Discord, Slack, X, Facebook, iMessage or WhatsApp shows the
page's title, description and that image. The landing page also carries a `WebSite` JSON-LD block.

Search results, the sign-in page, the account pages, the link page and a private character seen by its owner carry
`<meta name="robots" content="noindex">` and no canonical link. Every other page names itself as canonical: the
canonical is the page's path with the query string dropped, so filter variants (`?server=`, `?class=`, `?cursor=`,
`?rule=`, `?level=`, `?layer=`, `?sessions=`) point at the unfiltered page. `/stats?ruleset=x` names `/rulesets/x`,
and a character page names the character's stored spelling (`/c/Name-Surname`).

`/sitemap.xml` is a sitemap index. It lists `/sitemaps/pages.xml`, which holds the fixed pages and a ruleset, board
and talent spec page for each that exists, and one file per 25,000 rows for each kind: public characters
(`/c/{name}`, with `lastmod` from `last_seen_at`), creatures, items, zones and quests. `robots.txt` disallows
`/auth/`, `/account` and `/link`, and names `https://scarletweb.io/sitemap.xml`.

The JSON answers the pages fetch (`/api/v1/public/*` and a character's heat endpoint) carry
`X-Robots-Tag: noindex` instead of a robots.txt rule, so a crawler following a URL into them does not index them.

`tools/share-image/build` draws the share image and the touch icon from `share.html` and `icon.html`.
`node e2e/seo.mjs <base URL>` asks a running site for all of the above and prints what is missing.

### Art and game layer

The stats pages show the same numbers as art and as a game (owner, 2026-11-21: "more fun, more gamified and more
visual", "better art and better backgrounds"). The look is still the survey sheet; this layer adds what a survey
sheet has and the pages lacked. `resources/css/game.css` holds it and loads after `app.css` on every app page.

- **Contour sheet.** `tools/art/contours.mjs` draws `public/art/contours.svg`: isolines of a seeded noise field at the
  sheet's two line alphas, every fifth line heavier, crosses on the summits. The same seed writes the same bytes. It
  sits behind the head of every app page (`body.art`) under a scarlet glow and fades into the plain sheet, and it
  fills a `.plate` band and the podium plinths.
- **The map as the hero.** `/stats` and `/rulesets/{slug}` open on the world map with path heat behind the title
  (`.pg-hero`), the way a zone and a character page do. The map there is decoration: it takes no pointer and is
  hidden from assistive technology. The interactive map stays in "Where characters go".
- **Icons.** `<x-icon name>` is one authored set on a 24-unit grid with one round stroke, coloured by its text.
  No emoji and no icon font.
- **Metals and class colours.** Rank and tier use copper, silver, gold, scarlet and, above scarlet, legend: a scarlet
  seal ringed in gold. A character's
  name carries its class in the game's class colour (`data-class` sets `--class`). Horde and Alliance have one
  colour each. Colour never carries a fact alone: a tier is also named, a class also written.
- **Motion.** The tally counts up once when a page opens. Bars on a rule, rank tracks, plinths and flags grow in as
  they scroll into view, with CSS view timelines only, so a browser without them and a viewer who prefers reduced
  motion get the finished page.

- **The camp scene.** `/camps` opens on the game's own Basic Campfire, Camp Tent and Camp Chair drawn in three.js
  through the scene module of the profession pages (below; `scene/scenes/camp.js` and the `camp` setting): the models'
  geometry and textures, their flame emitter and their two lights. The campfire model's spark emitter runs but is drawn
  with front faces only, which hides it, as the head has always looked (`cull` in the scene file). The smoke and the
  ground are drawn by the page, and the line under the head says so. Three callouts on the models carry the share of visits that sit down
  and that use a tent, and the share of Camp Benefits that came with the Camp Chair buff. The scene loads only when
  the head is on screen and the browser has WebGL, stops drawing while the head is off screen or the tab is hidden,
  and draws one still frame under reduced motion (a drag still turns it). When the art is not on the disk the head is a
  plain page head with no stage; when WebGL or the art fails after the page is drawn, the stage keeps its height and
  lists the three callouts. The art is Blizzard's: `tools/scene-art` (`camp`) writes it to the `maps` disk under `camp/`
  (`camp.json`, `tex/<file id>.png`, `icons/<spell id>.png`), `/scene-art/camp/*` serves it with the cache headers of the
  map tiles, `bin/deploy maps` carries it to the server, and no model, texture or icon is committed. The benefit icons in
  the table come from the same folder and are empty squares without it.
- **Item icons** (owner, 2026-11-22). Every item on a profession page shows the game's icon: what comes up, the clams, crates
  and trunks and the poles on a gathering page, and what a recipe makes and the materials the crafts used on a crafting page.
  30,347 items of build 1.60.1.71037 share 3,376 icon files, so the art is stored once per file, not per item or per scene:
  `tools/scene-art/items.mjs` reads the client's `Item` table and writes `items/items.json` (item id to icon file id) and
  `items/icons/<file id>.png` to the `maps` disk. `App\Support\ItemIcons` owns the folder: a page asks for the icons of its
  rows in one cache read, each answer (`0` for none) lives five minutes, and the 30 thousand entry map is read from the disk
  only while an answer has expired. `/item-icons/<file id>.png` serves a file with the scene art's cache headers, and its
  address carries `?v=` with the time `items.json` was written. The image has its width and height, so a row does not move
  when it loads. Without the folder the tables are as they were, with no placeholders. The art is Blizzard's and is never
  committed.
- **Profession scenes** (owner, 2026-11-22: "create a page like this for every profession and gathering"). Each
  profession page opens on a head built like the camp's, drawn from the game's own models. The code is general:
  `resources/js/scene.js` is the page script (WebGL check, load when the head is on screen, callout placing, the
  `data-state="off"` list) and loads one scene's spec and `resources/js/scene/mount.js` as chunks of their own. A scene is
  a data file in `resources/js/scene/scenes/` (`fishing.js` is the first; `camp.js` is the camps page's): the props with where each stands, how each
  model's emitters are tuned, the point each callout stands on, the camera, fog and bloom, and a `setting`, which is
  what the page draws itself. There are three settings. `water` is still water with the moon's glitter and a low bank
  under moonlight (Fishing). `ground` is open ground under one low light, with any number of small warm lights that
  flicker, such as a forge or a lantern (Herbalism, a meadow at first light; Mining, a mine floor lit by a forge;
  Skinning, tanning racks at dusk; and the eight crafting scenes, each a workshop with its station, where the props a
  callout stands on are keyed `station`, `work` and `stock` in every one). Both draw their earth with `settings/land.js`.
  `camp` is the camps page: flat packed earth under a moon, the smoke column (`settings/smoke.js`), and a fire the model
  lights itself: the campfire model's own two point lights and their flicker track, the glow of its camera-facing flame
  batches (`model.js` tags a batch on a bone that faces the camera `userData.billboard` and the setting turns it), its
  blend 7 core and the bloom all follow that one track. A scene that needs another adds a
  module to `scene/settings/` and names it in the spec; a setting may also define `attach(placed)`, called once with the
  placed props, and `warm(dt)`, called with each warm-up step, and its `step` gets the camera and the frame's `dt`. A prop's `emit` may name `all` in place of an index. A
  prop's `emit` lists the model's emitters to draw by index, each with its own rate, size and brightness (an emitter the
  spec leaves out is not drawn). The core draws one facing the camera, stretched along its velocity (`streak`, or by
  the model's tail) or flat on the model's ground plane for water rings (flag `0x1000`), additive and alpha-blended
  alike, and `bite()` ducks a floating prop and fires its
  model's splash emitters; the Fishing scene does that on its own every 7 to 12 seconds. The head markup is the camp's
  under `.scene-head` and the shared `.callouts`, `.callout` and `.credit` rules (`resources/views/pages/profession.blade.php`,
  and `design/scene.blade.php` over invented facts), the stage's label and the credit line are `App\Support\Scene::label()` and `credit()`, so each text exists once, and the stage element carries
  `data-scene`, `data-models` and `data-texture`. The art lives on the `maps` disk under the scene's name
  (`fishing/fishing.json`, `tex/<file id>.png`), is written by `tools/scene-art` and is served by
  `/scene-art/{scene}/models.json` and `/tex/{id}.png` with the camp art's cache headers and `?v=`
  rule; `App\Support\Scene` names the scenes and `App\Support\SceneArt` owns the folder. `/design/scenes/{scene}`
  (local environment only) shows a scene's head over invented facts. The scene builds with three 0.187, which has dropped the soft shadow filter the approved prototype was tuned on: shadows
  use the plain filter.

What the layer adds to each page:

| Page | Addition |
| --- | --- |
| `/stats`, `/rulesets/{slug}` | The hero and a tally of seven figures with glyphs (characters, highest level, hours, kills, deaths, quests, dungeon runs). **Records across every character**: the deadliest creature, where most characters die, the most dangerous level, the slowest level, the busiest hour, the most run dungeon, the most trained profession (professions only, not weapon skills), the most used emote across characters and the share of experience from quests at the highest level, each from a figure the page already reads, each left out when its figure is missing. One death is not a record: the creature and the place need two. **The next round numbers**: kills, hours, quests, deaths, dungeon runs, jumps and gold earned read off a rule whose right end is the next of 10, 25, 50, 100, 250, 500 and so on. **The climb**: the median played time to each level as a ridge from level 1 to the cap, with a flag at each level where characters stand now (the published reach counts, level by level: reached L less reached L + 1) and a dotted line from the end of the ridge to the cap. The dotted line is not data and says so; a flag at a level past the ridge stands on it. A flag is one level's own count, so one under the character threshold is not drawn. The climb is left out until the curve has two levels. **Classes played** and **Horde and Alliance**: characters by class and by faction (`Rollups\Roster`), a class under the character threshold left out. The modules sit in chapters: Levelling, Danger, Dungeons and skills, Who plays, Gold and time, The world. The tally replaces the old figures row; the count at the level cap stays in the level cap table |
| `/leaderboards` | The hall: every board with the character that leads it now and how many characters are on it, for the chosen ruleset and server. It replaces the redirect to one board, and the board picker's first choice, "All boards", leads back to it |
| `/leaderboards/{board}` | The first three rows of the first page stand on a podium of gold, silver and copper plinths; the table lists the rest, and later pages are the table alone. New boards: `most-kills`, creatures killed, and `most-jumps`, jumps, both counted from the first recorded session, and `most-time-by-the-fire`, seconds near a campfire (`camp_stays_daily_character`, counted stays), and `most-fish-caught`, `most-herbs-gathered`, `most-ore-mined` and `most-creatures-skinned`, looted gathering attempts (`gathers_daily_character`, counted rows), and one board of crafts for each crafting profession, `most-alchemy-crafts` to `most-first-aid-crafts` (`crafts_daily_character`, counted rows) |
| `/c/{name}` | The head carries the class colour on its dot, a glyph on each figure and a rule from level 1 to the cap with the character's level marked. Record tiles carry glyphs. **Standings**: the character's place on every board it is on, among public characters of every ruleset, with gold, silver and copper for the first three places, each linking to its board; shown for a public character only, since a board lists no other. `GET /public/characters/{name}` carries the same list as `standings`. Records tiles for **Jumps** and the **Favourite emote**, and a bar of **distance by mode** (running, walking, mounted, swimming, flying, in yards): each shown only when the character has rows for it, so an owner who stopped sharing `movement` or `social` loses nothing. **Feats**, 75 feats of five tiers each (below). The page shows the six rarest the character has earned in two rows of three, and "View all" opens a dialog with every feat: those earned first, then those not yet earned. **Pinned feats**: the owner pins up to three feats (`characters.pinned_feats`, a list of feat keys in pin order, limit `Character::MAX_PINNED_FEATS`) with a toggle on each card in the dialog, whose header shows "2 of 3 pinned". Pinned feats show for every viewer in a row of their own above the six, in pin order, in the same three-column grid. A pinned feat is not also one of the six: the next feat in the order takes its place. In the dialog a pinned feat stays in its group. A fourth pin is refused until one is unpinned. A pinned feat with no row for the viewer (a gold feat while the economy is hidden, or a feat not stored) is skipped. The toggles are a Livewire component for the owner only (`?as=visitor` shows none); visitors get plain HTML with no Livewire. The dialog stays open at its scroll position while a pin saves. A visitor's page is edge-cached for a short time and the API answer for 30 seconds, so a visitor may see the old pins until they expire. The summary counts feats (`{held} of {total} feats`) and, by the tier each holds now, how many are legend, scarlet, gold, silver and copper. A feat not yet earned shows what copper takes; one with a tier shows what the next takes, and a track of five segments shows how far up it is. The order is rarity: the fewest characters holding the tier this character holds, ties by the higher tier, then the order of the table below; the feats not yet earned follow, closest to copper first. Rarity orders the list and is never shown or published. The gold feats (Earner, Spender, Tycoon, Windfall) are listed only when the economy is shown. `GET /public/characters/{name}` carries the same list as `feats`, each with `pinned`; a pinned feat keeps its place in that list |

A feat counts what the character shares, and the gold feats follow the economy switch. An observed count
(DESIGN "Mid-life characters") starts when Scarlet first saw the character, and the module says "since" that date
for a character not recorded from level 1. A value is rounded down before its tier is read (whole hours, one
decimal of a world width), so a feat never shows a tier its number has not reached. A share on the track is rounded
down too, so a segment is full only when its tier is held. Unbroken is also left out when the first death has no
level.

A feat is **new** to its owner when the tier its stored value holds is above `character_feats.seen_tier`, the highest
tier of that feat the owner has been shown (smallint, default 0). A `seen_tier` of 0 means the feat was earned for the
first time; above 0 it was upgraded. A tier that falls and climbs back is not new again, since `seen_tier` never goes
down. The migration that added the column set every existing row to the tier its value held, so nothing earned before
the deploy is announced; a row inserted later starts at 0, so a new character's first feats are. The feat refresh
(`StoreCharacterFeats`) never writes `seen_tier`. Only the owner reads it: the owner's feat wall (`ShowOwnedFeats`, as
`new`, gold feats included) and the app's signed `GET /installs/me/feats` (docs/API.md). `MarkFeatsSeen` raises
`seen_tier` to the tier shown, never above the tier the value holds now, and is safe to repeat and to run at once;
`POST /installs/me/feats/seen` and `MarkOwnedFeatsSeen` call it. `GET /public/characters/{name}` carries neither
`seen_tier` nor `new`.

**Feat toasts.** The signed-in owner of a character sees a toast on that character's page for each new feat, in the
order of `new`. The stack is part of the owner's Livewire feat wall, so a visitor, a signed-out viewer and `?as=visitor`
get no toast markup and no script work. About 600 ms after the page has loaded, and only while the tab is visible
(otherwise when it becomes visible), the toasts play one at a time at the bottom right, above the page and above an
open feats dialog. Each says "Feat earned" or "Upgraded from Silver", the feat name and its new tier, stays 4.2 s,
pauses while hovered or focused, and has a close button; the next follows 300 ms after one leaves. At most five play.
When more are new, a sixth toast says "N more feats" with a "View all" button that opens the feats dialog. Showing a
toast marks it on the server (`FeatWall::seen`, through `MarkOwnedFeatsSeen`): the toast's feat and tier, or for the
"more feats" toast every feat beyond the fifth. A toast that never showed is not marked, one that was closed early
stays marked, and a reload after the sequence shows nothing. A pin toggle re-renders the section but not the stack,
so it does not restart or repeat the sequence. The seal stamps in (scale 1.35 to 1, a ring in the tier's colour, one
light sweep; an upgrade starts in the previous tier's colours and changes at the stamp; Legend rings twice). With
reduced motion there is no movement, scale, ring or sweep, only a 150 ms fade, on the same timings.

A feat is one tier of one ladder. Copper comes in the first session or two. Each later tier takes several times the
play of the one before: silver about 50 hours, gold about 200, scarlet about 500 and legend, the top tier, about
2,500 (owner, 2026-11-21: "much much much harder", "bronze can be easy to get people interested but then things get
slow", "make legends larger, go 150k" for kills with scarlet at 30,000). The thresholds were sized from the rates of
one character that plays a lot (53 kills, 2.7 quests and 0.29 world widths an hour), so they are a first setting.
Summit, Unbroken, Artisan, Explorer, Questing and Tracker stop where the game does: legend is the level cap, five
professions at 300, or most of the zones, quests and rares of the world. Delver, Crafter, Angler and Gatherer are
sized for play given to them, such as 130 hours of fishing. The summary counts the feats held, and its counts by
tier add up to them.

| Feat | Counts | Copper | Silver | Gold | Scarlet | Legend |
| --- | --- | --- | --- | --- | --- | --- |
| Summit | levels reached | 10 | 25 | 45 | 55 | 60 |
| Veteran | hours played | 2 | 50 | 200 | 500 | 2,500 |
| Devoted | days played | 3 | 14 | 60 | 150 | 365 |
| Steadfast | days played in a row | 2 | 7 | 21 | 60 | 180 |
| Seasoned | weeks played | 1 | 4 | 12 | 26 | 52 |
| Regular | sessions played | 5 | 60 | 250 | 600 | 3,000 |
| Long Haul | sessions of two hours or more | 1 | 15 | 60 | 150 | 600 |
| Longstanding | days since the first recorded session | 7 | 30 | 90 | 180 | 365 |
| Unbroken | levels reached without dying (only for a character recorded from level 1) | 10 | 20 | 35 | 50 | 60 |
| Slayer | kills | 100 | 2,500 | 10,000 | 30,000 | 150,000 |
| Bestiary | kinds of creature killed | 10 | 250 | 750 | 1,250 | 2,000 |
| Finisher | killing blows of your own | 50 | 1,000 | 5,000 | 15,000 | 75,000 |
| Gladiator | players killed | 1 | 25 | 100 | 500 | 2,500 |
| Rampage | kills in one session | 50 | 300 | 600 | 1,000 | 2,000 |
| Fallen | deaths | 1 | 10 | 40 | 100 | 400 |
| Flattened | points of damage in the biggest killing blow from a creature | 50 | 300 | 1,000 | 2,500 | 5,000 |
| Nemesis | deaths to one killer | 2 | 4 | 8 | 15 | 30 |
| Territorial | kills in one zone | 50 | 500 | 1,500 | 4,000 | 12,000 |
| Roamer | zones with 100 kills or more | 1 | 8 | 16 | 24 | 32 |
| Questing | quests turned in | 10 | 150 | 500 | 1,000 | 1,500 |
| Errand Runner | quests turned in in one session | 3 | 10 | 20 | 35 | 50 |
| Fickle | quests abandoned | 1 | 20 | 75 | 150 | 300 |
| Cartographer | areas discovered | 5 | 100 | 300 | 600 | 800 |
| Local Hero | zones with 25 quests or more turned in | 1 | 4 | 8 | 12 | 18 |
| Thorough | quests turned in in one zone | 10 | 30 | 60 | 90 | 110 |
| Delver | dungeon runs | 1 | 20 | 75 | 200 | 500 |
| Vanquisher | dungeon bosses killed | 1 | 50 | 250 | 1,000 | 2,500 |
| Spelunker | dungeons and raids entered | 1 | 5 | 12 | 18 | 22 |
| Raider | raid runs | 1 | 10 | 40 | 100 | 250 |
| Conqueror | raid bosses killed | 1 | 25 | 100 | 300 | 800 |
| Deep Dweller | hours inside dungeons | 1 | 20 | 75 | 200 | 600 |
| Flawless | dungeon runs with a boss killed and no death | 1 | 10 | 40 | 100 | 300 |
| Familiar | runs of one dungeon | 2 | 8 | 20 | 50 | 120 |
| Soloist | dungeon runs alone | 1 | 5 | 20 | 50 | 150 |
| Full House | dungeon runs in a group of five | 1 | 20 | 75 | 200 | 500 |
| Explorer | zones entered | 5 | 20 | 40 | 55 | 65 |
| Globetrotter | zones with an hour or more of play | 2 | 10 | 20 | 30 | 40 |
| Local | hours in one zone | 2 | 15 | 40 | 100 | 300 |
| Wayfarer | world widths walked | 1 | 15 | 60 | 150 | 750 |
| Jumper | jumps | 100 | 5,000 | 20,000 | 50,000 | 250,000 |
| Swimmer | yards swum | 500 | 10,000 | 50,000 | 125,000 | 600,000 |
| Runner | yards run | 1,000 | 400,000 | 1,500,000 | 4,000,000 | 15,000,000 |
| Rider | yards ridden | 1,000 | 100,000 | 1,000,000 | 4,000,000 | 20,000,000 |
| Stroller | yards walked | 100 | 2,000 | 10,000 | 50,000 | 250,000 |
| Flyer | yards flown | 1,000 | 150,000 | 600,000 | 1,500,000 | 7,500,000 |
| Tracker | rare creatures seen | 1 | 10 | 30 | 60 | 100 |
| Scout | elite creatures seen | 5 | 75 | 250 | 500 | 900 |
| Witness | bosses seen | 1 | 5 | 15 | 30 | 50 |
| Naturalist | kinds of creature seen | 25 | 750 | 2,000 | 3,500 | 5,000 |
| Skullseer | creatures seen whose level the game hides | 1 | 5 | 20 | 50 | 100 |
| Looter | corpses looted | 10 | 1,500 | 6,000 | 15,000 | 75,000 |
| Scavenger | poor items looted | 5 | 250 | 1,000 | 3,000 | 12,000 |
| Lucky | uncommon items looted | 1 | 100 | 400 | 1,000 | 4,000 |
| Collector | rare items looted | 1 | 15 | 60 | 150 | 500 |
| Jackpot | epic items looted | 1 | 3 | 10 | 25 | 75 |
| Curator | kinds of item received | 25 | 600 | 1,500 | 2,500 | 4,000 |
| Unboxer | containers opened from the bags | 1 | 25 | 100 | 300 | 1,000 |
| Angler | fish caught | 10 | 250 | 1,500 | 5,000 | 15,000 |
| Fishmonger | kinds of catch | 3 | 15 | 30 | 45 | 60 |
| Wading | zones fished in | 1 | 5 | 10 | 20 | 30 |
| Gatherer | nodes gathered | 10 | 250 | 1,500 | 5,000 | 15,000 |
| Forager | zones gathered in | 1 | 5 | 10 | 20 | 30 |
| Artisan | profession ranks | 75 | 300 | 750 | 1,350 | 1,500 |
| Master | professions at rank 300 | 1 | 2 | 3 | 4 | 5 |
| Practised | skill-ups | 10 | 300 | 1,000 | 1,800 | 2,500 |
| Crafter | items crafted | 5 | 500 | 2,500 | 10,000 | 25,000 |
| Repertoire | kinds of item crafted | 3 | 25 | 75 | 150 | 300 |
| Earner | gold earned (only when the owner shows the economy) | 1 | 100 | 1,000 | 5,000 | 25,000 |
| Spender | gold spent (only when the owner shows the economy) | 1 | 100 | 1,000 | 5,000 | 25,000 |
| Tycoon | gold carried at once (only when the owner shows the economy) | 1 | 50 | 500 | 2,000 | 10,000 |
| Windfall | gold earned in one session (only when the owner shows the economy) | 1 | 10 | 50 | 200 | 1,000 |
| Expressive | emotes used | 10 | 250 | 1,000 | 5,000 | 25,000 |
| Mime | kinds of emote used | 3 | 15 | 40 | 80 | 120 |
| Signature | uses of one emote | 5 | 100 | 500 | 2,000 | 10,000 |
| Indecisive | talent respecs | 1 | 3 | 10 | 25 | 60 |

The feats and their thresholds are one table in code (`App\Support\Feat`). The counts are stored in
`character_feats` (one row per character and feat, the `value` and when it was counted): `RefreshCharacterFeats`
runs after every decode of the character and `feats:refresh` queues it for one character or for every
character with a session, as a daily safety net. `CountFeats` and its counters under
`App\Actions\Feats\Counts` read the base tables only, `sessions` and the fact tables, never a continuous
aggregate, so a count never lags the decode that triggered it. The tier is not stored: it is read from the
value with `Feat::tier()`, so a threshold change needs no recount. Values that are hours, gold or days are
rounded down before they are stored, and Wayfarer down to one decimal, so a value never shows a tier it has
not reached. The emote feats leave out `Emote::READ`, as the emote rankings do. The character page and the API read the stored rows. `CountHolders` counts, for every feat and tier,
the characters whose stored value reaches it (one query, cached 15 minutes), and `ShowCharacterFeats` orders by it.

### Search

Search finds public characters, creatures, items, zones, quests and the site's own pages by name. One Action,
`App\Actions\Public\Search`, answers the search page, the instant results under a search field and the public API.

**How a name matches.** The text is split into words and every word must be in the name. A hit carries how it
matched, and the tiers rank in this order (`App\Support\SearchMatch`):

| Tier | A name matches when | Example for "wolf" |
|---|---|---|
| `exact` | it is the text, ignoring case | Wolf |
| `prefix` | it starts with the text | Wolfguard Worg |
| `word` | one of its words starts with the text | Timber Wolf |
| `contains` | the text is somewhere inside it | Direwolf |
| `fuzzy` | it is a close spelling of the text | "razormne wolf" finds Razormane Wolf |

A number finds the creature, item, quest or zone with that id as an `exact` hit. `fuzzy` runs only when
nothing matched any other way, in any kind asked for, and the text has four characters or more, so a correct spelling
costs what it cost before and never pulls in look-alikes. It uses the trigram indexes the search already has (`word_similarity` at 0.6, PostgreSQL's default:
0.5 let "stats" find Starving Mountain Lion), with no new index. It finds a dropped, doubled or swapped letter inside a
longer word; two letters swapped in a four-letter word share too few trigrams to be found. Close spellings list the
closest first.

**Order inside a tier** is by how much players did with the thing, most first, then the shorter name: kills for a
creature, turn-ins for a quest, hours played for a zone, sources for an item, time played for a character. The
numbers are the ones a result line shows, under the same character threshold, read from `search_scores` (kind, id,
score), which a scheduled Action refills every hour from the rollups. A thing with no score ranks after those with
one.

**Site pages** are results too: the dashboards, each leaderboard, each ruleset, each class and spec of the talents
pages, the map, the download and the API reference. They come from a registry in code
(`App\Support\SitePages`), matched the same way on their title and a few keywords, and read no table.

**Instant results.** The search field in the header and the one on `/search` are comboboxes
(`resources/js/site/search.js`). From two characters they show a panel with the best hit of every kind first, grouped
with the kind's glyph, the class colour of a character and the quality colour of an item, and a last row that opens
the full results. `/` puts the cursor in the header field, the arrow keys move through the hits, Enter opens the
marked one or the full results, Escape closes the panel. With no text the panel lists the last searches made on this
device, kept in the browser only. The panel reads `GET /search/suggest?q=`, a page route (so it shares the pages'
rate limit and edge cache) that answers the lines already formatted; the script formats nothing. Without the script
the field is the plain form it was.

**The search page.** The field sits in a page head over the contour art. Results open with the best hit as one large
line with its numbers, then the groups; the matched part of each name is marked. When only close spellings matched,
the page says so above them. With no text, and under a search that found nothing, the page shows what players search
for by doing it: the most killed creatures, the zones with the most deaths, the most finished quests
(`App\Actions\Public\SearchStart`, which calls the three rollup Actions that already rank them).

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
  without a link, until a hardcore ruleset has data. The address of a ruleset is its kind (`/rulesets/normal`); an
  address from before kinds redirects to the ruleset that took it over. Rows with no sessions are not listed.
- **Art and game layer**: the contour sheet, the map hero, the tally, records, rules, the climb, the roster, the hall,
  the podium and feats are not in the comps or in the gallery that rebuilds them (`/design/*`). "Art and game
  layer" above is their design.
- **Camps** (`docs/comps/camps.html`): the comp's head is a still with no game art; the built head is the three.js scene
  above. The approved prototype differed from the page in five places. Tent rests by level stands where it had rested
  experience by level, because the recording does not say whether experience was rested. "Lost to a death" stands where it
  had "Median kept". "Play with Camp Benefits" stands where it had "The half hour after a camp". Visit lengths use six
  ranges around the 60 second mark (under 30 s, 30 to 59 s, 1 to 2 min, 2 to 5 min, 5 to 10 min, 10 min and over)
  instead of minutes. The figure strip is the contract's six (visits, characters, hours by the fire, median visit, visits a
  played hour, Camp Benefits earned) and the head has no line of facts under the title. "Kits and blueprints" is not
  built: kits are crafted or mailed, not looted, and no table records that. Play with Camp Benefits has no kills
  figure: no aggregate gives a kill rate by level. A note under the benefits table gives the share of Camp Benefits
  given for two hours instead of one. Under 820 px the stage shows the three names and the shares are a list below it.
- **Class filter**: leaderboards filter by class (`characters.class`). The dashboards and result pages do not: no
  aggregate carries the class.
- **Reach**: "Characters that reached each level" in place of the comp's wording; the chart counts characters.
- **Dungeon table**: "Bosses a run" replaces "Median level"; "Skills" replaces "Professions"; the gold chart is the mean
  earned and spent per character, not the median carried.
- **Map layers**: the third layer is creatures met; kills carry no position. The map has no Hours/Days toggle.
- **Aggregates without a ruleset**: item and drop-rate figures cover every ruleset, as the drop aggregates have none.
- **Not built**: Played with and the progress bar on the character page (the decoder yields no group membership),
  "How it kills" and "Rolls in groups" on creature and item pages.
- **Distance** is in world widths; a step longer than 0.05 of the world is a teleport and is not counted. Distance by
  mode on the character page is different: the yards its `W` movement stretches report. The Wayfarer feat and the
  most-distance board stay on the path length.
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

**Dungeon maps.** A recording carries no position inside a dungeon, so a dungeon is not on the world map. Its page
shows the map of each floor instead: the pictures the OpenMap addon ships (Blizzard's own dungeon maps, maps of WoW
Forever's dungeons drawn by a player who lets OpenMap use them, and maps laid from the game's minimap pictures),
drawn in the dark look by `tools/map-tiles/dungeons.mjs`: light and dark kept, most of the colour taken out. OpenMap's
boss places become numbered pins, and each boss in the list under the map links to its creature page when the site
knows a creature of that name. The pictures are not in git: they live on the `maps` disk under `dungeons/` with
`dungeons.json`, `/dungeon-maps/{instance}/{floor}.webp` serves them, and a page has the section only while that list
has its instance (`App\Support\DungeonMaps`). A drawn map is credited to its artist under the picture, by the name
OpenMap credits, which comes from `dungeons.json` and is not in this repo.

**Credit.** Every page that shows the art says "Map art © Blizzard Entertainment. Map by OpenMap." under the
map, with OpenMap linked. Data or code copied from OpenMap into this repo carries its MIT notice
(`tools/map-tiles/OPENMAP-LICENSE.txt`).

**Where a recorded point is.** The recording gives a zone map and a position on it (`uiMapID`, x, y). The world
position needs that zone's rectangle on the world, which the game computes. `map_rects(ui_map_id, u0, v0, u1, v1)`
holds one row per zone map, loaded from `docs/map-rects.tsv`. Zephras Isle (2521) is its own continent, which the
game's world map does not hold, so the tables give it no rectangle: `tools/map-tiles/rects.mjs` places it by hand
(`AWAY`) on the island the world's own art paints south of the Maelstrom, at the world's scale. The battlegrounds
(1459 to 1461) and Darkspear Islands (2524) are not on the world either and have no rectangle; nothing has been
recorded on them. The game's tables hold no map of a dungeon, and a recording carries no position inside one. Ingest stores `u, v` next to the zone position on
`path_points`, `deaths` and `creature_sightings` (null for a map that is not on the world, such as a dungeon), and
the heat rollups are keyed by a world cell: `cell_i = floor(u * 768)` and `cell_j = floor(v * 512)`, a grid of 768 by
512. The world is 3:2 (16032 by 10688 px at the top zoom), so those cells are square: 20.875 px at the top zoom, about
1.3 of the world's 1002 by 668 units a side. A point with no `u, v` is in no cell. `bin/artisan maps:place-points` fills
the nulls of rows whose map has a rectangle, in windows of seven days, then refreshes the world heat and measures
again the sessions whose path points it placed; it finds nothing to do on a second run. `maps:load-rects` does the
same after it loads the table, and the deploy runs that, so a rectangle added to the table reaches the points
recorded before it, and nothing is uploaded again. Kills carry no
position, so the map's third layer is the creatures met (`creature_sightings`), not kills.

**Heat levels.** Heat is kept at four cell sizes so that zooming in shows detail, not bigger squares. Level `n` is a grid
of `192 * 2^n` columns by `128 * 2^n` rows: 192, 384, 768 and 1536 columns for paths (the fine rollups), 192, 384 and 768
for deaths and creatures. A coarser level is read from the finest rollup by integer division of `cell_i` and `cell_j`, so
the levels nest exactly, and the minimum-characters rule applies to each cell of each level. Paths are recorded about
once a second, 0.16 world units apart at the median, so the 1536 grid (0.65 units a cell) joins them into lines.
`GET /api/v1/public/map/density?layer&level&ruleset&creature` answers one whole level: `columns`, `rows` and `cells`, a
flat list of pairs (cell index minus the previous cell's index, counter), cells in index order with `index = j * columns
+ i`. It is cached at the edge for `scarlet.edge.api_seconds`. Pages carry no heat cells: the viewer asks for the level
that suits its zoom (the finest one whose cells are at least 2.4 CSS px wide; it keeps the coarser one on screen while
the finer one loads, and switches back only when a cell falls under 1.9 px).

**Drawing heat.** The counters of a level go into a grid, are blurred with a Gaussian of 0.6 cell, put through a log
scale that tops out at the 98th percentile of the nonzero cells (so one hotspot does not saturate the rest), and coloured
by a ramp that is transparent at low density and scarlet to rose at high. The result is one texture per level, drawn
scaled with bilinear filtering; a frame is a single `drawImage` of the visible part. A character's page draws its own
cells (1536 grid, no threshold) the same way, and the replay shows a texel once the first cell that touches it has been
walked. The page does not carry those cells: its map spec names `/c/{name}/heat`, which answers `data.grid` and `data.cells`
(a flat list of cell_i, cell_j and points, in the order first walked) to exactly the viewers who may open the page, and the
viewer reads it after the first paint; a replay waits for it like it waits for tiles.

**Lines.** Paths are drawn as lines once a cell of the finest grid is 1.9 CSS px wide (fully from 2.6 px; the texture
stays under them at 0.3 of its strength, as the glow, and fades further once a cell is over 14 px so it never washes
over a city). The viewer builds them from the cells, once per level
(`resources/js/map/trails.js`, about 40 ms for 20,000 cells): gaps of up to 3 cells that walked cells enclose are
closed, the walked cells are thinned to their one-cell-wide middle (Zhang-Suen), and a cell that thinning removed
with every neighbour comes back as a dot. Each cell left is a point at the middle of the counters of its 3 by 3
neighbourhood, weighted by them, which puts a road that runs between two rows of cells between them. Points that touch
are joined, three passes move each point to the mean of itself and the points it is joined to, and each point is
stroked as a curve from halfway to one neighbour to halfway to the other. Width and colour follow the counters round
the point against the median point: five steps from a 1 px wine line at a quarter of the median to a 2.7 px rose line
at four times it, so a map of one character is a plain scarlet line and a shared road stands out. The widths are
scaled by a fifth of the cell's width in px, between 0.7 and 1.5, so a whole zone is fine lines. Lines are vectors,
drawn 32 by 32 cells at a time from the chunks in view, so they stay sharp at every zoom. Deaths and creatures are
places, not routes, and stay the texture. A replay draws the points walked so far.

**Sheets.** The Survey look is the Scarlet app's drawing, as one continuous world: the app's dark sheets (`app/maps-dark`,
drawn by its `tools/render-dark.mjs`, no pixel of the game's art in them) stitched together and cut into the same
256 px tile pyramid the Game look uses, so the map zooms and pans without ever changing sheet. `tools/map-tiles/sheets.mjs`
builds it into `public/map-sheets`, which is committed (2,959 tiles, 2.8 MiB): the world's sheet is the base, and each
of the 43 zone sheets is laid on it at its rectangle, cut to the zone's own land from OpenMap's `MapZoneLand` mask, once
7 world units past the shore so the coast is the zone's drawing and once on the land alone so no zone paints its flat
neighbour tone over the land next door. Orgrimmar, Ironforge, Thunder Bluff and Undercity are laid over their zones
the same way, cut by OpenMap's `MapCityMask` and without the shore pass, so a path through a city runs between its
buildings; Stormwind City and Darnassus have no such mask and keep their zone's drawing. Zephras Isle is cut by
`MapCore`, and because the world's sheet does not draw it, it is laid on every level. Under the zones the world's sheet is blurred, because its lines enlarged eight
times are smears. A zone drawn as a thumbnail is noise, so levels 0 to 3 are the world's sheet alone, level 4 is half
zones, and levels 5 and 6 are the zones. A tile that is the bare sheet is left out and the manifest's `ground` colour
fills it. `manifest.json` is the tile manifest plus `places`: each zone's rectangle and its names (the client's area,
town and flight point names, from the app's label tables) in world coordinates. Names are lettered as the app letters
them, towns with a dot and areas in spaced capitals: a zone's towns once it is 380 px wide on screen, all its names
from 1000 px. The viewer fades each tile level into the next over the first 0.45 of a level, so the change from the
world's drawing to the zones' is a dissolve. Heat and lines are drawn over the whole world, unmasked. The Game look
keeps the game's tile pyramid. The world map page lists the 12 zones with the most recorded play, busiest first, with
the world on top and the opened zone added when it is not among them: a row moves the map to that zone,
`/map?zone=<uiMapID>` opens on it, and the highlighted row, the zone page link, the address, the ruleset links and the
line under the map follow the zone the view is on (of the zones that fill two fifths of the view, the one whose
rectangle the centre is deepest inside; none over the whole world). Zone rectangles overlap, so the readout names the
zone under the pointer by the same rule: the one whose rectangle the pointer is deepest inside, measured against the
rectangle's size, and a continent only where no zone is. A double click on a zone's name does what one click does. A `zone` that is no uiMapID is ignored. `zone` is one of the query parameters the
page cache keeps (`EdgeCacheable`), so each opened zone is its own cached page. A map with no sheet manifest reads the
Survey look from the game's pyramid.

**Creatures.** A creature page marks where it was met with dots. Neither a sighting nor a kill records the creature's
position, so a dot is the nearest position the same character's path recorded within ten seconds of the sighting, else
the middle of the sighting's cell. A dot carries no time and no character. A creature met more than 600 times is drawn
as the same density surface instead (768 grid). The map opens on the dots' bounding box, never wider than a zone.

**The viewer** is the site's own canvas module, with no map library: tiles by zoom, drag and wheel and pinch to move,
plus and minus keys, a heat layer drawn as a density surface, paths as scarlet lines, death crosses, zone names from the dictionary at the zooms where they fit, and a link from a zone's name to its page. It
loads only the tiles in view. The wheel zooms towards the pointer and a double click zooms in twice as close (out with
Shift), both eased so the map glides; a notch of the wheel is 1.4 times. The wheel zooms a map, except one that lies behind a page head (a character page,
a zone page): there the wheel and a one-finger vertical swipe scroll the page, so a visitor is never stopped on the way
down. Clicking or dragging that map hands it the wheel until the pointer leaves it, and Ctrl or Command with the wheel
always zooms. Two fingers pinch and move it. Dungeons and other maps that are not on the world keep the plain sheet with their own
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
