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
| `imports` | Imported history from other addons | Gold balances, sales, purchases, returned auctions and other income and expense read from other addons' saved variables; owner-only, never in a board |
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

The `imports` group carries gold balances, sales, purchases, returned auctions and other income and expense that the companion read from
other addons' saved variables (Scarlet's FORMAT.md, "Imported history"). They are a third kind of record beside lifetime
and observed numbers and stay apart from both:

- One part per UTC day, whose session key is that day's start in epoch seconds. `DecodeSession` sends these parts to
  `scarlet-part decode --imports` and `StoreImportedRecords` replaces the day's rows in `imported_records`; the session
  decoder skips the group, so no session, fact row, rollup or board ever sees them.
- A line is `YG` (kind `gold`; `source` is `TSM` or `Mastercraft`), `YS` (`sale`), `YB` (`buy`), `YM` (`other`) or `YU`.
  `YU` is an auction that came back: kind `e` becomes `expired` and `x` becomes `cancel`, with `item_id`, `quantity` = the
  line's count, `copper` 0 and the stack size in `via`. A `YU` of any other kind is skipped. The decoder fails a day on
  a code it does not know, so a decoder that reads a new code ships before any app writes it.
- `imported_records` has no public reader. `ShowImportedHistory` feeds the signed-in owner's character page and the
  account's characters page, both labelled with the source. Both show the returned auctions (expired and cancelled) as a count, with the items in all.
  Removing the group or the character deletes its rows.

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
window (an event, a session, a day, a level, or a lifetime `[since, as of]`), dimensions (zone, item, npc, level, quest,
instance) and amounts in Scarlet's metric names. A non-additive fact (a record weight, a first catch, an attunement step) is not a
claim: it stays in its addon's group and is shown beside, never summed.

Only a field this table maps becomes an amount. Each row was measured against Scarlet's own rows on the owner's data
(2026-11-22, 12 sessions since 2026-11-20 19:36 UTC, 366 channel ends; the `rxp` and `fdj` rows on the owner's main
character the same day: 18 levels, 133 quest entries, 8 runs); a new integration adds its rows with its own
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
| `mc` | `nodes[*].c` summed by profession | `attempts` for kind `herb` and `ore`, as an account `life` claim (store empty), reference only | measured on the owner's data: ore 272 = Scarlet's 272 gathers (77 of 77 nodes equal), herb 84 against 85 inside Scarlet's window (73 of 80 nodes equal), plus 355 herb gathers before Scarlet's first herb row that no character can be given |
| `mc` | `life.escapes`, `sessions`, `seconds`, `history` | nothing | `escapes` never fires; sessions and seconds follow its own rules; `history` is gold and skill |
| `rxp` | per level, the sum of `mobs[*].count` | `kills` of a `kill` claim, window `level`, dims `level` | at or below Scarlet's `kills` rows at 18 of 18 levels (3,624 against 4,157), and 3,482 against 3,510 kills that gave XP over the 14 complete levels. It counts only kills that gave XP, so it is a subset of Scarlet's by construction. Every Scarlet row at those levels is the backport's: no level overlaps live capture |
| `rxp` | each `quests[zone][id]` of a level, with XP above 0 | `xp` of an `xp` claim, window `level`, dims `level` and `quest`, one claim per quest id | equal to Scarlet's `turned_in` rows at the 14 complete levels (116 turn-ins, 368,090 XP both). The 4 partial levels hold less than Scarlet. RXP overwrites a repeated id in a zone, and no level shows one |
| `rxp` | `mobs[*].xp`, `deaths`, `groupExperience`, `levelsArchive`, the /played seconds | nothing | the XP is the first number of the chat line (+5,341 at one level: a boss with "6000" in its name), deaths are 27 against Scarlet's 15, the rest has no Scarlet twin |
| `fdj` | each `runLog.runs[i]` with `closed = true` | `runs` = 1 of a `run` claim, window `session` from `created` (the first kill) to `lastSeen`, dims `instance` (`map`), store `<id>:<created>` | its 8 runs are 4 of Scarlet's joined runs, and Scarlet has 16 in all, so it adds 0 runs to the owner's data. `map` equals `instance_id` in 8 of 8 (2970, 189, 129, 3039). `lastSeen` is within 2 to 3 s of Scarlet's leave, and `created` is the first kill, 39, 55, 200 and 433 s after Scarlet's entry |
| `fdj` | `kills`, `x`, `money`, `deaths`, `needs`, `party`, `exits` | nothing | kills are 235 above Scarlet's in 8 of 8 runs, exactly the kills that gave no XP; XP and money equal Scarlet's to the copper and the one death is 1 against 1, the same figures twice |
| `nf` | each `journalKills[<guid>][<encounterID>].at[i]` | `bosses` = 1 of a `boss` claim, window `event`, `from` = `to` = `at[i]`, dims `encounter` (the encounter id), store empty | 115 kills of 82 bosses, and all 115 match a Scarlet kill within 0.6 s (offset 0 ms), 5 of them kills Scarlet knows only from an `E` end; 3 Scarlet kills match no NF kill (2 encounters its journal does not list, 1 before it recorded). The character is the one `charGuid` names for the GUID; a GUID with no name there writes nothing. The store is empty, so two GUIDs of one name give one claim |
| `nf` | `n` past the 10 dated `at`, `first`, `took`, `best`, `team`, `drops`, `completoRareKills` | nothing | never written; `team` and `drops` hold other players' names |

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
kind. Scarlet's FORMAT.md lists a new code before `groups.tsv` does, because `scarlet-part check-groups` reads FORMAT.md
from Scarlet's main and fails a group that lists a code FORMAT.md lacks.

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
  `reconcile_version`. The columns from `status` on stay null until reconcile runs. `key_from` is generated: `from_at`
  for a lifetime, 0 for every other window, so one unique index holds the whole key.
  An account claim has one row per character whose parts carry it. Those characters are its scope, and the account
  page and rule 7 read it once per key.
- `addon_claim_parts(claim_id, part_id, amounts, from_at)`: the parts that carry a claim, each with the amounts and
  window start it carried.
  Decoding a part replaces its rows here and inserts claims whose key is new; a claim goes when no part carries it.
  When carrying parts disagree, the claim takes the amounts and window start of the part whose id sorts highest, and
  the audit lists the claim. Decode reads no other table and writes no fact table or rollup, so Scarlet's own numbers
  cannot move.
- `addon_days(character_id, day_key, decoder_version, reconcile_version)`: the scheduled outdated pass re-decodes and
  re-reconciles these as it does sessions.

`scarlet-part decode --counts` reads a day's `counts` parts and writes one `claim` row per `YC` line (the part's id,
the key fields, `dims`, `amounts`) and a closing `day` row with the decoder version and the lines skipped: a code that
is not `YC`, a kind the decoder does not list (`fishing`, `kill`, `run`, `xp`, `herb`, `ore`, `boss`), a line it cannot read, and a key a part
repeats (the last line wins). Nothing is fatal to the day. `StoreAddonClaims` takes the rows under the character lock;
a key whose `counts` parts are all gone decodes again, which drops the claims only they carried. Removing the `counts`
group, or the character, deletes the character's claims.

### Reconcile

`reconcile(character, kind)` is one function in `scarlet-part`, run by the server and later by the app
(`scarlet-part reconcile` reads JSON on stdin and writes JSON; `--explain` prints the table of statuses). It reads rows,
not parts: the character's claims of the kind, the character-scope claims of the characters linked to it with the
Scarlet rows each holds beside those claims (rule 4: for fishing the times of its rows within 60 s of them, for
`kill`, `xp` and `run` the rows the kind's rule reads) and its own Scarlet rows of the kind, counted or not. For fishing a row is a `gathers` row of kind fish with its `looted` and
the quantity of the loot window that followed it (the `gather_items` from its cast to the next cast of its session,
without the ones that came from opening a container). For a `kill` claim a row is the count of `kills` rows with
`player` false at one level, with the time of the last of them, and the count with no level (the decoder writes a null
level until the session has one). For an `xp` claim a row is a turn-in, from `quest_events` (`event` `turned_in`) or from
`xp_gains` (source `q`), with its quest id (null when the client hid it), `xp`, `level` and zone; both tables are read
because their groups (`quests`, `xp`) can be on apart, and the turn-ins with no quest id are those of the table that
holds more of them. For a `run` claim a row is a `dungeon_runs` row joined along
`continues_previous`: `instance_id`, the first entry and the last leave (the entry of the session's next run when
`left_at` is null, a direct move to another dungeon, and `at` plus `inside_ms` when no run follows). `inside_ms` is
the time inside over all stays, so it ends a run that was left and entered again too early.
It gives every claim exactly one status with its counted amounts
and its evidence. It reads no database and no clock; `now` is an input. Every counted amount is floored at 0 and
rounded half up in integer arithmetic; a ratio whose denominator is 0 is 0.

The order of the work is fixed, and each step sees only the claims the steps before it left:

1. Claims that fail their bounds are `rejected`; account-scope claims are `reference`; a kind with no row source
   (`fishing`, `kill`, `xp`, `run` and `boss` have one), an origin outside the kind's rank list (fishing: `fl`, `ff`;
   `kill` and `xp`: `rxp`; `run`: `fdj`; `boss`: `nf`), a metric the kind does not know (`kills`; `xp`; `runs`;
   `bosses`) and, for the kinds with a shape, a window or dimension keys other than it reads or a dimension that is
   not a whole number from 1 to 2,147,483,647 written plainly (`kill`: window `level`, dim `level`; `xp`: window
   `level`, dims `level` and `quest`; `run`: window `session`, dim `instance`; `boss`: window `event`, dim
   `encounter`) are `held` with the rule `unmapped`. A `level` is inside 1 to 100 by then,
   since the bounds run first, and the read of Scarlet's rows leaves out a dimension above 2,147,483,647, which the
   database cannot bind as an integer.
2. A character-scope `life` claim is `held` (rule 6).
3. A claim equal to one of another linked character is `superseded` (rule 4) unless its character is the one that
   counts it: the one with Scarlet rows beside the claim, else (for `boss`) the one with a session that has a combat
   part within 60 s of it, else the lowest id. The claims it moves stay known to
   steps 4 and 5, so that the claims they were one record with are decided with them.
4. Dated claims of one origin with equal dims that overlap collapse into one (rule 5, second sentence): the claims
   are taken by end, start, store and dims, latest first, and each one with equal dims overlapping a winner already
   taken loses to it.
5. For `kill`, `xp` and `run`, each claim left goes through its own rule (3L, 2Q, 3R; `boss` goes with the events
   of step 6): none has a second origin, so
   there is no rule 5 across origins, and none has a time to assign rows by. For fishing, per
   origin, highest rank first, Scarlet's rows are assigned to the claims left and settled across the seams between
   neighbouring claims (rule 3). Then each claim in turn: a
   claim that overlaps counted or residual claims of a higher rank by more than 60 s and is covered by them is
   `superseded` (rule 5); an origin that stamps with the server's clock goes through rule 1; a claim they cover in part
   counts the rest (rule 5); every other claim goes through the arithmetic of rule 3.
6. Events are matched last (rule 2).

The server runs it kind by kind in the queued job `ReconcileCharacterClaims`, or by hand with `php artisan
addons:reconcile {character?}`, after any decode of the character (its sessions and its `counts` parts), any removal of
a group or of everything, a ruleset merge, the link of an install to an account and a plausibility change. A
plausibility change happens only inside a decode, which writes `sessions.counted`. The job reads its inputs, runs
`scarlet-part` outside any transaction, then takes the locks of the characters it read, lowest id first, reads the
inputs again and writes only when they are the ones it decided on; otherwise it decides again, three times at most. A
claim row is written only where a column differs, so a second run changes no row. The scheduled outdated pass queues a
reconcile on its own, without a decode, for a character whose `addon_days.reconcile_version` is older than the rules
or null. `RECONCILE_VERSION` is 7: the `boss` kind arrives with it (6 brought the rules for `kill`, `xp` and `run` and
the equal-dims collapse), every character is decided again once, and no fishing, `kill`, `xp` or `run` verdict changes
(every other claim is keyed on its kind).

Every write that can change a verdict nulls `addon_days.reconcile_version` of the character and of the characters
linked to it in its own transaction: the store of a decoded session or of a day of `counts` parts, `RemoveGroup`,
`RemoveEverything` (for the characters linked to the removed ones), the link of an install and a ruleset merge. The
dispatch after the commit is the fast path and the outdated pass the guarantee, so a worker or request that dies
between the commit and the dispatch leaves the version null and the next pass decides again. The reconcile stamps the
version under the same locks that it writes the verdicts with, on the days of every character it decided and of the
one it was asked for, so a character whose day holds no claim (every line of it skipped) is stamped once and a pass
with nothing changed queues nothing. A write nulls the days of the characters it names whether or not they hold claims.
A page reads a claim only while its character has no null version.

Each claim stores `status`, `counted` and `evidence`, the `ruleset_id` and `counted_ok` of the session that holds the
window's end (else the nearest session in time), and the `level` and `zone_id` of the last Scarlet row assigned to it
(null when none is). A `kill` or `xp` claim stores its `level` dim instead, with a row or without; `sharedLevels`,
`killsAssigned` and the rows beside a claim for rule 4 read it. The evidence is a JSON object that always holds `rule` (1 to 7, `bounds` or `unmapped`) and
`diff`, the claimed minus the counted amount per metric, so that claimed = counted + diff for every metric. It adds
what the rule used: `rows` (`n`, `first` and `last` in epoch milliseconds, `looted`, `items`; for rule 3L `n`, `level`
and `unleveled`, the rows at the claim's level and with none; for rule 2Q `n` 1 and `quest`), `quest`, `scarlet` and
`claims` (rule 2Q: the quest id, Scarlet's turn-ins of it and the claims of it), `with` (the key of the
claim it lost to, and for rule 4 the name of its character; for rule 5 across origins the keys of the higher
claims in a list, with `minus`, their share of it per metric), `same_origin`, `origin`, `catch_started`, `row`,
`overlap`, `offset_ms` and `why`.

| Status | Counted | When |
| --- | --- | --- |
| `counted` | whole | no Scarlet row and no higher-ranked claim falls in its window |
| `residual` | part | Scarlet's rows, or the claims of a higher-ranked origin, cover part of its window |
| `superseded` | 0 | Scarlet's rows, a higher-ranked origin, the same claim on another character, or another claim of its origin cover it; the evidence names which |
| `held` | 0 | an event with no matching Scarlet row inside a Scarlet session that recorded rows of its kind (a `boss` event: within 60 s of a session that has a combat part); a claim no rule maps (a kind with no row source, an origin outside the rank list, an unknown metric); a lifetime of one character (rule 6); an `ff` claim with Scarlet rows near it and none assigned (rule 1) |
| `rejected` | 0 | it fails its bounds: an amount that is not a whole number from 0 to 9e15, a window that ends before it starts or starts before 2019-08-26 (before the addons existed), a window that ends more than a day after the clock, landed above casts, `casts - unknown` above the window's seconds / 2 + 1; a `level` dim outside 1 to 100, more than 100,000 `kills` in a level claim, more than 1,000,000 `xp` in a quest claim, a `run` claim of any count but 1 |
| `reference` | 0 | an account-scope claim: shown on the account page and checked as a ceiling, never added to a character |

Rules, in order:

1. **Clock.** Scarlet stamps with the PC's clock. `fl` session times and `mc` use the same clock and need no offset
   (within 2.5 s on the owner's data). `ff` stamps with the game server's clock; the distance is that PC's clock
   error, unmeasured on the owner's data. Until Scarlet's `S` line carries the server time, an `ff` claim counts only
   when no Scarlet row of the kind lies within 24 hours of its window. Nearer than that it is `superseded` when rows
   are assigned to it and `held` when none are, never `residual`. An event origin takes its offset per character from
   the pairs that match, never a constant per origin.
2. **Events** match one to one with an unused Scarlet row of the same key (npc, item) within the origin's tolerance.
   Matched is `superseded` with the row it matched. No mapped origin writes item events yet (`nf` writes boss events,
   2B), so this is built and not measured: the tolerance is a provisional 2 s around an offset searched within 60 s (per origin, the median offset
   of the pairs that matched). An event that names no item is `held`, as is an unmatched one that falls inside the
   span of a Scarlet session that recorded rows of the kind; any other counts.
   **2B, boss claims (`boss`).** A `boss` claim is one kill of one encounter at one second. The key is the encounter
   id and Scarlet's rows are the character's `boss_kills` (docs/ROWS.md), counted or not; the offset, the 2 s
   tolerance and the one-to-one match are those above. An unmatched claim within 60 s of a session of the character
   that has a combat part, from the session's start to its end and not from its first to its last kill, is `held`
   ("Scarlet recorded this session and has no kill of the boss"), by the claim's own time and not the offset's; any
   other counts 1. A session decoded before the current decoder version has no `boss_kills` rows, but its span exists,
   so its claims are held until the re-decode writes the rows that supersede them. A kill is also an XP kill and a
   `K` row, so it is a `boss` claim and never a `kill` claim, which would meet the `kill` claims of `rxp` and add to
   the Kills figure.
   **2Q, quest claims (`xp`).** An `xp` claim is one turn-in of a quest, and Scarlet's rows are matched to it by quest
   id, never by time: a character is one name, so both lives and every ruleset share one pool, and a one-to-one match
   cannot add. Per quest id Scarlet holds m turn-ins, the larger of its `quest_events` count (`turned_in`) and its
   `xp_gains` count (source `q`, the quest as `source_id`). With n claims for the id, the n - m claims with the smallest
   `xp` are `counted` whole (ties: the earlier `to`, then the store that sorts first) and the others are `superseded`,
   each with one Scarlet row as evidence (`rows` with `n` 1, in time order); with m at or above n all are
   `superseded`. Then the turn-ins with no quest id (the client hid it), from `quest_events` or `xp_gains`, whichever
   holds more, supersede as many of the claims that would otherwise count as they can be matched to one to one, a row to a claim at its level or the level below (any
   level when the row has none), and of the sets that size the one with the most `xp`: the turn-in that levels a
   character up is booked at the new level by Scarlet and at the old one by RXP, so a row can fit two claims and the
   match takes the claims by `xp`, largest first, and moves a row to another claim that fits when a later claim needs
   it. That is the most XP Scarlet's rows can cover, the worst case for what Scarlet already holds. A repeatable
   quest turned in twice, claims of 300 and 500 beside one Scarlet turn-in, counts 300 and Scarlet's 500: 800.
3. **Dated claims against Scarlet.** Each Scarlet row is assigned, per origin, by its `at` (the cast start) to the
   claim whose window contains it. A row in no window goes to the claim whose nearer edge is closest, within 60 s; at
   equal distance, to the claim that starts later. Seams: a window's edges are whole seconds and Scarlet's clock runs up
   to 1 s behind, so a cast within 2 s of a stop can be assigned to the neighbouring claim. For two consecutive claims
   of an origin: a claim is over when it holds more rows than its `casts` minus `unknown`, or more looted rows than its
   `landed`, and under when it holds fewer. For each metric the smaller of one claim's excess and the other's shortfall
   is what the pair counts twice. While the fewest rows at the seam, within 3 s of the other's window, can move to the
   other claim and lower that sum over both metrics, they move (the rows between them go too, so the ranges stay
   disjoint). Without it a stop and a cast 1.2 s later, as a stop-and-cast macro makes, counts a cast twice, and a
   looted one also its landed and items. With no row assigned, the claim is `counted`, unless every cast of
   it is `unknown` (and it names no landed or items) and its window lies inside a Scarlet session that recorded rows
   of the kind: Scarlet recorded the window and dropped those casts as cancelled, so the claim is `superseded` with the
   session's first and last row as evidence. Otherwise, for fishing:
   - casts: the claim's `casts - unknown` is at most the rows Scarlet keeps when it saw the whole window, because
     `unknown` holds the cancelled channels Scarlet drops. Counted casts = `casts` x the share of `casts - unknown`
     that the assigned rows do not cover.
   - landed: counted = `landed` minus the assigned rows whose `looted` is true. A row whose `looted` is not true
     subtracts no landed, whatever the reason; the decoder writes it false in the sessions of 2026-11-20 that read
     no loot windows, so their landed and items stay with the claim.
   - items: counted = `items` x counted landed / `landed`. Items follow landed so that a catch booked across a session
     seam is not added on both sides.
   - A claim without `casts` (a session a catch started) holds items of the last cast of the claim before it. It
     counts them unless a Scarlet row whose `looted` is true is assigned to it or is the last row of the claim of its
     origin that ends within 60 s before it starts; then it is `superseded` with that row as evidence. A row that was
     not looted shows only that Scarlet saw the cast, not the items, so those stay with the claim.
   - `superseded` when every counted amount is 0, else `residual`. The evidence holds the assigned rows (count, first
     and last time, looted, items) and the difference between claimed and counted per metric.
   Another kind maps its own anchor metric here in the same way before it ships. The seams and the cancelled-in-session
   test above run for fishing only, since every fishing claim is a `session` window with empty dims and no other
   claim is.
   **3L, level claims (`kill`).** Scarlet's count at the claim's `level` plus its count with no level is assigned to
   it. Counted `kills` = max(0, claimed - that count): `counted` when Scarlet has no row, `residual` above 0,
   `superseded` at 0. There is no time window. RXP counts only kills that gave XP and Scarlet every kill, and a
   null-level row subtracts from every level, so a half-seen level counts less than the truth, never more. A recreated
   character of the same name loses its first life's RXP kills where Scarlet recorded the second life at the same
   level: a cost, never a double count. The evidence holds `rows` with `n` (the count assigned), `level` and
   `unleveled`.
   **3R, runs (`run`).** Claims of one `instance` are chained in `from` order while the next starts under 2,400 s after
   the previous ends (Scarlet joins runs at 30 minutes from leave to entry, and FDJ's start is the first kill, so 40). A
   chain is `superseded` whole, with the Scarlet runs as evidence (`rows`: `n`, `first`, `last`), when any joined
   Scarlet run of its instance overlaps `[from - 600 s, to + 5 s]` of a member, or any Scarlet dungeon run of any
   instance overlaps a member's unpadded `[from, to]` (one character is in one instance at a time, so this guards the
   assumption that FDJ's `map` is Scarlet's `instance_id`). Otherwise its first member is `counted` 1 and the others
   are `superseded` with the first's key (`same_origin`, rule 5). A Scarlet run supersedes every claim it overlaps; `run`
   has no `residual`. FDJ does not store the entry, so with no Scarlet run two wings whose first kill came more than 10
   minutes after the entry can be 2 runs where Scarlet's rule would say 1, and two claims of one instance that start
   41 minutes after the previous one's end are 2.
4. **Between characters.** A character-scope claim that equals a claim of another character of the same account in
   origin, kind, window, `from`, `to`, dims and amounts (the store is ignored, events are included) is one record read
   from two folders (a restored backup, a copied folder). It counts on the character that holds the most Scarlet rows of
   the kind beside the claim, and on the lowest id among those that hold as many (among all when none holds any).
   Beside the claim is within 60 s of its window for fishing, and for the other kinds the rows its own rule reads:
   the kills at its level and with none, the turn-ins of its quest and the ones with no quest id that fit its level
   (the larger count of the two tables), the runs that overlap it. Every character counts its own rows and the others'
   the same way, once each. It is `superseded` on the others, with that claim and its character as evidence. The lowest id alone would count a copied folder's history
   twice whenever the copy was uploaded first: its claim counts on the copy and the original's rows count beside it.
   The lowest id among those that hold rows would do the same beside a character that holds more of them: with 2 rows
   of its own in the window on the copy and 50 on the original, the copy counted 48 casts beside the original's 50
   rows. Characters are of one account when they share a game account,
   or an install that uploaded both, and so on transitively among the characters that hold claims. A decode or
   removal that adds or removes such a claim or the rows beside it reconciles the other characters too, locked lowest
   id first. A claim moved to another character stays part of its record on this one. A dated `level` claim with the
   origin, store and dims of a claim moved away is `superseded` (rule 5, `same_origin`, with the moved claim as
   evidence), since a level read under two `to` values is one level whichever character holds the first reading. A
   chain of runs (3R) with a moved member and no Scarlet run beside it counts nothing here: its other claims are
   `superseded` (rule 5) with the moved claim's key, and the character that holds the record counts the run. Without
   both, one record cut in two by rule 4 counted on two characters: the original's later reading of a level, or its
   third wing, beside the copy's. The cost: a character's own run that starts under 2,400 s after a run rule 4 moved
   away is lost with it. By 3R they are one run.
5. **Between origins.** A fixed rank per kind (fishing: `fl` above `ff`). A claim whose window overlaps counted or
   residual claims of a higher rank by more than 60 s counts what it claims minus what they claim in the time they
   share with it, per metric, floored at 0: each higher claim gives its amount times the overlap over its own length,
   rounded half up, so a higher claim that reaches far past this one takes only the share it covers. It is
   `superseded` when that reaches 97.5% of it in every metric (provisional until an `ff` overlap is measured). A claim
   that overlaps only the last minutes of a long one keeps the rest, which a whole supersession would lose, and a
   short claim that straddles two long ones takes a share from each. An origin that stamps with the server's clock goes through rule 1 when it is not covered. Two claims
   of one origin and one character with equal dims whose windows overlap, or whose ends are equal, are one, whatever
   their stores: the later end wins, then the later start, then the store that sorts last. The others are `superseded`
   with its id. A level is claimed once per store: two `level` claims of one origin and store with equal dims are one
   whatever their windows, since a level read under two `to` values (a zone change, a second PC) would otherwise
   count twice. Claims whose dims differ are never one: the quests of a level, or the levels whose `to` are equal.
6. **Lifetime.** No mapped origin has a character-scope lifetime. A `life` claim with scope `c` is `held` until an
   origin maps one and this rule is written with its measurement. The undated term is empty until then.
7. **Account scope** is `reference`. Per store, the audit compares its latest read with Scarlet's rows plus the
   counted claim amounts of its characters inside `[from, to]`, and lists a sum more than 2.5% above it as a note,
   never a violation (Mastercraft ran 1.2% below the sum of the per-character addons; a character also played on
   another PC, or with Mastercraft off, honestly exceeds it). It does the same for a herb or ore read, its `attempts`
   against the characters' `gathers` rows of that kind; a kind the account page has no figure for (`kill`, `run`,
   `xp`) is never compared.

On the owner's fishing data this gives Scarlet's 343 casts, 295 landed and 316 items, plus 0 casts, 39 landed and 40
items from the two Fishing Log sessions before Scarlet read loot windows: 343, 334 and 356. With a Scarlet session
withheld, its claim counts whole; releasing the session moves every total by exactly the stored differences
(*measured*: the arithmetic of rule 3 run over R0's assignment of rows to the 12 sessions). Where only an addon saw a
trip, its casts include the short cancelled channels Scarlet drops: 23 of 366 (6%) on the owner's data.

Proven through the real upload (2026-11-22, `node e2e/boot.mjs -- node e2e/addon-counts.mjs`: an invented character
whose 12 sessions carry R0's rows per session, one `counts` part per day with the 12 Fishing Log sessions and two
Mastercraft reads): the totals are 343, 334 and 356; with the 224-cast session withheld, 347, 334 and 359, and 343, 334
and 356 again, to the hash, once it arrives; with no Scarlet rows (Professions removed) every claim counts whole,
366, 334 and 360. The scheduled outdated pass (`ingest:redecode --outdated`) decides every claim again from emptied
columns to the same hash and stamps every addon day. A second character of the same install that uploads the same
counts has its 12 character-scope claims `superseded` by rule 4 with the first character named, its 2 account claims are
`reference`, and the first character's hash does not move, with the twin there and after its parts are gone. On the
owner's data the second cast of session 8 starts at 00:13:14.50, inside the session's window; its loot arrives 9.4 s
after the stop. The Fishing Log booked its landed to session 8 and its 3 items to session 9, rule 3 assigns the row to
session 8, and both claims count 0 (*measured*: the binary on the real rows, and the proof uploads session 8 with
those instants).

The same lane holds one invented character per case a verdict can get wrong, each with the totals its history has
(2026-11-22, 58 checks): a cast 0.8 s after a stop and one at the stop, each beside a claim that began 1 or 2 s later,
make 20, 20 and 20, not 21 (seams); a session uploaded in halves counts 13, 10 and 13 beside Scarlet's 10, 10 and 13
(12.5 rounds up), then 20, 20 and 25 with the hash of the whole upload; a claim a catch started beside 3 rows that were
not looted counts its 3 items, 3, 3 and 5 in all; an `ff` session of 7200 s whose last 600 s a `fl` session covers is
residual 460, 414 and 432, 500, 450 and 470 in all; 3 cancelled casts inside a Scarlet session count nothing and the
same claim outside any session counts 3 (7, 4 and 4 with Scarlet's rows); two characters with the same counts and no
rows count once on the lower id, the twin uploaded first (366, 334 and 360), and once the rows arrive on the higher id
the counts move there (343, 334 and 356, the lower id superseded); two installs of nobody's account each count their
own history, and linking both installs to one account supersedes the twin's claims with no new upload. The lane waits
for the character to have no null `reconcile_version` and the rows and claims it uploaded, and compares hashes only
after that.

The same lane holds one invented character per case of the kill, quest and run rules, each with a Scarlet session and
one `counts` part uploaded through the real API (2026-11-22, 71 of the lane's 173 checks; `node e2e/boot.mjs --
node e2e/addon-counts.mjs levels` runs only these). The totals each history has:

- Levels. 120 kills claimed with no Scarlet row count 120. With 100 Scarlet kills at no level beside a claim of 150, the
  total is 150 (100 and 50 residual); 60 at the level beside 100 is 100 (40 residual); 130 beside 100 is 130
  (superseded). Two levels whose `to` are equal, one begun on the sentinel (`from` = `to`), both count, 50 and 70:
  120. One level read twice under one store with `to` an hour apart is 40: the later read counts, the earlier is
  superseded by rule 5.
- Quests. Four claims of one level beside Scarlet's turn-ins of two of them (one booked a level above by the level-up)
  are 3,200: Scarlet's 1,600 and the two others, 700 and 900. The same with the `quests` group withheld, so that only
  `xp_gains` hold the turn-ins, is 3,200. A turn-in with no quest id, 900 at the new level, beside claims of 500 and
  900 at the old one is 1,400, and still 1,400 with the `quests` group withheld, so that the turn-in with no quest id
  is only an `xp_gains` row. Two such turn-ins, 400 at level 9 and 500 at level 10, beside claims of 400 at level 8 and
  500 at level 9 are Scarlet's 900. A repeatable quest claimed at 300 and 500 beside one Scarlet turn-in of 500 is 800.
- Dimensions above 2,147,483,647. A kill claim of level 3,000,000,000 (rejected) and a quest claim of quest
  3,000,000,000 (held) beside a good kill claim of 30 and a fishing claim of 10, 8 and 8, on an install of their own,
  count 30 kills, 0 XP and 10, 8 and 8, and the character's addon day carries the reconcile version. A run claim of
  instance 3,000,000,000 beside a Scarlet run is held, 1 run in all, and the audit runs.
- Runs. A run whose first kill is 433 s after Scarlet's entry is superseded by Scarlet's run, 1 in all, and so is one
  of an instance Scarlet does not have where Scarlet has another run at the same time. Two claims 41 minutes apart
  under one joined Scarlet run are both superseded, 1. With no Scarlet run, two claims 39 minutes apart are 1 and two
  claims 41 minutes apart are 2; one claim alone is 1. An instance entered, left, entered again 15 minutes later and
  left by a direct move to another dungeon, with a claim of the last stay, is 2 runs in all (Scarlet's two), not 3: the
  run ends at the entry of the next one, not where its time inside adds up to.
- Two characters of one install with the same claims, one with no Scarlet rows: its three claims are superseded by rule
  4 and name the other, which counts 50 kills (20 are Scarlet's, 30 residual), 300 quest XP and 1 run beside its rows.
- One record cut in two by rule 4, the copy (lower id) uploaded first and the original holding the Scarlet rows. Two
  wings 35 minutes apart on both, the original's Scarlet run beside the second only: 1 run, not 2. Three wings 20
  minutes apart on the original, the copy holding the first two and no Scarlet row anywhere: 1 run, not 2. A level
  read under two `to` values an hour apart on the original, the earlier reading on the copy: 40 kills, not 80. A
  turn-in with no quest id, 900 at the claim's level, in the original's rows beside a claim of 900 on both: 900, not
  1,800.
- Boss claims (`nf`, window `event`) of 13 characters, each with its total and its verdicts: a Scarlet `B` 0.5 s after
  the kill, and a kill with only a successful `E` end, are `superseded` (rule 2) and add 0, and the run's `bosses` is
  1. A wipe (an `E` end with success 0 and a `B` at the same second) and the kill 4 minutes later give one `boss_kills`
  row, and the claim of the kill is superseded. A `B` with no encounter id 1 s after a successful `E` end is one row
  with the `E`'s id and the claim is superseded. A claim inside a session that has a combat part and no kill of its
  boss is `held`, as are claims 120 s before and 200 s after the one kill of a session and one 30 s after the session's
  end; one 100 s after it, and one 3 hours after, count 1. Two characters of one install with one claim: with the row on
  the higher id, the lower is superseded by rule 4 and the higher by rule 2 (1 boss in all); with no row anywhere and a
  combat session only on the higher id, the lower is superseded by rule 4 and the higher holds it (0). An encounter id
  above 2,147,483,647 is `held` (`unmapped`) and a claim of 2 bosses is `rejected` (`bounds`). With the combat group
  removed the claims no row or session holds count 1 each, and restored every character has its hash again.

The audit passes with every one of these characters held at once, reconcile from blank columns decides all of their
claims again to the same hash, and a second reconcile changes none. The fishing cases of the lane keep their verdict
hashes (every claim, status, counted amount and evidence, without the reconcile version) at versions 6 and 7: 39 of 39
equal to the ones the same lane gave when these rules first ran, and 38 of 38 equal to the ones it gave at version 5
(the herb and ore case came after it). At version 7 all 72 hashes of the cases that existed before the boss cases, and
the 15 of the decoder's boss kill cases, equal the ones the lane gave at version 6.

### Reading

- Scarlet's fact tables and rollups do not change. A total that includes addon data is three terms, each one query:
  Scarlet, addon dated (by the UTC day of the window's end) and addon undated (lifetime). A chart with a time axis has
  no undated term.
- Rates, medians, hour of day, heat, trips, waits, lures, poles and skill-ups per cast read Scarlet's rows only, and a
  page that shows them beside a total that includes addon data says which is which.
- Leaderboards read Scarlet's rows only, until the owner decides otherwise.
- A public figure split by origin is shown only when each side passes the character threshold, or when Scarlet's side
  has no character at all, which gives nothing away; a player's own pages always show the split.
- The flag. `ADDON_COUNTS_PAGES` (`scarlet.addon_counts_pages`, true unless the environment sets it to false) switches these
  reads on for the fishing page, public at `/professions/fishing` and the player's own at
  `/account/professions/fishing`, and for the Fish caught tile, the Kills, Quests and Boss kills figures and the Dungeon runs module of a character page. Only the web controllers read it:
  with it off no page reads `addon_claims`, and the public API never does. On since 2026-11-22, after the owner's 38 Fishing Log claims reconciled in production to the verdicts the design predicts; `ADDON_COUNTS_PAGES=false` turns it off.
- What a page reads. The terms are the `counted` amounts of character-scope `fishing` claims whose status is `counted`
  or `residual`. Dated are the windows other than `life`, undated the `life` windows, one query each. A claim is read
  only while its character has no `addon_days` row with a null `reconcile_version`, so a character whose claims
  reconcile has not decided again adds nothing. A claim with no ruleset (its character has no session) is missing from
  a page narrowed to a ruleset or a server.
- The fishing page. A table of casts, catches and fish gives Scarlet, From addons and Total: `casts`, `landed` and
  `items` of the claims against Scarlet's attempts, looted attempts and the quantity of the item rows that did not
  come out of a container. The head counts the total casts. The figures, ends, waits, hours, trips, poles, lures,
  creatures and the board stay Scarlet's, and the table's columns say which is which. The page has no chart over time,
  so it has no per-day term.
- The threshold. The addon term is read only when at least the scope's threshold of characters have a counted claim in
  it (the larger of the dated and undated counts, never their sum). Under it the page adds nothing, and does not show
  the split alone either: the public API gives Scarlet's figure, and the total would give away the difference. A
  player's own pages have a threshold of 1. Scarlet's side is under the threshold in two ways. With some characters
  under it (1 to threshold − 1), the page adds nothing, as above. With none (no Scarlet fishing row in the scope), there
  is no figure of Scarlet's to give away, so the addon term is shown under the addon side's own threshold.
- A scope whose fishing is only in addon claims. The fishing page shows its table with Scarlet at 0, From addons and
  Total equal, and its head counts the total casts and the characters the claims belong to. Every Scarlet-only figure
  (rates, medians, hours, trips, waits, lures, poles, heat, standings, the board) shows its normal empty state: the
  figures show a dash and the modules that have no rows are not drawn. The note that Scarlet has recorded too little
  does not show beside a table of numbers. A new player's history in an addon is part of the totals from the first
  upload, public pages (under the `scarlet.min_characters` rule above), the account's own page and the character page.
- The account page. `/account/characters` shows, for the signed-in account only, each account-scope `life` claim once per
  origin, store, kind and dims, at its latest read, under a heading by kind: "Mastercraft lifetime fishing" (the addon's
  casts, catches and fish), "Mastercraft lifetime herb gathers" and "Mastercraft lifetime ore gathers" (its attempts).
  Each shows the read's `from_at` and `to_at`, and under it what Scarlet's rows plus the counted claims of the
  characters of the account that carry the read add up to inside `[from, to]`, the sum of rule 7 and of the audit
  (`SumScarletAndClaims`). Scarlet's rows are picked by kind: fishing's `gathers` (casts, those looted as catches) and
  `gather_items` (fish), and for herb and ore the characters' `gathers` rows of that kind, one attempt each. A kind the
  account page has no figure for (`kill`, `run`, `xp`) has no figures and no note, and the page does not show it. A sum more
  than 2.5% above a figure is written under the table as a note, for herb and ore as for fishing. A read is left out
  while a character that carries it has a null reconcile version, and the flag above switches it off with the other
  reads. The public API and the public pages never read it.
- The character page. The Fish caught tile adds the claims' `landed` and says "From addons" with their count, to the
  character's owner, and to a visitor only where `scarlet.min_characters` is 1. A character whose only records are
  those catches shows the Records strip with that tile alone. Most caught, the feats and the standings stay Scarlet's.
- Kills, quests, dungeon runs and boss kills on the character page (`AddonCharacterTerms`, `CharacterOverview`'s
  `addonCounts`). Four terms, one query each, over the character's character-scope claims, read under the same rules as the fishing
  tile (the flag, the null reconcile version, the owner or a threshold of 1; a character-scope read has no other
  threshold). The head figure Kills adds the counted `kills` of the `rxp` `kill` claims that are `counted` or
  `residual`. Quests adds one for each `rxp` `xp` claim that is `counted`: each is a turn-in Scarlet did not record,
  and the claim's XP adds to nothing, since the page shows no quest XP total and no figure here reads
  `quest_events.xp` or `xp_gains`. The Dungeon runs module adds the counted `runs` of the `fdj` `run` claims that are
  `counted` or `residual` to its count, "N dungeon runs in all. From addons M.", and lists only Scarlet's runs, so a
  character whose only runs are the addon's shows the module with that line and no list. The head figure Boss
  kills sits after Kills and is drawn only when above 0: the character's `boss_kills` rows with `counted` true (one
  query, `CharacterOverview`'s `bossKills`, read with the flag off too) plus the counted `bosses` of the `nf` `boss`
  claims that are `counted` or `residual`. Like Kills it says "since <first session>" while nothing is added, and drops
  it when an addon term adds, since the total then reaches back before that day; a character whose only boss kills are
  the addon's shows it with no Scarlet term. The per-run "N bosses" of the run list stays Scarlet's distinct count, and
  the feats that read `sum(bosses)` (Vanquisher and the role boss feats) stay as they are. Each figure shows "From
  addons N" under it when N is above 0. Everything else stays Scarlet's rows only: the leaderboards, the public stats
  and ruleset pages, the per-level charts (XP by level, time per level), XP per hour, the sessions table, the quest
  and kill lists, the week's figures, rates, and the public API. With the flag off these claims are not read either.

### Checks

`php artisan addons:audit {character?}` runs these as SQL over the stored columns, never by running reconcile again,
prints per character and kind the table that explains the total (one line per status, with its claims, what they
claimed, what counts and the rule most of them name, then Scarlet's own term and the total), and fails on any
violation. Scarlet's own term is per kind: for fishing its attempts, looted attempts and item quantity; for `kill` the
`kills` rows with `player` false; for `xp` the larger of the `xp` of its `quest_events` turn-ins and of its `xp_gains`
of source `q`, since the groups of the two can be on apart; for `run` the joined runs. It runs on demand; a schedule that logs breaches comes with the first page that reads statuses. Checks 1
to 5, 8, 9 and 11 run now. Check 6 runs in `e2e/addon-counts.mjs` with the flag on (its default), which compares the
terms on the fishing pages and the character page (Fish caught, Kills, Quests, Boss kills and Dungeon runs) with the totals read from the database. Check 10 is the rule of the
read above. Check 7 is the comparison of the same pages with the flag off before and after a change, which
`ADDON_PAGES_DIR` keeps.

Besides the numbered checks it prints the claims and summed amounts per character, scope and kind, and holds the
checks that need no status: every claim has a carrying part and takes the amounts of the one whose id sorts highest (a
note lists claims whose parts disagree), no character holds one key twice, and no text field names a player but the
character (check 9).

1. Every claim has exactly one status; claimed = counted + superseded + held + rejected + the stored differences.
2. 0 <= counted <= claimed; `superseded`, `held`, `rejected` and `reference` count 0. A `rejected` claim may claim a
   negative amount, so it only has to count 0.
3. Every `superseded` and `residual` claim names its evidence. A claim superseded by rule 2Q names one Scarlet row
   (`rows` with `n` 1), and one superseded by rule 2 or 2B names its row (`row`).
4. No Scarlet row is assigned to two claims of one origin: the assigned ranges of a character and origin are disjoint,
   they name no more rows than Scarlet holds, each range holds at least the rows it names, and a range lies within 60 s
   of its window. The kinds without a time to assign by hold it as follows.
   - `kill`: no two claims of one origin and store that count or are superseded by rows share a level, none of them
     shares a level with a claim rule 4 moved to another character, the count assigned to a claim equals Scarlet's
     count at its level plus its count with no level, and its counted kills are the claimed minus that count, floored
     at 0.
   - `xp`: per character and quest id, the `counted` claims number at most the claims minus Scarlet's turn-ins of it,
     the `counted` XP is at most that of the claims with the smallest `xp`, as many as the claims minus Scarlet's
     turn-ins, and no Scarlet row is the evidence of two claims.
   - `run`: no `counted` claim has a joined Scarlet run of its instance overlapping `[from - 600 s, to + 5 s]` or a
     Scarlet run of any instance overlapping its unpadded `[from, to]`, and no two claims of one instance are under 2,400 s apart when one is `counted` and the other is `counted` or was
     moved to another character by rule 4.
   - `boss`: no `boss_kills` row is the evidence of two claims, and no claim is `counted` within 60 s of a session of
     its character that has a combat part.
5. No two counted or residual dated claims of one character overlap by more than 60 s across origins, except a
   residual of rule 5, which counts only what the higher claims do not cover, or at all within one origin with equal
   dims: the quests and the levels of one origin share windows by design, and are told apart by their dims.
6. Every page total equals Scarlet + addon dated + addon undated, and every breakdown sums to it with the unknown
   bucket.
7. Scarlet's row counts and rollup sums are the same before and after each ship of this work.
8. The account notes of rule 7 are listed (a note, not a violation), and so is each claim assigned more looted rows
   than its `landed` (rule 3, seams), and the `boss` claims held beside a session already decoded at the current
   version, per character and encounter id: they measure whether the addon's encounter id is Scarlet's for a boss
   Scarlet has no kill of.
9. No text field of a claim holds a `First-Last` name other than the character's own.
10. No public bucket shows a per-origin figure from fewer characters than the threshold.
11. No dated claim is counted or residual on two characters of one account with the same origin, kind, `from`, `to`
    and amounts, and no such claim is superseded by rule 4 on a character that has a Scarlet row within 60 s of it
    in favour of one that has none. For `kill`, `xp`, `run` and `boss` the rows beside the claim are the ones rule 4 names, and
    the claim is not superseded on a character that has more of them in favour of one that has fewer.

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
| `kills` | `K`, `LM`, `GP` | combat | npc, by, and the loot rule and group size in force (decoder version 10; null without a `loot` part), and `group_at`, the span of `group_spans` in force (decoder version 11; null while solo and without a `social` part), and `corpse_key` (decoder version 12; null on a line from before the key and for a player) |
| `deaths` | `D`, `DL`, `DR` | combat | map position, killer kind, killer creature and its id, killer level, spell, the killing blow's event and environmental type |
| `death_hits` | `D`, `DR` | combat | one hit of a death's recap, joined to its death on character, session and time: position, spell, amount, source, event, environmental type, school, health, seconds before, absorbed, resisted, blocked, flags. Came with decoder version 8 |
| `spell_casts` | `C`, `CH`, `CI`, `F` | combat | one row per spell and level the session cast: casts, cast time, casts inside fights, channel time, interrupted casts and the longest run of the spell with no other cast between. Came with decoder version 13; one row per level and the last four columns came with 14 |
| `meter_totals` | `DM`, `DP`, `Z` | combat | one row per damage meter, level and place (world, dungeon, raid, battleground): its fights' totals summed, the best single fight, the fights and their seconds, and the group's total over the fights that had `DP` lines. Came with decoder version 13; the place and the group columns came with 14 |
| `meter_spells` | `DS` | combat | the top spells the client's damage meter listed after fights, summed per meter, spell and level. Came with decoder version 14 |
| `state_times` | `A`, `AR`, `F`, `MO`, `MP`, `PD`, `PT`, `TW`, `G` | combat, movement, build | time spent in a state, by level, and the part of it inside fights: your auras, others' buffs on you, debuffs, auto repeat, totems, fights (combat), mounts and tracking (movement), pets, pet happiness and the weapons held (build). A removed group deletes its own kinds. Came with decoder version 14 |
| `item_flows` | `I`, `DI`, `C`, `CH`, `AR` | loot | units that went into or out of the bags, by item, direction, where, and for items with no place the spell cast at the time. Came with decoder version 14 |
| `class_tallies` | `K`, `D`, `F`, `C`, `A`, `TW`, `SR`, `U`, `R`, `SH` | combat | counts of sequences the class pages read: the spell of a kill, what opened a fight, what a seal or a totem came to, shape-shifts, deaths and the spells before them. Came with decoder version 14 |
| `dungeon_runs` | `Z`, `E`, `B`, `D` | combat | instance, seconds, bosses, deaths, group size (the run rule of OVERVIEW.md). `bosses` counts the distinct bosses of the run's `boss_kills` rows (decoder version 15; before it only `B` lines) |
| `boss_kills` | `E`, `B`, `Z` | combat | one boss kill: encounter id, name, the dungeon run it fell in, and whether it came as a `B` line or a won `E` end. One list per session from both lines, each kill once (decoder version 15) |
| `quest_events` | `Q` | quests | quest, accepted, turned in or abandoned, xp, copper |
| `money_changes` | `M`, `S` | economy | earned, spent, balance (transfers between own characters count as neither) |
| `loot_opens` | `LO`, `LM`, `C`, `CH`, `P` | loot | source kind and id, first read or not, loot rule, threshold, group size, what the window was read for (loot, skin, herb, ore, fish, bag or other), the item entries, the map position (decoder version 10), the span of `group_spans` in force as `group_at` (decoder version 11), the corpse key (decoder version 12) |
| `loot_drops` | `LO`, `O`, `C`, `CH` | loot | source, item, quantity, loot rule, group size, what the window was read for, the corpse key (decoder version 12). A window has one row per item, its stacks added (decoder version 10) |
| `peer_opens`, `peer_drops` | `LW` | loot | the loot windows that group members who run Scarlet read and sent: the same keys as `loot_opens` and `loot_drops` without a position, and who (a pseudonym), how late the message was, and the corpse key. Their own tables, so nothing that counts `loot_opens` counts them (decoder version 12) |
| `group_loot` | `LT`, `LW` | loot | what another member of the group received: kind (looted or pushed), who (a pseudonym), their class, item, quantity, loot rule, threshold, group size, and the source of the window that member sent for the item (decoder versions 11 and 12) |
| `loot_rolls` | `LD`, `RW`, `RL` | loot | one row per player per roll: the roll's number in the session, item, who (own name or a pseudonym), class, roll type, number, outcome, the uploader's time to decide, loot rule (decoder version 11) |
| `group_spans` | `GP`, `GM`, `GL`, `SL` | social | a stretch in which the group did not change, with no names: size, raid, members known, their classes, roles, levels, guild mates and friends, and whether the uploader has Skinning. `kills` and `loot_opens` carry the `at` of the span in force as `group_at` (decoder version 11) |
| `boss_loot` | `EL` | loot | an item someone in the uploader's group received from a boss encounter: encounter id, item, quantity, receiver (a pseudonym outside the social group) and class. The notice reaches every member of the group, so each uploader has a row and a page counts a drop once: by encounter, item and a 10 minute bucket, taking the largest count any one uploader has |
| `skill_ups` | `SK`, `SL` | professions | skill, rank |
| `crafts` | `CR`, `CB`, `RD`, `TS`, `SL`, `CS`, `G` | professions | item, quantity, recipe, profession, the recipe's difficulty, the skill before and whether it went up, the gap since the last craft of the profession. A lure or weapon enchant (a `CR` with no `CB` for the main hand item) is not a craft (decoder version 9) |
| `gathers` | `C`, `CH`, `LO`, `SL`, `F`, `G`, `P` | professions | one gathering attempt (fishing, herbalism, mining, skinning): seconds, whether the loot window came and its source, the skill before and whether it went up, the gap since the last attempt of the kind, the lure and pole of a fishing cast, whether the character was in combat, the map position (decoder version 9). A fishing cast cancelled under 2 s writes no row |
| `gather_items` | `LO`, `O`, `C`, `CH`, `SL`, `P` | professions | one item a gather gave, and the items of a caught container opened later (decoder version 9) |
| `path_points` | `P`, `W` | movement | map, x, y, and the mode of the `W` stretch whose time covers the point (null when none does: a stretch under a yard, a point written while standing). The mode came with decoder version 16 |
| `movement_stretches` | `W` | movement | mode (`f` running, `w` walking, `m` mounted, `s` swimming, `a` flying), yards, seconds, jumps; `jumpAt` is not kept |
| `flights` | `T` | movement | a flight path taken, from its `T 1` to its `T 0`: where it began and where it landed (map, x, y and the world `u, v` of each end) and how long it took. A flight still in the air when the session ends has no row. Came with decoder version 16 |
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
| `loot_items_daily` | 1 day | source, read, loot rule, group size, zone, item | windows that list the item, quantity, characters; one character's own first reads, read by What the looter kept |
| `loot_windows_character_daily` | 1 day | source, read, loot rule, group size, character | windows, item rows and their squares; one character's own first reads, read by What the looter kept; the arms of the rule and size charts read `loot_windows_character_counted` |
| `loot_receipts_daily` | 1 day | source, read, loot rule, group size | receipts that came from a corpse (`loot_drops` via `receipt` with a source window), quantity, characters; read by What the looter kept |
| `loot_windows_unkeyed_daily`, `loot_items_unkeyed_daily`, `loot_windows_character_unkeyed_daily`, `loot_freeforall_items_unkeyed_daily`, `kills_solo_attributed_daily` | 1 day | source, read, loot rule, group size, zone (items add the item; the character table the character; the free-for-all table the character and item, no zone or rule; solo kills the npc) | the counts of `loot_items_daily` and `loot_windows_character_daily` over rows with no `corpse_key`, for the windows, items, free-for-all items and solo kills the pages read: the first-read rule for windows from before the key. They replace `loot_windows_daily`, `loot_freeforall_items_daily` and `kills_solo_daily`, dropped by 2026_12_23. `kills_solo_attributed_daily` (npc; kills, characters) counts the solo kills with no key whose `credit` is 1 or 2; it replaced `kills_solo_unkeyed_daily`, which counted `credit` 1 only, dropped by 2026_12_27 |
| `loot_windows_keyed_daily`, `loot_items_keyed_daily`, `loot_windows_character_keyed_daily`, `loot_kills_keyed_daily`, `loot_kill_items_keyed_daily` | 1 day | as above; kills by npc, rule and size | plain tables, not aggregates: a corpse with a key counts once however many characters read it (docs/LOOT.md, Units of counting, rule 7), which no sum of daily buckets gives. `RebuildKeyedLoot` rebuilds the days of every refresh and a day on each side at its end, under its lock, and `loot:rebuild-keyed` rebuilds every day daily at 04:20 UTC. The views `loot_windows_counted`, `loot_items_counted` and `loot_windows_character_counted` read a keyed table with its unkeyed twin and a `keyed` column |
| `group_share_receipts` | 1 day | loot rule, group size, character | a plain table like the keyed ones: the receipts of "Your share of group loot" (a counted receipt in a group of 2 or more under a rule that owes an equal share, from a creature or object source or won by an own roll from 90 s before the receipt to 5 s after it), `receipts` by day, rule, size and character, so the page sums the days and counts the characters against `scarlet.min_characters`. `GroupShareReceipts` fills it inside `RebuildKeyedLoot`, for the same days and under the same lock, and `2026_12_21_000100` fills it from the rows stored then; the roll is looked up on any day, so the edge of a range cannot cut it from its receipt |
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
| `gather_items_daily_source` | 1 day | kind, source (the skinned creature, the herb or vein object, the bobber or school; null for what a container held), container item, item | item rows, quantity, rank as a percentile sketch |
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
fact table. The drop aggregates carry no ruleset, so `DropRates` and `ItemSources` take none. A loot table that counts
windows or drops over every rule takes its free-for-all part from the one-reader arm of each source, as the creature
page does (docs/LOOT.md, Units of counting, rule 6): `SourceRates`, `ItemSources`, `ItemZones`, `LootSources`,
`LootZones`, `LootFigures`, `LootItems`, `LootRuleSizes` and `GroupFigures` read the arms of all their sources at once
through `CountedLoot`. Heat cells are zone-map
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

Aggregates refresh on a policy and once more, for the affected time range, after a decode or a removal. Several
refreshes over one aggregate cannot run at the same time: TimescaleDB holds the aggregate for a whole refresh window,
so the callers wait for the refresh running instead of failing (`SQLSTATE 55P03`). Removing a
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
| Addresses that called the API | no | no | Kept 30 days for the operator's `/admin/api` page, then deleted (Admin) |
| The app build and system an install last sent | no | no | The install's User-Agent, for the operator's `/admin/clients` page (Admin) |

## Admin

`/admin` is the operator's view of the whole site: totals and trends, every character, who calls the API, which app
builds run, who reaches the limits, the database and the ingest pipeline. Pages: `/admin` (overview),
`/admin/characters`, `/admin/api`, `/admin/clients`, `/admin/limits`, `/admin/database` and `/admin/ingest`.

**Clients.** A signed request saves its User-Agent on the install (`installs.client`, printable ASCII, 96 characters
at most) with `last_seen_at`, at most once in five minutes, so a client that changes its agent on every request adds no
writes. The app sends `scarlet-app/<version> (<os>)` and the command line `scarlet (<os>)`, whose crate version is not
the release; older builds send `scarlet-upload` with no version. `/admin/clients` counts the installs
seen within 30 days by build and by system against the newest release (`ShowAppDownload`), the 12 largest and the rest as Other, the addon versions of the
sessions decoded within 30 days by characters and sessions, and lists the 25 installs seen most recently.

**Limits.** `/admin/limits` shows the limits in force (`scarlet.rate`, `scarlet.page`, `scarlet.upload` and
`PublicApi::BAD_TOKENS_PER_MINUTE`), requests and refusals per day with the `429` answers apart (`api_calls.limited`),
the busiest addresses today with their average requests a minute from their first request to their last, the addresses
answered `429` most within 30 days, today's upload bytes per install against the daily cap and the installs with the
most characters against theirs. It keeps its answer 30 seconds. `limited` counts from 23 Nov 2026; a 429 before that
day counts only as refused.

**Characters.** `/admin/characters` lists every character, public and private, the most recently seen first, 50 to a
page (`App\Actions\Admin\ListCharacters`). `?q=` matches part of the name (`name_key`, which the trigram index serves),
or `#12` the characters of account 12; `?show=public` or `private` keeps one visibility. A row shows the level, race
and class, realm, account, installs, decoded sessions, played time, first and last seen and a plausibility flag; a
public character links to its page. The list is read on each request, not cached.

**Who sees it.** An account opens it when one of its sign-ins is listed in `scarlet.admins`, which `SCARLET_ADMINS`
sets as `provider:subject` pairs separated by commas (`google:1234`). Email cannot be the key: no email is stored
(Security, Accounts). The check runs on every request against the account's identities, so editing the variable
grants or revokes at once, with no migration. Everyone else gets `404` with no cookie, the answer of a path that
does not exist (`AdminHiddenFromVisitors`): a visitor with no session gets it before the session starts, and any other
refusal loses the session and XSRF cookies. The account page shows each linked sign-in's
`provider:subject`, so the operator can copy theirs. The pages are private (`Cache-Control: private, no-store`,
`noindex`), stay out of the edge cache and cap each statement like every page.

**API calls.** `App\Http\Middleware\RecordApiCall` counts every request to `/api/v1` that matched a route, after the
answer is sent, into `api_calls`: one row per UTC day, surface (`public` or `upload`), endpoint (method and route path),
address bucket (`ClientAddress::bucket`), account (from the API key, or the install's account) and install. A row holds
the requests, the refused ones (status 400 and above, except 404), the limited ones (status 429, counted as refused
too) and the first and last time. A `200` or `304` answer
that names a character also counts on that character's row of the day, which has no address, account or install, so
walking many characters adds one row per character, not one per caller and character. Both writes run in one
transaction capped at two seconds, and a failed write is reported without touching the answer, which has already gone.
Answers the edge cache serves never reach the origin, so the counts are the origin's. `api-calls:prune` keeps today and
the 30 full days before it, daily.

**Numbers.** Each page reads one Action under `app/Actions/Admin` and keeps its answer 60 seconds. Row counts of big
tables come from the planner's estimate (`approximate_row_count` for hypertables, `reltuples` for the rest), never
`count(*)`; sizes, compression, jobs, cache hits, connections, vacuum and index use from the PostgreSQL and
TimescaleDB catalogs.

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
| `/search` | Characters that are public, creatures, items, objects, zones, quests. An object is a hit when it has a name and a loot window of loot, herbs or ore counted under the character threshold (docs/LOOT.md, phase 3; comp `search.html`): the line is its name, `Object` and how many times it was opened. Its score is the windows. The bobber of a fishing line is not one |
| `/c/{name}` | A character: overview, levels, sessions, deaths, dungeons, map paths, talents (the latest build of each talent group drawn on its tree, or listed by tab while no layout is stored; the order the points were taken in; respecs; talent facts: characters with the same build at the same level, respec count and gold, the least picked talent among the class and spec, the level of the first build with 31 points in one tree, the longest and median wait to spend a point, and picks refunded within ten minutes, each with its n). A Lifetime chapter, shown when a read is stored, gives the client's own totals of quests completed, titles known and zones explored (the latest `QB`, `KT` and `EB`) and the instance locks that still hold with their reset time (`IS`); a character without those lines shows no chapter. The hero map redraws the walked cells in the order they were first walked (a day at a time, from the daily heat aggregate), with the Level figure counting up and each death appearing at its day; a Replay button runs it again, and a viewer who prefers reduced motion gets the finished map. Skills lead with the profession ranks over played time, placed from the hours played; play time is the last 21 days and the hour of the week, drawn in the viewer's time zone from the hourly aggregate and in UTC without script. Public when its owner says so; the owner sees the private parts too |
| `/c/{name}/battlegrounds` | The scoreboards of a character's ten latest battlegrounds that have `BO` rows (`battleground_scores`): every player the client read, by faction, with killing blows, honorable kills, deaths, honor, damage and healing, under the zone and the result of the match when the `pvp` group holds its `BM` lines (`battleground_matches`). A match is one uploader's rows that share `match_at`; two uploaders of one match each have their own. Visible exactly when the character's page is. Other players' names follow the privacy rule: the owner sees them, a visitor sees a name (linked to its page) only when that player's own character is public, and every other row says "a player". The character page links it when the character has a match with a scoreboard |
| `/c/{name}/{class}` | A character's page for its class, with the class's slug (Class pages, above; the comp is `docs/comps/class.html`). Built for all nine classes: a head of the class's own models with a callout on each, six figures, the charts and modules the class is known by (the nine pages, below), a module for each of up to three talents the build holds, what the class does for the party, the character against other characters of its class and level bracket once 20 of them are stored (`class_standings`), and the feats of its class and its build's talent tree. A class with no builder, a slug that is not the character's own class, an unknown character and a character private to the viewer answer 404. Visible exactly when the character's page is. The character page links it, and shows a band of up to five of its figures, read from the row the standings job stored (`class_bands`), once the character has a counted cast |
| `/talents`, `/talents/{class}/{spec}` | Public. Every spec with the characters that play it, and one spec: pick rate by node (a heat on the real tree when a layout is stored, a table always), the most common order of points, builds by level bracket, and builds and outcomes (the ten most held builds with the level-ups, median time to level, deaths and dungeon runs that happened while each was held, from one snapshot to the next; `?level=10-19` to `60`; `ruleset` and `server` as on `/stats`). Each character counts once, by its latest counted build read inside the bracket, and every figure prints its n. `/stats` carries a Talents block with the most played specs |
| `/creatures/{id}`, `/items/{id}`, `/zones/{id}`, `/quests/{id}` | Result pages: kills and killers, drop rates by loot rule, where it is, levels. An item page also lists the boss encounters that dropped it (`boss_loot`) and the recipes that make or use it (`recipes`, `recipe_reagents`). A creature page, and an object page, add two modules under the drop table (docs/LOOT.md, phase 4; comp `creature.html`): Drop rate by loot rule and Drop rate by group size under the rule the page shows (`?rule=`, else the rule with most corpses). Each is one row for every item with 10 drops or more and one mark for every rule (or size) on a shared 0 to 100% axis: the dot is the rate, the line its 95% range, `k of n` beside it, hollow from 10 to 29 corpses and not drawn under 10. The windows with a corpse key count whole, each corpse once; the windows with no key of a free-for-all arm that two or more characters contributed to count their one reader, and the arm carries `one reader` only then. The first two arms drawn (the most corpses) are compared: the row says `no difference found`, or marks the difference in points with its Newcombe range and the tags of both arms when both arms have 30 corpses and the range stays off zero after 0.05 is divided by the number of items in the chart. A personal-loot arm is drawn and never compared. When fewer than two arms are drawn the module says which rules, or which sizes with their corpses, were recorded. A creature or object page also links `/loot/groups?source=`. An item page adds Rolls in groups (the uploaders' own rolls of the item under the rules that roll: need, greed and pass as a rate with its counts, a bar from 10 rolls, and how many of the rolls everyone passed) and Who received it by class (what other members of a group received by class, from `group_loot` and the class their line named, and what the uploaders received, with each class's share of the receipts from 10 receipts and the rolls of need or greed it made on the item and won; one uploader's view of its group, so a group with several uploaders counts once for each). The two sit side by side under Where it drops, or alone when only one has data; each is left out when fewer than `min_characters` characters contributed (comp `item.html`). A zone page adds Looted here under Creatures met here (docs/LOOT.md, phase 3; comp `zone.html`): the five creatures looted most often in the zone with their corpses looted and most common item, the five items that dropped most often with their best source (each rate with its 95% range, or its counts under 10 corpses), and the link `All loot in {zone}` to `/loot?zone={id}`. It counts every ruleset and every loot rule but personal loot, so the ruleset filter of the page does not narrow it, and fishing is not in it. The module is left out while the zone has no loot |
| `/items/{id}` loot modules | Public (docs/LOOT.md, phase 3; comps `item.html`, `container.html`). Under the item's drop modules: **By zone** (a third) lists the zones where creatures were looted and held the item: Zone, Dropped (corpses that held it) and Per 100 corpses, the share of the zone's creature corpses looted for the first time that held it, with its 95% range after it, or `k of n` while the zone has under 10 corpses; the ten zones with most drops, by Dropped; every ruleset and every loot rule but personal loot. **Where it was looted** (w8) marks the map with where the character stood at each of the newest 600 windows that held the item, as the creature and object pages do. Both are left out for an item no window held. An item that is a container (windows read as `bag`) adds **What it holds**, the object page's drop table over what the container gave: Item, Drop rate with its range, Range, Opened and Per drop, without the rule control. Gathering sources keep Where it's gathered, and fishing windows are in none of them |
| `/loot` | Public (docs/LOOT.md, phase 3; comp `loot.html`). The loot index, over every ruleset and every loot rule but personal loot. The strip: corpses looted, skinned, sources (creatures, objects and containers), items, items of uncommon quality or better, characters; the facts line says how many by how many characters between which days. `?view=` picks the table: `creatures` (default), `objects`, `containers` or `items`. The Sources table (creatures, objects, containers) has Source, Zone (where most of its windows were), Level, Corpses looted (Opened for an object or a container), Items seen, the most common item with its rate, 95% range and counts, and Characters; a source with fewer than 10 windows shows the item's counts only. Level is the creature's level for a creature and the looter's level for an object or a container. The Items table has Item (icon, quality colour), Dropped, Sources, Best source with its rate and range, and the zone it dropped most in. Filters are query parameters of one form, no script: `q` (a name or id of a creature, object or item, or an item a source dropped), `zone`, `min` and `max` (a level range that overlaps the source's level), `quality` (items of that quality or better; a source has one of them), `sort` (`windows`, `items`, `level`), and `cursor` (25 rows a page). Under the table, Zones (windows by zone, each linking `/loot?zone={id}`) and Loot rule and group size (windows by rule and size, each linking `/loot/groups`). Fishing is a note under the table with one link to `/professions/fishing`: the bobber (object 35591) and every window read for fishing are in no table or figure. A zone filter narrows the strip too |
| `/objects/{id}` | Public (docs/LOOT.md, phase 3; comp `object.html`). What a chest, herb, vein or other object gives, on the creature template without kills and deaths. `h1` is the object's name, or `Object 649094` until the addon names it. The facts line says where it was opened, at which looter levels and what it gives. Figures: opened, items, characters, zones. Modules: What it gives (the drop table, over the read the object has most windows of, with the loot rule control for a chest) and Where it was opened (a map of the windows' positions, the newest 600). 404 for an id with no window. `/objects/35591` goes to `/professions/fishing` |
| `/loot/groups` | Public (docs/LOOT.md, phases 4 to 6; comp `groups.html`). Loot rules and group size over every ruleset. The strip: corpses looted, loot rules seen, largest group, creatures under two rules, rolls, groups recorded. Modules, each with its empty state: **What a corpse held, by loot rule** (items per corpse under two rules, `?a=` and `?b=`, else the two rules with the most corpses on creatures seen under both; only sources with 10 corpses or more under each count, each weighted n1 x n2 / (n1 + n2), with the difference, its 95% range, and how many sources and corpses it rests on; the figure over all sources is never shown); **What the looter kept** (receipts that came from a corpse over the items it held, by rule and size, `other creatures` on a size seen only on sources no other size has); **Your share of group loot** (own receipts from corpses, from `group_share_receipts`, against `group_loot` rows of kind looted in the same rule and size, with 1 over the size beside it); **Rolls** (the uploaders' own need, greed and pass by item quality and rule, and items at or above the threshold per 100 corpses in groups); **Kills an hour by group size** (member kills over member hours from `group_spans` and `kills.group_at`, from sessions with kills); **Group composition** (need share by class and armour type, skins per corpse by whether the uploader has Skinning, the only profession a group records, kills an hour with and without a tank and a healer in groups whose members were all seen, no names). `?source={kind}{id}` (`c255851`) narrows every module that can be narrowed to one source and says in one line which cannot (share, rolls, kills an hour, composition), and `?marks=1` adds the developer list of every marked difference between rules or sizes among the 150 creatures and objects with the most corpses under two rules or two sizes. Reads the same `LootGroups` as `/public/loot/groups` |
| `/spells/{id}` | Public. What the client says a spell is (`spells`: cast time, range, cost, passive, description, icon id) and, for a recipe, what it makes and needs. A spell with a name, facts or a recipe has a page. The talent tables on a character's talents page and on a spec page link each talent's spell here; the drawn tree does not link yet |
| `/encounters/{id}` | Public. The items a boss encounter dropped, from `boss_loot`. A drop is counted once per uploader group: rows of one encounter and item within 10 minutes are one drop, and the uploader who saw most of them gives the count. An encounter has no name in the data, so the page says `Encounter <id>` |
| `/map` | The world map: one seamless map, zoomable down to a zone, with heat from paths, deaths and kills (see Maps). `?character=Name-Surname` is one character's own map (Personal maps), for every viewer who may see that character's page and a 404 for everyone else, noindex |
| `/account/map` | Private, signed in only: the personal map of every character of the account, private ones included (Personal maps). Declared with `->defaults(ScopeRequest::OWN, true)` and `cache.headers:private;no_store`, noindex, not in the sitemap |
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
`?rule=`, `?level=`, `?layer=`, `?sessions=`, `?view=`, `?zone=`, `?min=`, `?max=`, `?quality=`, `?sort=`) point at the unfiltered page. `/stats?ruleset=x` names `/rulesets/x`,
and a character page names the character's stored spelling (`/c/Name-Surname`).

`/sitemap.xml` is a sitemap index. It lists `/sitemaps/pages.xml`, which holds the fixed pages and a ruleset, board
and talent spec page for each that exists, and one file per 25,000 rows for each kind: public characters
(`/c/{name}`, with `lastmod` from `last_seen_at`), creatures, items, objects (those the search finds), zones and quests. `robots.txt` disallows
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
- **Spell icons** (owner, 2026-11-22). The class pages' rows show the game's spell icons, stored as the item icons are: once per icon
  file, with a map from spell id to file. `tools/scene-art/spells.mjs` reads the client's `SpellMisc` table (`SpellID`,
  `SpellIconFileDataID`) and writes `spells/spells.json` and `spells/icons/<file id>.png` to the `maps` disk: 31,836 spells share
  2,342 icon files. `AppSupportSpellIcons` and `ItemIcons` are two folders of `AppSupportIconFolder`, which holds the caching,
  and `/spell-icons/<file id>.png` is served as `/item-icons/` is. The folders stay apart, so an icon file that is both an item's
  and a spell's is stored twice. `<x-item-icon :spell="$id" />` draws a spell's icon from its id and an empty square when the
  icon is missing; a row the game has no icon for passes no id and shows no square.
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
  (local environment only) shows a scene's head over invented facts, for the professions, the camp and the nine classes. The scene builds with three 0.187, which has dropped the soft shadow filter the approved prototype was tuned on: shadows
  use the plain filter.

What the layer adds to each page:

| Page | Addition |
| --- | --- |
| `/stats`, `/rulesets/{slug}` | The hero and a tally of seven figures with glyphs (characters, highest level, hours, kills, deaths, quests, dungeon runs). **Records across every character**: the deadliest creature, where most characters die, the most dangerous level, the slowest level, the busiest hour, the most run dungeon, the most trained profession (professions only, not weapon skills), the most used emote across characters and the share of experience from quests at the highest level, each from a figure the page already reads, each left out when its figure is missing. One death is not a record: the creature and the place need two. **The next round numbers**: kills, hours, quests, deaths, dungeon runs, jumps and gold earned read off a rule whose right end is the next of 10, 25, 50, 100, 250, 500 and so on. **The climb**: the median played time to each level as a ridge from level 1 to the cap, with a flag at each level where characters stand now (the published reach counts, level by level: reached L less reached L + 1) and a dotted line from the end of the ridge to the cap. The dotted line is not data and says so; a flag at a level past the ridge stands on it. A flag is one level's own count, so one under the character threshold is not drawn. The climb is left out until the curve has two levels. **Classes played** and **Horde and Alliance**: characters by class and by faction (`Rollups\Roster`), a class under the character threshold left out. The modules sit in chapters: Levelling, Danger, Dungeons and skills, Who plays, Gold and time, The world. The tally replaces the old figures row; the count at the level cap stays in the level cap table |
| `/leaderboards` | The hall: every board with the character that leads it now and how many characters are on it, for the chosen ruleset and server. It replaces the redirect to one board, and the board picker's first choice, "All boards", leads back to it |
| `/leaderboards/{board}` | The first three rows of the first page stand on a podium of gold, silver and copper plinths; the table lists the rest, and later pages are the table alone. New boards: `most-kills`, creatures killed, and `most-jumps`, jumps, both counted from the first recorded session, and `most-time-by-the-fire`, seconds near a campfire (`camp_stays_daily_character`, counted stays), and `most-fish-caught`, `most-herbs-gathered`, `most-ore-mined` and `most-creatures-skinned`, looted gathering attempts (`gathers_daily_character`, counted rows), and one board of crafts for each crafting profession, `most-alchemy-crafts` to `most-first-aid-crafts` (`crafts_daily_character`, counted rows) |
| `/c/{name}` | The head carries the class colour on its dot, a glyph on each figure and a rule from level 1 to the cap with the character's level marked. Record tiles carry glyphs. **Standings**: the character's place on every board it is on, among public characters of every ruleset, with gold, silver and copper for the first three places, each linking to its board; shown for a public character only, since a board lists no other. `GET /public/characters/{name}` carries the same list as `standings`. Records tiles for **Jumps** and the **Favourite emote**, and a bar of **distance by mode** (running, walking, mounted, swimming, flying, in yards): each shown only when the character has rows for it, so an owner who stopped sharing `movement` or `social` loses nothing. **Feats**, 258 feats of five tiers each (below): the 74 general feats and, for every class, its class, talent-tree and role feats. The page shows the six rarest the character has earned in two rows of three, and "View all" opens a dialog with every feat the character can hold: those earned first, then those not yet earned. **Pinned feats**: the owner pins up to three feats (`characters.pinned_feats`, a list of feat keys in pin order, limit `Character::MAX_PINNED_FEATS`) with a toggle on each card in the dialog, whose header shows "2 of 3 pinned". Pinned feats show for every viewer in a row of their own above the six, in pin order, in the same three-column grid. A pinned feat is not also one of the six: the next feat in the order takes its place. In the dialog a pinned feat stays in its group. A fourth pin is refused until one is unpinned. A pinned feat with no row for the viewer (a gold feat while the economy is hidden, or a feat not stored) is skipped. The toggles are a Livewire component for the owner only (`?as=visitor` shows none); visitors get plain HTML with no Livewire. The dialog stays open at its scroll position while a pin saves. A visitor's page is edge-cached for a short time and the API answer for 30 seconds, so a visitor may see the old pins until they expire. The summary counts feats (`{held} of {total} feats`) and, by the tier each holds now, how many are legend, scarlet, gold, silver and copper. A feat not yet earned shows what copper takes; one with a tier shows what the next takes, and a track of five segments shows how far up it is. The order is rarity: the fewest characters holding the tier this character holds, ties by the higher tier, then the order of the table below; the feats not yet earned follow, closest to copper first. Rarity orders the list and is never shown or published. The gold feats (Earner, Spender, Tycoon, Windfall) are listed only when the economy is shown. `GET /public/characters/{name}` carries the same list as `feats`, each with `pinned`; a pinned feat keeps its place in that list |

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
Summit, Unbroken, Artisan, Explorer, Cartographer, Questing and Tracker stop where the game does: legend is the level
cap, five professions at 300, or most of the zones, areas, quests and rares of the world. No legend asks for more than
one character can reach (owner, 2026-11-22: "capped to possibility"). Cartographer counts the discoveries that give experience: the client's
AreaTable has about 585 such areas in the zones of the two continents, and a discovery gives none at the level cap or
far above the area's level, so legend at 500 asks for the areas of both factions' zones found while leveling. Artisan and Master count
Cooking, First Aid, Fishing and the two primary professions with the highest ranks, each at most 300, and leave out
Lockpicking and Poisons. `skill_ups` keeps the highest rank of a profession the character dropped, so a dropped
primary can stand in for a held one, but the count never passes what two primaries reach. No feat counts yards flown: the realms have no flight a player
steers (taxis are not movement stretches), so no character could earn one. Delver, Crafter, Angler and Gatherer are
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
| Cartographer | areas discovered that gave experience | 5 | 100 | 250 | 400 | 500 |
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
| Artisan | ranks of the five professions one character can hold | 75 | 300 | 750 | 1,350 | 1,500 |
| Master | of those five, the professions at rank 300 | 1 | 2 | 3 | 4 | 5 |
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

The other 184 feats belong to a class, a talent tree or a role, and a character only ever sees the ones that apply
to it. Applicability is by class: the feats of the character's class, the feats of its class's talent trees and the
role feats of the roles its trees play. A Mage never sees a Warrior feat, and a feat that does not apply is not
stored at all. A session plays the tree of its login spec: the session's first talent read names its spec tab, the
tab with the most points (Druid Feral is both Tank and Melee). Class and tree casts count in every session of the
class, whichever spec that session played. The role feats count only the sessions whose tree plays that role, and
their hours, meters, runs and kills come from those sessions alone. Casts match spells by their English name in the
`names` table; `feats:spells` lists, per cast rule, the spell names that table does not hold, so a spell the server
renamed shows up. `feats:proof <tables>` checks every rule against the client's own spell tables: which spells the
client lets each class learn and cast, and the fastest the legend tier could be earned from them. [FEAT-PROOF.md](FEAT-PROOF.md)
is its output.

| Feat | For | Counts | Copper | Silver | Gold | Scarlet | Legend |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Battle Cry | Warrior | casts of Battle Shout | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Charger | Warrior | casts of Charge, Intercept | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Executioner | Warrior | casts of Execute | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Stance Dancer | Warrior | casts of Battle Stance, Defensive Stance, Berserker Stance | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Mortal Striker | Warrior, Arms | casts of Mortal Strike | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Overpowering | Warrior, Arms | casts of Overpower | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Bloodletter | Warrior, Arms | casts of Rend | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Weapon Master | Warrior, Arms | hours played with Arms as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Bloodthirsty | Warrior, Fury | casts of Bloodthirst | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Whirling | Warrior, Fury | casts of Whirlwind | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Seeing Red | Warrior, Fury | casts of Death Wish, Recklessness, Berserker Rage | 5 | 100 | 500 | 1,500 | 6,000 |
| Berserker | Warrior, Fury | hours played with Fury as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Sunderer | Warrior, Protection | casts of Sunder Armor, Devastate | 50 | 2,500 | 12,000 | 40,000 | 200,000 |
| Vengeful | Warrior, Protection | casts of Revenge | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Blockade | Warrior, Protection | casts of Shield Block, Shield Wall, Shield Slam | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Shieldbearer | Warrior, Protection | hours played with Protection as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Judicator | Paladin | casts of Judgement* | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Benediction | Paladin | casts of Blessing of *, Greater Blessing of * | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Last Resort | Paladin | casts of Lay on Hands | 1 | 10 | 30 | 80 | 200 |
| Untouchable | Paladin | casts of Divine Shield, Divine Protection | 1 | 30 | 125 | 400 | 1,500 |
| Radiant | Paladin, Holy | casts of Holy Light | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Quickening | Paladin, Holy | casts of Flash of Light | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Purifier | Paladin, Holy | casts of Cleanse, Purify | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Lightbringer | Paladin, Holy | hours played with Holy as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Bastion | Paladin, Protection | casts of Holy Shield | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Lawbringer | Paladin, Protection | casts of Hammer of Justice | 5 | 100 | 500 | 1,500 | 6,000 |
| Consecrator | Paladin, Protection | casts of Consecration | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Templar | Paladin, Protection | hours played with Protection as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Sealbearer | Paladin, Retribution | casts of Seal of * | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Hammerfall | Paladin, Retribution | casts of Hammer of Wrath | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Zealot | Paladin, Retribution | casts of Repentance | 5 | 100 | 500 | 1,500 | 6,000 |
| Crusader | Paladin, Retribution | hours played with Retribution as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Playing Dead | Hunter | casts of Feign Death | 5 | 100 | 500 | 1,500 | 6,000 |
| Trapper | Hunter | casts of * Trap | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Marked Prey | Hunter | casts of Hunter's Mark | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Beast Tamer | Hunter | casts of Tame Beast | 1 | 10 | 40 | 120 | 400 |
| Unleashed | Hunter, Beast Mastery | casts of Bestial Wrath | 1 | 30 | 125 | 400 | 1,500 |
| Intimidating | Hunter, Beast Mastery | casts of Intimidation | 5 | 100 | 500 | 1,500 | 6,000 |
| Pet Mender | Hunter, Beast Mastery | casts of Mend Pet, Revive Pet, Feed Pet | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Pack Leader | Hunter, Beast Mastery | hours played with Beast Mastery as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Deadeye | Hunter, Marksmanship | casts of Aimed Shot | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Spreadshot | Hunter, Marksmanship | casts of Multi-Shot | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Arcane Archer | Hunter, Marksmanship | casts of Arcane Shot | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Sharpshooter | Hunter, Marksmanship | hours played with Marksmanship as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Close Quarters | Hunter, Survival | casts of Raptor Strike, Mongoose Bite | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Wing Clipper | Hunter, Survival | casts of Wing Clip | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Escapist | Hunter, Survival | casts of Disengage, Deterrence, Counterattack | 5 | 100 | 500 | 1,500 | 6,000 |
| Survivalist | Hunter, Survival | hours played with Survival as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Unseen | Rogue | casts of Stealth | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Pickpocket | Rogue | casts of Pick Pocket | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Locksmith | Rogue | casts of Pick Lock | 5 | 100 | 500 | 1,500 | 6,000 |
| Vanishing Act | Rogue | casts of Vanish | 1 | 30 | 125 | 400 | 1,500 |
| Eviscerator | Rogue, Assassination | casts of Eviscerate | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Bleeder | Rogue, Assassination | casts of Rupture, Garrote | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Cold Blooded | Rogue, Assassination | casts of Cold Blood | 1 | 30 | 125 | 400 | 1,500 |
| Assassin | Rogue, Assassination | hours played with Assassination as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Cutthroat | Rogue, Combat | casts of Sinister Strike | 50 | 2,500 | 12,000 | 40,000 | 200,000 |
| Gouger | Rogue, Combat | casts of Gouge | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Blade Dancer | Rogue, Combat | casts of Blade Flurry, Adrenaline Rush, Evasion, Sprint | 5 | 100 | 500 | 1,500 | 6,000 |
| Swashbuckler | Rogue, Combat | hours played with Combat as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| First Strike | Rogue, Subtlety | casts of Ambush, Cheap Shot | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Sandman | Rogue, Subtlety | casts of Sap | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Bloodspiller | Rogue, Subtlety | casts of Backstab, Hemorrhage, Ghostly Strike | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Shadowstalker | Rogue, Subtlety | hours played with Subtlety as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Fortifier | Priest | casts of Power Word: Fortitude, Prayer of Fortitude | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Aegis | Priest | casts of Power Word: Shield | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Puppeteer | Priest | casts of Mind Control | 1 | 30 | 125 | 400 | 1,500 |
| Shrieker | Priest | casts of Psychic Scream | 5 | 100 | 500 | 1,500 | 6,000 |
| Inner Flame | Priest, Discipline | casts of Inner Fire | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Dispeller | Priest, Discipline | casts of Dispel Magic | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Infuser | Priest, Discipline | casts of Power Infusion, Inner Focus | 5 | 100 | 500 | 1,500 | 6,000 |
| Disciplinarian | Priest, Discipline | hours played with Discipline as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Mender | Priest, Holy | casts of Lesser Heal, Heal, Greater Heal, Flash Heal | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Renewer | Priest, Holy | casts of Renew | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Prayer Leader | Priest, Holy | casts of Prayer of Healing, Holy Nova | 10 | 400 | 2,000 | 6,000 | 25,000 |
| High Priest | Priest, Holy | hours played with Holy as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Mind Flayer | Priest, Shadow | casts of Mind Flay | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Pain Bringer | Priest, Shadow | casts of Shadow Word: Pain | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Mind Blaster | Priest, Shadow | casts of Mind Blast | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Voidspeaker | Priest, Shadow | hours played with Shadow as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Totemic | Shaman | casts of * Totem | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Pack Runner | Shaman | casts of Ghost Wolf | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Shock Therapy | Shaman | casts of Earth Shock, Flame Shock, Frost Shock | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Twice Born | Shaman | casts of Reincarnation | 1 | 5 | 15 | 40 | 100 |
| Bolt Thrower | Shaman, Elemental | casts of Lightning Bolt | 50 | 2,500 | 12,000 | 40,000 | 200,000 |
| Chain Reaction | Shaman, Elemental | casts of Chain Lightning | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Purger | Shaman, Elemental | casts of Purge | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Stormcaller | Shaman, Elemental | hours played with Elemental as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Stormstriker | Shaman, Enhancement | casts of Stormstrike | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Weapon Imbuer | Shaman, Enhancement | casts of * Weapon | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Charged | Shaman, Enhancement | casts of Lightning Shield | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Spiritwalker | Shaman, Enhancement | hours played with Enhancement as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Wave Rider | Shaman, Restoration | casts of Healing Wave, Lesser Healing Wave | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Chain Healer | Shaman, Restoration | casts of Chain Heal | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Tide Turner | Shaman, Restoration | casts of Mana Tide Totem, Nature's Swiftness | 1 | 30 | 125 | 400 | 1,500 |
| Tidecaller | Shaman, Restoration | hours played with Restoration as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Sheep Herder | Mage | casts of Polymorph* | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Portal Master | Mage | casts of Portal: * | 1 | 30 | 125 | 400 | 1,500 |
| Quartermaster | Mage | casts of Conjure * | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Blinker | Mage | casts of Blink | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Missile Barrage | Mage, Arcane | casts of Arcane Missiles | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Detonator | Mage, Arcane | casts of Arcane Explosion | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Channeler | Mage, Arcane | casts of Evocation, Arcane Power, Presence of Mind | 5 | 100 | 500 | 1,500 | 6,000 |
| Arcanist | Mage, Arcane | hours played with Arcane as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Firestarter | Mage, Fire | casts of Fireball | 50 | 2,500 | 12,000 | 40,000 | 200,000 |
| Pyromaniac | Mage, Fire | casts of Pyroblast | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Scorcher | Mage, Fire | casts of Scorch, Fire Blast | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Pyromancer | Mage, Fire | hours played with Fire as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Frostbinder | Mage, Frost | casts of Frostbolt | 50 | 2,500 | 12,000 | 40,000 | 200,000 |
| Shatterer | Mage, Frost | casts of Frost Nova, Cone of Cold | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Iceborn | Mage, Frost | casts of Ice Block, Ice Barrier | 5 | 100 | 500 | 1,500 | 6,000 |
| Cryomancer | Mage, Frost | hours played with Frost as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Soul Harvester | Warlock | casts of Drain Soul | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Life Tapper | Warlock | casts of Life Tap | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Fearmonger | Warlock | casts of Fear, Howl of Terror, Death Coil | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Summoner | Warlock | casts of Ritual of Summoning | 1 | 30 | 125 | 400 | 1,500 |
| Corruptor | Warlock, Affliction | casts of Corruption | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Agonizer | Warlock, Affliction | casts of Bane of Agony | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Leech | Warlock, Affliction | casts of Drain Life, Siphon Life | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Hexweaver | Warlock, Affliction | hours played with Affliction as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Demon Caller | Warlock, Demonology | casts of Summon Imp, Summon Voidwalker, Summon Succubus, Summon Felhunter, Inferno, Ritual of Doom | 5 | 100 | 500 | 1,500 | 6,000 |
| Funneler | Warlock, Demonology | casts of Health Funnel | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Pactbinder | Warlock, Demonology | casts of Demonic Sacrifice, Fel Domination, Subjugate Demon, Soul Link | 5 | 100 | 500 | 1,500 | 6,000 |
| Demonologist | Warlock, Demonology | hours played with Demonology as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Bolt Slinger | Warlock, Destruction | casts of Shadow Bolt | 50 | 2,500 | 12,000 | 40,000 | 200,000 |
| Immolator | Warlock, Destruction | casts of Immolate | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Searing | Warlock, Destruction | casts of Searing Pain | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Destroyer | Warlock, Destruction | hours played with Destruction as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Shapeshifter | Druid | casts of Bear Form, Dire Bear Form, Cat Form, Travel Form, Aquatic Form, Moonkin Form | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Wild Gift | Druid | casts of Mark of the Wild, Gift of the Wild | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Back From The Brink | Druid | casts of Rebirth | 1 | 10 | 30 | 80 | 200 |
| Entangler | Druid | casts of Entangling Roots, Hibernate | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Wrathbringer | Druid, Balance | casts of Wrath | 50 | 2,500 | 12,000 | 40,000 | 200,000 |
| Starcaller | Druid, Balance | casts of Starfire | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Moonlit | Druid, Balance | casts of Moonfire | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Stargazer | Druid, Balance | hours played with Balance as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Mauler | Druid, Feral | casts of Maul, Swipe | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Shredder | Druid, Feral | casts of Claw, Shred, Rake | 50 | 2,500 | 12,000 | 40,000 | 200,000 |
| Savage | Druid, Feral | casts of Ferocious Bite, Rip | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Wildheart | Druid, Feral | hours played with Feral as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Greenthumb | Druid, Restoration | casts of Healing Touch, Regrowth | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Rejuvenator | Druid, Restoration | casts of Rejuvenation | 25 | 1,500 | 7,500 | 25,000 | 100,000 |
| Swiftmender | Druid, Restoration | casts of Swiftmend, Nature's Swiftness, Tranquility, Innervate | 5 | 100 | 500 | 1,500 | 6,000 |
| Grovekeeper | Druid, Restoration | hours played with Restoration as the main talent tree | 2 | 40 | 150 | 400 | 1,500 |
| Caretaker | Healer | hours played in sessions played as a healer | 2 | 40 | 150 | 400 | 1,500 |
| Lifegiver | Healer | healing the client's meter counted in sessions played as a healer | 2,500 | 500,000 | 5,000,000 | 25,000,000 | 150,000,000 |
| Miracle Worker | Healer | the most healing in one fight in sessions played as a healer | 500 | 8,000 | 30,000 | 75,000 | 150,000 |
| Field Medic | Healer | dungeon runs in sessions played as a healer | 1 | 15 | 50 | 150 | 400 |
| Chaplain | Healer | dungeon bosses killed in sessions played as a healer | 1 | 40 | 200 | 750 | 2,000 |
| Raid Medic | Healer | raid bosses killed in sessions played as a healer | 1 | 20 | 80 | 250 | 650 |
| Guardian Angel | Healer | dungeon runs with a boss kill and no death in sessions played as a healer | 1 | 8 | 30 | 80 | 250 |
| Resurrector | Healer | casts of Resurrection, Redemption, Ancestral Spirit, Rebirth, Revive in sessions played as a healer | 1 | 30 | 125 | 400 | 1,500 |
| Cleanser | Healer | casts of Cleanse, Purify, Dispel Magic, Cure Disease, Abolish Disease, Cure Poison, Abolish Poison, Remove Curse in sessions played as a healer | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Battle Medic | Healer | kills in sessions played as a healer | 50 | 1,500 | 6,000 | 20,000 | 100,000 |
| Stalwart | Tank | hours played in sessions played as a tank | 2 | 40 | 150 | 400 | 1,500 |
| Damage Sponge | Tank | damage taken the client's meter counted in sessions played as a tank | 5,000 | 750,000 | 7,500,000 | 40,000,000 | 200,000,000 |
| Unbreakable | Tank | the most damage taken in one fight in sessions played as a tank | 1,000 | 10,000 | 50,000 | 120,000 | 250,000 |
| Vanguard | Tank | dungeon runs in sessions played as a tank | 1 | 15 | 50 | 150 | 400 |
| Bulwark | Tank | dungeon bosses killed in sessions played as a tank | 1 | 40 | 200 | 750 | 2,000 |
| Juggernaut | Tank | raid bosses killed in sessions played as a tank | 1 | 20 | 80 | 250 | 650 |
| Immovable | Tank | dungeon runs with a boss kill and no death in sessions played as a tank | 1 | 8 | 30 | 80 | 250 |
| Provoker | Tank | casts of Taunt, Mocking Blow, Challenging Shout, Growl, Challenging Roar, Seal of Fury in sessions played as a tank | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Frontliner | Tank | kills in sessions played as a tank | 50 | 1,500 | 6,000 | 20,000 | 100,000 |
| First To Fall | Tank | deaths in sessions played as a tank | 1 | 8 | 30 | 80 | 300 |
| Brawler | Melee DPS | hours played in sessions played as melee DPS | 2 | 40 | 150 | 400 | 1,500 |
| Bruiser | Melee DPS | damage the client's meter counted in sessions played as melee DPS | 5,000 | 1,000,000 | 10,000,000 | 50,000,000 | 300,000,000 |
| Onslaught | Melee DPS | the most damage in one fight in sessions played as melee DPS | 1,000 | 10,000 | 50,000 | 120,000 | 250,000 |
| Skirmisher | Melee DPS | dungeon runs in sessions played as melee DPS | 1 | 15 | 50 | 150 | 400 |
| Headhunter | Melee DPS | dungeon bosses killed in sessions played as melee DPS | 1 | 40 | 200 | 750 | 2,000 |
| Warbringer | Melee DPS | raid bosses killed in sessions played as melee DPS | 1 | 20 | 80 | 250 | 650 |
| Untouched | Melee DPS | dungeon runs with a boss kill and no death in sessions played as melee DPS | 1 | 8 | 30 | 80 | 250 |
| Silencer | Melee DPS | casts of Kick, Pummel, Shield Bash, Earth Shock, Hammer of Justice, Rebuke, Repentance, Bash, Feral Charge in sessions played as melee DPS | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Cleaver | Melee DPS | kills in sessions played as melee DPS | 50 | 1,500 | 6,000 | 20,000 | 100,000 |
| Duelist | Melee DPS | players killed in sessions played as melee DPS | 1 | 20 | 80 | 400 | 2,000 |
| Longshot | Ranged DPS | hours played in sessions played as ranged DPS | 2 | 40 | 150 | 400 | 1,500 |
| Artillery | Ranged DPS | damage the client's meter counted in sessions played as ranged DPS | 5,000 | 1,000,000 | 10,000,000 | 50,000,000 | 300,000,000 |
| Barrage | Ranged DPS | the most damage in one fight in sessions played as ranged DPS | 1,000 | 10,000 | 50,000 | 120,000 | 250,000 |
| Backliner | Ranged DPS | dungeon runs in sessions played as ranged DPS | 1 | 15 | 50 | 150 | 400 |
| Spellslinger | Ranged DPS | dungeon bosses killed in sessions played as ranged DPS | 1 | 40 | 200 | 750 | 2,000 |
| Siegebreaker | Ranged DPS | raid bosses killed in sessions played as ranged DPS | 1 | 20 | 80 | 250 | 650 |
| Out Of Reach | Ranged DPS | dungeon runs with a boss kill and no death in sessions played as ranged DPS | 1 | 8 | 30 | 80 | 250 |
| Warden | Ranged DPS | casts of Polymorph*, Frost Nova, Fear, Howl of Terror, Banish, Freezing Trap, Scare Beast, Scatter Shot, Hibernate, Entangling Roots, Shackle Undead, Mind Control, Psychic Scream, Frost Shock, Earthbind Totem in sessions played as ranged DPS | 10 | 400 | 2,000 | 6,000 | 25,000 |
| Sniper | Ranged DPS | kills in sessions played as ranged DPS | 50 | 1,500 | 6,000 | 20,000 | 100,000 |
| Bombardier | Ranged DPS | players killed in sessions played as ranged DPS | 1 | 20 | 80 | 400 | 2,000 |

The feats and their thresholds are one table in code: `App\Support\Feat` names them, `FeatRows` holds their cards
and `FeatRules` holds the rule of every class, tree and role feat. The counts are stored in
`character_feats` (one row per character and feat, the `value` and when it was counted): `RefreshCharacterFeats`
runs after every decode of the character and `feats:refresh` queues it for one character or for every
character with a session, as a daily safety net. `CountFeats` and its counters under
`App\Actions\Feats\Counts` read the base tables only, `sessions` and the fact tables, never a continuous
aggregate, so a count never lags the decode that triggered it. The tier is not stored: it is read from the
value with `Feat::tier()`, so a threshold change needs no recount. Values that are hours, gold or days are
rounded down before they are stored, and Wayfarer down to one decimal, so a value never shows a tier it has
not reached. The emote feats leave out `Emote::READ`, as the emote rankings do. The character page and the API read the stored rows. `CountHolders` counts, for every feat and tier,
the characters whose stored value reaches it (one query, cached 15 minutes), and `ShowCharacterFeats` orders by it.

### Class pages

A character has a page for its class, `/c/{name}/{class}` (owner, 2026-11-22: "go deeper into class fantasy so we
have metrics/charts/visuals for specific classes and talents", "fun and exciting and for all classes and talents").
It shows what only that class does: a warlock's soul shards, Life Tap and demons, a shaman's totems, a druid's forms.
The design is approved: the comp is `docs/comps/class.html` (the Warlock) and the Scarlet app gets the same
view. Status: all nine pages are built, one for each class. Built: the art (the nine heads, `warrior` to `druid` in
`resources/js/scene/scenes/` and in the `SCENES` table of `tools/scene-art`, with `AppSupportScene` and
`PlayerClass::scene()` naming them, and `/design/scenes/{class}` showing each over invented numbers, and the spell icons,
Spell icons), the view layer, the route, the reads, the comparison and the nine pages (below). Every class has a builder,
and a character has its page once its sessions counted a cast.

**What is built (the view layer).** `App\Support\ClassPage\ClassPage` is the typed page object every class fills: the
character line, the links, the callouts, the six figures, rows of modules (each a list of blocks), the talent modules,
the party rows, the comparison and the feats. A block is a `Flow`, `Link`, `Mirror`, `Stack`, `Bag`, `Rank`, `Pair`,
`Split`, `Titled` or `Columns`; each renders through `resources/views/components/class/<name>.blade.php`, and
`<x-class.page>` lays out the skeleton. The geometry of the flow, link and mirror charts is in `App\View\ClassPage`
(`FlowLayout`, `LinkLayout`, `MirrorLayout`) and is drawn on the server with no JavaScript. `ClassIcons::of` collects
every `Icon` on a page so the page resolves them once, items through `ItemIcons` and spells through `SpellIcons`. The
class colour is `data-class` plus `--class`. The rules are in
`resources/css/class.css`, scoped under `.cls-page` and `.cls-band`, loaded only by a page that asks for it, so no other
page changes. `/design/class`, `/design/class-rogue` and `/design/class-shaman` render the Warlock, Rogue and Shaman
pages from invented numbers (`App\View\Design\ClassPages`); the band (`<x-class.band>`) sits on `/c/{name}`. The head's
scene and callouts come from the art that the nine heads provide.

**What is built (the route, the reads and the Warlock).** `GET /c/{name}/{class}` is `CharacterClassController`, which
asks `App\Actions\Public\ClassPage\CharacterClassPage` the way the battlegrounds page asks its Action: unknown or private
to the viewer, a slug that is not the character's own class, or a class with no page is a 404. A character has a class
page when its class has a builder and its sessions counted a cast (`HasClassPage`: a counted `spell_casts` row with `casts`
above 0, since a channel or an interrupted cast alone writes a row with none). The route asks that rule once per
request; the band and the link on `/c/{name}` come from the stored band (below), which exists only where the rule held,
so a link never leads to a 404. The route validates only what it reads (`CharacterClassRequest`).

**A withheld group is not a zero.** A figure or module whose rows the character does not share is left out, not drawn as
0: no pet rows (the `combat` group removed) means no demon figures, no `item_flows` rows means no soul shard figures, and
a share whose whole is 0 (a damage share when no damage was counted) is not drawn. A figure the rows cannot answer has no
stored standing either.

- **Reads.** `App\Support\ClassRows\ClassRows` is the one typed reader over the six class tables (docs/ROWS.md, "The class
  tables"): one aggregate query for each table over its counted rows, plus the spell names, the descriptions the `SD` lines
  filled, the kills a party member got, the played time by level and the levels seen. It memoizes, so a builder asks for
  anything as often as it likes. A builder holds no SQL. A read takes only what it is asked for: `states(StateKind ...)`
  and `items('Soul Shard', '*Healthstone')` (names, `*` as a wildcard) read those kinds and names, a call with no argument
  reads everything, and a read that covers a later one answers it. Every sum is capped at 2^52 in SQL and the level axis at
  60, so a stored number cannot grow a page; the value objects cap what they draw (a bag 32 slots, a stack 60 columns and
  12 series, a rank 20 rows, a flow 8 and 12 ribbons, a link 10 a side). `holds(ClassTable)` says whether a table has a
  counted row for the character, which is how a builder tells a group that was withheld from one that counted nothing.
  `Tallies::count($key, $a, $b)` matches any value for a `null` list and nothing for an empty one.
- **Items.** The rule for soul shards and every other flow is in `ItemFlow`: "made" is direction In at place `o` only
  (`made()`), what a spell spent is direction Out at `o` (`spent()`), and "in the bags now" is the sum of every change
  (`held()`). A bank withdrawal or a loot pickup is in the bags but never made. "Other spells" is what `o` spent on a spell
  the chart does not name.
- **Builders.** `App\Contracts\ClassBuilder::handle(ClassRows, ClassFacts): ClassParts` returns what only the class knows:
  the head icon, the callouts, the six figures, the one to five of the band (a figure the rows cannot answer is not in it),
  the sheet, the talent modules, the party rows and the figures the page is compared on (each only when it can be worked
  out). `ClassFacts` carries the character, its latest build, the talents of that build
  that have a module (`TalentModules`, at most three) and the stored peers. `ClassBuilders` maps each `PlayerClass` to its
  builder; a class missing there has no page. The page adds the character line, the links, the comparison and the feats.
- **Warlock.** `WarlockBuilder` draws its sheet from `App\Support\ClassPage\Warlock\`: `Spells` (the spell sets), `Shards`
  (the flow of soul shards and the bag), `Taps` (Life Tap by level and the health traded), `Demons` (the stack by level,
  the callouts and the ranks), `Casting` (curses and banes, damage by spell, what finished the kill), `Talents`
  (Nightfall, Shadowburn, Siphon Life), `Party` (stones, summons, fear and control) and `Standings`.
- **Comparison.** `class_standings` holds one row for each character and figure (class, level at the count, ruleset,
  value), and `class_bands` one row for each character (below). `RefreshCharacterFeats` queues `RefreshClassStandings` for
  a character whose class has a builder, so a feat recount (after every decode and daily) rewrites them. The job runs on
  the backfill queue, in one transaction that locks the character's row first, as a decode and `RemoveGroup` do, and
  reads the character's rows inside it. A figure the rows cannot answer has no row. The time spent drinking is stored for
  every character of every class that has a builder (`Drinking::figure`), since the "Every class" comparison reads the
  other classes' rows. `ClassPeers` reads the other public characters in the bracket with one grouped query for every
  figure of a page view. The page draws "More than N% of warlocks" (the share of the others strictly below the
  character's stored value, rounded down, at most 99) only when the bracket holds 20 characters, the character included,
  and the character has a stored value for the figure; under that it draws nothing, and the Life Tap module's drinking
  comparison goes with it. Its "Every class" row needs 20 characters of other classes that hold the figure.
- **Stored band.** `class_bands(character_id, class, icon, figures, counted_at)` holds the figures of `/c/{name}`'s band,
  written by `StoreClassBand` with the standings and deleted with them. `/c/{name}` reads that one row
  (`CharacterClassBand`, `StoredBand::restore`) and builds nothing from the class tables; a character with no row has no
  band and no link to a class page. `RemoveGroup` deletes the characters' standings and bands in its transaction
  (`ForgetClassStandings`) and queues the job after it commits, so no figure drawn from the removed rows survives;
  `RemoveEverything` removes them with the characters (cascade). Bands of characters that already have a page appear at
  their next decode or the daily `feats:refresh`.
- **Navigation.** `CharacterLinks` builds the line (Character, the class's name, Battlegrounds) for `/c/{name}`, the class
  page and `/c/{name}/battlegrounds`; `CharacterClassBand` gives `/c/{name}` its band of up to five above "This week". A
  caller that knows whether the class page exists passes the answer (`$hasClassPage`). The
  sitemap does not list battlegrounds pages, so it does not list class pages either.
- **Charts of a short range.** A mirror or a stack of twelve levels or fewer names every level on its axis; longer ones
  name the first and each ten.
- **Where a by-level chart starts.** A chart starts at the first level that has a counted row of what it reads, and a
  share of played time counts only the time from that level: `ClassRows::levels()`, `playedByLevel()` and `playedMs()`
  take the tables or state kinds the chart reads (a demon chart reads the pet spans, a Life Tap chart the casts). The
  levels played before the rows existed are not drawn as "No demon out" or "No form"; a fight with no pet still is.
- **Fixture.** `Shard-Keeper` (`e2e/warlock-lines.mjs`) is a warlock of the first e2e install with six hours over levels 50
  to 52, written so every module has rows. `e2e/warlock-expected.mjs` holds the figures worked out from its lines before
  the page was read, and `e2e/checks/class-page.mjs` reads the page against them.

**Where the Warlock page differs from the comp.** Built by `WarlockBuilder` and the classes in
`App\Support\ClassPage\Warlock` (above). The rows the decoder writes cannot answer some of the comp's modules,
so the page leaves them out or says what the rows can. Soul shards: the pair "most held at once, typical pull, pulls with
none" is replaced by "In the bags now", since no row holds the bag over time. Nightfall: "Seconds to use one" is replaced
by "Shadow Bolts under it". Shadowburn: "Shards lost to it" is replaced by "Shards spent", and the kill cell reads "Killed
within 3 seconds". Siphon Life: "Fights with it" is replaced by its shares of the healing and damage meters, which list
only a fight's top five spells and say so. Summons: "At a dungeon door", the places the summons went and "Most in a day"
are replaced by "Interrupted". "Deaths undone by your own" reads "Deaths undone by a soulstone". The head has no link to
a build page. A killing blow with no cast of yours in the 3 seconds before it is labelled "No cast in the 3 seconds
before", with the note "a wand, a melee swing, a pet or a damage-over-time spell": the rows cannot tell which.

**Where the Warrior page differs from the comp.** Built by `WarriorBuilder`: a head of a target dummy, a weapon rack and
an armour stand with a callout each (the attack cast most, the weapon type held longest in the main hand, the shield);
six figures (Charges with Intercept, Executes, Sunder Armors, Battle Shouts, Taunts, Deaths); damage dealt and taken a
fight by level; the stance held, as a share of fight time and by level; how fights open; attacks; Execute; shouts;
weapons in the main hand; damage by spell; a module for each of Mortal Strike, Sweeping Strikes, Enrage, Death Wish,
Flurry, Last Stand and Shield Slam that the build holds (at most three, in the build's order); and what the warrior
does for the party. The comp's modules the rows cannot answer are replaced or left out. Execute: "within a second" of
a cast reads "within 3 seconds", which is what the `kill_blow` tally counts. Charge and the other casts: "in dungeons"
is dropped, since `spell_casts` has no place, and Holding the pull says casts are counted in every place. Stances:
"Most in one fight" and "Fights with it" are replaced by "Changed in a fight" and the share of fight time. Mortal Strike
and Shield Slam: "Fights with it" is replaced by "Killed within 3 seconds". Sweeping Strikes, Enrage, Flurry and Death
Wish: "Attacks under it" comes from the `cast_under` tally. Interrupts: "Interrupts landed" is the client's meter, which
counts every interrupt of yours, so "Landed of casts" can pass the casts of Pummel and Shield Bash and is capped at
100%. Battle Shout: no figure counts the hours a party stood next to the warrior, so the rate is casts a played hour
and the share of fight time the buff was up. Weapons: the time before the first gear change of a session is not
known, so the module says it counts from there. The fixture is Iron-Vanguard (`e2e/warrior-lines.mjs`, three clusters
of fights at levels 50, 51 and 52); `e2e/warrior-expected.mjs` works its figures out from the lines with
`e2e/lines-facts.mjs` and `e2e/checks/class-page-warrior.mjs` reads the page against them. The fixture holds three
talents, so the Death Wish, Flurry, Last Stand and Shield Slam modules were run against its rows with those talents
held and not stored as a page check. The comparison with other warriors waits for 20 of them, as for the Warlock.

**Where the Paladin page differs from the comp.** Built by `PaladinBuilder`: a head of the warhorse, a lectern and a
shield rack with a callout each (the mount's hours and summons, the blessing cast most, the bubbles and how many of
them had a Hearthstone cast inside); six figures (Judgements, Blessings cast, Lay on Hands, Divine Shields, Hammers of
Justice, Players raised); the chart from the seal to the judgement (a link of the seal put up to its fate, from
`seal_fate`, with the fates "ended another way" and "still up when the fight ended" beside the comp's three); the aura
held, by level, over the time played; blessings; spells for the undead; the bubble; Lay on Hands; heals; control; a
module for each of Seal of Command, Repentance, Consecration, Divine Favor, Holy Shock, Holy Shield and Blessing of
Kings that the build holds; and blessings, saves and cleansing for the party. What the rows cannot answer is replaced
or left out. "Seconds from seal to judgement" is an average to the end of the seal, not a median, since the tally keeps
a sum. "Against the undead and the rest" is "Spells for the undead": a kill is not stored with the creature type, so the
share of undead kills is left out and the note says so. Repentance, Consecration and Divine Favor: "in dungeons" and
"cut short" are replaced by "Used in a fight" and a rate. Blessings in a group: no row holds group time, so the rate is
over the time played, and "Lay on Hands in the open" is left out of Saves. Hearthstones inside a bubble come from
`cast_under` and deaths after a bubble from `death_after_aura`, both counting only the shield and protection that
the character cast. The comp's split of the Symbols of Kings between the greater blessings is "Symbols of Kings used":
the rows hold the symbols that left the bags and not who was blessed, and a note says so. The fixture is Oath-Bearer (`e2e/paladin-lines.mjs`); `e2e/paladin-expected.mjs` works its figures
out from the lines, including the fate of each seal by the rules of docs/ROWS.md, and `e2e/checks/class-page-paladin.mjs`
reads the page against them. The Warrior, Paladin and Hunter checks share `e2e/checks/class-page-kit.mjs`.

**Where the Hunter page differs from the comp.** Built by `HunterBuilder`: a head of the pets (a cat, a wolf, a boar and
an owl, each with a callout of its name, family, hours out and share of pet time); six figures (Hours with a pet out,
Tame Beast casts, Shots fired, Hours of Auto Shot, Traps set, Feign Deaths); the chart from where the ammo came to where
it went (bought, crafted, looted, mailed or traded in, on one side; fired by ammo, sold or destroyed, and held, on the
other); the quiver; the pet out, by level; pets; the aspect up, by level; shots; traps; Feign Death; tracking; a module
for each of Bestial Wrath, Intimidation, Aimed Shot, Scatter Shot, Deterrence and Counterattack that the build holds; and
auras, Freezing Trap, Tranquilizing Shot and Mend Pet for the party. What the rows cannot answer is replaced or left
out. "Beasts tamed" is Tame Beast casts, since no row says a taming worked. The comp's gold spent on ammo and its fights
that ran out of ammo are replaced by shots a fight and ammo bought, because no row prices an item or says a fight began
without ammo. The quiver is a bag of stacks of 200 counted from the changes the addon saw, since `BC` does not say which
bag is a quiver. A pet of any other family than the four with a model is "Another pet" in the level chart and keeps its
name in the list. Tracking lists the `MP` types that are spells, named by spell id; the minimap's own filters, such as an innkeeper or a
flight master, carry no spell and are left out. Auto Shot is found by the
name of the spell id the `r` rows carry, as they hold no name. "Auras for the group" and the traps are hours up and
casts: no row holds group time, so "in dungeons" is left out. Bestial Wrath and Intimidation read casts, the share
used in a fight and casts a fight, in place of the boss fights, since no row holds an encounter; Aimed Shot reads its
casts, its share of the shots and the kills within 3 seconds of one, in place of the fights it opened and the casts a
resist cut short, since no row says why a cast failed. Shots fired are the ammo item changes that Auto Shot
caused, read as for the item flows of docs/ROWS.md. Scatter Shot, Deterrence and Counterattack carry the "Used in a
fight" pair and a rate or deaths within 10 seconds; the fixture holds none of the three, so they were checked once
against synthetic talents and are not a page check. The fixture is Pack-Warden (`e2e/hunter-lines.mjs`), whose pets,
ammo and aspects are scripted per fight; `e2e/hunter-expected.mjs` works its figures out from the lines and
`e2e/checks/class-page-hunter.mjs` reads the page against them. The comparison with other hunters waits for 20 of them.

**Where the Rogue page differs from the comp.** Built by `RogueBuilder` and the classes in
`App\Support\ClassPage\Rogue`. The rows hold no money or items from a pocket, no dungeon split and no kills by
fight, so Pick Pocket reads "A played hour" and "Most in a row" where the comp has "Gold lifted", "Items taken" and
"What came out most". Locks read "Lockboxes opened" and "A played hour" instead of "Lockpicking skill". The poisons
are items, so each row carries the item's name and icon, and the counts of the ranks of one poison are added.
Blade Flurry and Adrenaline Rush read "Time up" and "Casts under it" instead of kills by fight and boss fights; Riposte
reads "Cast in a fight" and "Most in a row"; Sap reads "Fights opened with one" and "Of all fights" instead of
dungeons; Scouting is a rank of Distract, Detect Traps and Disarm Trap instead of hours in stealth inside dungeons.
"Kicks on bosses" is left out, since the rows do not split casts by encounter. The other talents with a module are Cold
Blood (casts, time up, casts under it), Ghostly Strike (casts, share in a fight, most in a row), Premeditation (casts,
casts from stealth) and Remorseless Attacks. The decoder does not watch the Remorseless proc for the casts that spend
it, so that module reads "Times it came up" and "Time up" and nothing about what spent it. The six figures kept for the
comparison are the share of fights opened from stealth, the share of played time in stealth, pockets and locks a played
hour, the share of Kicks that landed and the share of fights with a Vanish.

**Where the Priest page differs from the comp.** Built by `PriestBuilder` and the classes in
`App\Support\ClassPage\Priest`. The healing, damage and absorbs come from the client's meters after each fight, so a
fight's figures are the client's own, and the by-spell rank lists only the top five spells of a fight and says so.
Players raised are the casts of Resurrection: the rows hold no zone for a cast, so "Raised from the dead" reads "Players
raised", "A played hour" and "Most in a row" where the comp has a rank by dungeon, and the head's altar callout drops "1
in 3 dungeon runs". The comparison reads "Players raised a played hour" for the same reason. Overhealing and who was
healed are not recorded, so the page shows neither. Control shows casts only: the channel time of Mind Control is not
read. The wand module shows hours and the share of fight time, and the comparison puts it beside other priests. Inner
Focus reads "Casts", "Time up" and "Casts under it" instead of the heal that followed it. Spirit of Redemption reads
"Times it came up", "Time up" and "Heals cast as one". Lightwell, Power Infusion and Vampiric Embrace read casts, the
share cast in a fight and the most in a row, and Shadowform reads casts, time up and casts under it. The six figures kept
for the comparison are healing a fight, shields a fight, players raised a played hour, the share of played time in
Shadowform, the share of fight time with the wand firing and deaths a played hour.

**Where the Shaman page differs from the comp.** Built by `ShamanBuilder` and the classes in
`App\Support\ClassPage\Shaman`. A totem's time is the time it stood, cut at the next totem of its slot and at its
duration, so the head's hours and each element's "Stood" are never more than the totems could have run. What a totem did
for the party or against the enemy is not recorded, so the page shows drops, hours and how each ended, and nothing about
what a totem gave. "Totems for the group" ranks the hours the group totems stood, not the hours the character was
grouped. The fight tally holds how many of the four elements had a totem down, not how many totems were dropped, so
"Totems in a fight" reads "Fights with all four down", "Median elements down a fight" and "Fights with a totem down"
where the comp has the median totems dropped and the most dropped in one fight. The comparison reads Astral Recalls a
played hour where the comp compares the count, and the share of deaths walked off where the comp compares Reincarnations
a hundred deaths. Stormstrike reads "Casts", "Cast in a fight" and "Most in a row" where the comp has the
share of the character's damage; Elemental Focus reads "Times it came up", "Spent on a spell" and "Used"; Flurry reads
"Times it came up", "Time up" and "Casts under it" where the comp has the share of fights with it. Lightning Mastery and
Nature's Swiftness read casts, time up and casts under it, and Mana Tide Totem reads casts, the share cast in a fight and
the most in a row. The six figures kept for the comparison are totems dropped a fight, the share of fights with all four
elements down, shocks a fight, the share of played time as a Ghost Wolf, the share of deaths walked off with
Reincarnation and Astral Recalls a played hour.

**Where the Mage page differs from the comp.** Built by `MageBuilder` and the classes in `App\Support\ClassPage\Mage`
(`Spells`, `Conjuring`, `Schools`, `Portals`, `Casting`, `Control`, `Party`, `Talents`, `Standings`); it reads the spells a
meter listed by level through `MeterSpellLevels`. Its fixture is `Frost-Weaver` (`e2e/mage-lines.mjs`, worked out in
`e2e/mage-expected.mjs`), a mage of the stranger install, so the first install's character counts do not move. The rows
cannot answer some of the comp's modules, so the page says what they can. Conjured water and food: the site never receives
the bag contents, so "in the bags now" is not known, and what is left in the bags and what was gone at logout cannot be
told apart; they are one row, "In the bags or gone at logout" (conjured less everything that left), and a note says so.
The bag chart is left out for the same reason, and "Stacks conjured a played hour" reads "Conjured a played hour". Water
and food handed out leaves out "Most handed out in a day" and the trades, since no row holds a day or who took an item,
and a note says so. Arcane Intellect reads "Casts a played hour" where the comp has casts a group hour, since no row holds
group time. Damage by school by level counts the Frost, Fire and Arcane spells the damage meter listed, which holds the
five largest spells of a fight, and a note says so. Polymorph reads casts, the share cast in a fight and "Interrupted",
without "Polymorphs in dungeons"; Counterspell says in a note how many interrupts the client's meter landed. "Curses lifted
and sheep kept" is "Curses lifted", the Remove Curse casts and the client's dispel meter, since no row says how long a
sheep held. The talent modules are Ice Barrier, Cold Snap, Ice Block, Presence of Mind, Arcane Power, Pyroblast and
Combustion, in that order, and the page shows the first three the build holds. "Fights with it" reads "In a fight"; Ice
Barrier adds the damage absorbed, which counts the fights where the meter listed it, and a note says so; Cold Snap reads
casts, the share in a fight and the hours between casts, without "Near a freeze", since no row says what a Cold Snap
followed; Ice Block reads casts, in a fight and deaths within 10 seconds of one ending. The comparison covers water
conjured, water handed out, portals opened, kills in the biggest pull, time spent drinking and Blinks, each as a rate a
played hour or a share, and waits for 20 mages.

**Where the Druid page differs from the comp.** Built by `DruidBuilder` and the classes in `App\Support\ClassPage\Druid`
(`Spells`, `Forms`, `Casting`, `Talents`, `Party`, `Standings`). A form is the aura of `state_times` kind `a` under its client name (Bear Form and Dire Bear Form
are one), and the form chart reads the `shift_form` tally, which counts gains only: the left side has "No form", the
right side has none. A shift back into the form just left inside 2 seconds is the tally's `a` equal to `b`. Its fixture is
`Moon-Walker` (`e2e/druid-lines.mjs`, figures in `e2e/druid-expected.mjs`), a druid of the stranger install. The rows
cannot answer some of the comp's modules, so the page says what they can. "What each form did" lists the spells that need
a form (Maul, Claw) and the caster's spells, by casts instead of a share, since no row holds the form worn at a cast, and
a note says so. Left out, since no row says where or in which fight a cast fell: Rebirths by dungeon, Innervates in a
dungeon run and in boss fights, most shifts in one fight, forms worn in one fight, and "The role played in dungeons",
which "The form worn in fights" replaces (the form's share of fight time, over every fight). Rebirth and Innervate show
casts, the share cast in a fight and Rebirth's interrupts or the hours between Innervates; who a spell was cast on is not
recorded. Prowl shows the hours prowling, the casts of Prowl and the spells cast while it was on (`cast_under`), in
place of the openers and fights opened. Leader of the Pack shows the hours it was up, its share of the time in Bear or
Cat Form and the times it went on, since the group is not recorded. Healing by spell reads the healing meter and says it
lists five spells a fight. The talent modules are Feral Charge (Bear), Omen of Clarity, Leader of the Pack, Nature's
Grasp, Moonkin Form, Nature's Swiftness and Swiftmend, the first three the build holds shown first. Omen of Clarity is a
Balance talent in this client, and its procs are the Clearcasting auras. The comparison covers shapeshifts a fight, the
time in a form of played time, Travel Form of travel, the shifts back into the form just left, Rebirths and Innervates in
ten played hours, and the fight time in Bear or Cat Form, and waits for 20 druids.

**Where it lives.** `/c/{name}/{class}` with the class's slug (`warrior`, `paladin`, `hunter`, `rogue`, `priest`,
`shaman`, `mage`, `warlock`, `druid`); any other slug, and a class the character is not, is 404. Visible exactly when
the character's page is. `/c/{name}`, the class page and `/c/{name}/battlegrounds` share one line of navigation under
the head (Character, the class's name, Battlegrounds), each shown only when it has something to show. `/c/{name}`
carries a band of up to five of the class's figures above "This week", linking to the page. There is no public page for
"all warlocks": the class page is a character's, and the one place other characters appear is the comparison below.

**The skeleton, the same for every class.**

1. A head built like a profession page's (Profession scenes): the game's own models for what the class is known by,
   in the `ground` setting, with a callout on each model carrying the character's own number for it. A callout
   stands on its model and takes the side and the pole length at which its text touches no model, no other callout and
   no edge of the scene, at 1280 and at 390 wide (`resources/js/scene/callouts.js`, from the models' screen rectangles
   that `mount.js` projects); where a scene leaves no such place, the callout with the least overlap wins, and the nine
   class scenes' anchors are placed so that one exists. The models stand still: the scene module draws no skeleton animation. A creature needs its skin, so `tools/scene-art` reads a
   creature's display row and puts its texture variations in the model's slots 11 to 13 before it writes the art.
2. The name line (the class with its icon, the character, level, talent build with points by tree, hours played),
   the navigation line and the credit line.
3. Six figures with the game's icons.
4. The sheet. The first row is the class's own chart, the one no other class has. Below it, rows for what the class
   spends its time on: by level where the answer changes as the character grows, as a rank where it is a list.
5. **From the talents.** One module for each talent the build has that changes how the class plays, up to three,
   tagged with the points spent (`2 of 2`). A module is shown only while the latest build has the talent, so a
   respec changes this section and nothing else.
6. **For the party.** What the class does for other players: stones and summons, water and portals, blessings,
   totems, resurrections.
7. **Against other characters of the class.** Six of the page's figures as a place among characters of the same
   class in the level bracket, drawn on `.rules`. Hidden for a bracket under the character threshold.
8. **Class feats.** The feats the catalog already holds for the character: the four of its class and the four of
   the talent tree its latest build has most points in, read from the stored rows as the feat wall reads them. The
   page adds no feat of its own, and the other trees' feats and the role feats stay on the wall.

**Colour.** The class colour (`data-class`, `--class`) carries the class's own resource and its charts' first series.
Scarlet stays what it is everywhere else: recorded totals, the character against others, tiers. Grey is what was
lost or wasted (a shard destroyed, a seal that ran out), white what is still held. Every game icon is the client's
own and never committed. Item icons are the ones the profession pages use (Item icons); spell icons are stored the
same way, once per icon file with a map from spell id to file, under `spells/` on the `maps` disk. A row whose icon
is missing shows an empty square, and a row the game has no icon for (deaths, "every other spell") shows no
square. A rogue's poisons are items, so their rows carry the item's name and icon.

**Charts the page adds.** A *flow*: what was made on the left, one pool in the middle with the total, what it went to
on the right, each ribbon as wide as its count (soul shards, ammo, conjured water). A *link*: the same without the
pool, pairs from a left list to a right list (opener to finisher, the form left to the form entered, a seal to what
became of it). A *mirror*: two series by level from one axis, one up and one down (health for mana, healing against
damage). A *stack*: shares of time by level (the demon out, the stance, the form, the aura). A *bag*: the bag's slots
with the item in the ones that hold it. The rest are the site's `rank`, `pair`, `tally`, `rules` and feat seals.

**The nine pages.** Spell and item names are the client's own for build 1.60.1.71037, which differ from the old
game's in places (`Bane of Agony`, `Subjugate Demon`); a page prints the name the client gives for the id.

| Class | Head | Six figures | First chart | Rows below it |
| --- | --- | --- | --- | --- |
| Warlock | The Imp, Voidwalker, Succubus and Felhunter on a ritual circle. A callout on each demon: its name, the hours it was out and its share of the time a demon was out | Soul shards made, Life Taps, hours with a demon out, healthstones made, soulstones made, players summoned | Flow: where soul shards came from (Drain Soul, Shadowburn) and what they bought (a demon summoned, Shadowburn, Soul Fire, healthstones, soulstones, Ritual of Summoning, other spells, destroyed to make room, in the bags now), beside the bag with its shards | Mirror: health traded for mana by level, beside Life Tap (taps in combat, most in a row, deaths within 5 seconds of a tap, time spent drinking against other classes). Stack: the demon that was out by level, beside the demons by hours with their names. Curses and banes cast. Damage by spell. What finished the kill |
| Warrior | A training yard: a target dummy, a weapon rack and an armour stand by a brazier. Callouts: the attack used most, the weapon type fought with longest, the share of fights with a shield | Charges, Executes, Sunder Armors, Battle Shouts, Taunts, deaths | Mirror: damage dealt and damage taken a fight by level, beside the stance (share of fight time in each, changes a fight) | Stack: the stance held by level, beside how a fight opens (Charge, Intercept, Bloodrage, a ranged pull, nothing in 3 seconds). Attacks used, beside Execute (casts, kills within a second of one). Shouts. Weapons in the main hand by hours. Damage by spell |
| Paladin | A cathedral yard: the paladin's mount (the Warhorse, the Charger from level 60), a lectern and a shield rack under a banner. Callouts: hours on the class mount and times it was summoned, the blessing cast most, Divine Shields with how many ended in a Hearthstone | Judgements, blessings cast, Lay on Hands, Divine Shields, Hammers of Justice, players raised | Link: the seal put up to what became of it (judged, ran out, replaced, still up when the fight ended), beside Judgement (seconds from seal to judgement, judgements a fight) | Stack: the aura that was up by level, beside blessings by casts. Spells against the undead and demons with their share of kills, beside the bubble (casts, Hearthstones used inside one, deaths within 10 seconds of one ending). Lay on Hands. Heals. Hammer of Justice and control |
| Hunter | A camp clearing with the character's own pets, each family's model. A callout on each pet: its name, family, hours out and share of pet time | Hours with a pet out, beasts tamed, shots fired, hours of Auto Shot, traps set, Feign Deaths | Flow: where the ammo came from (bought, crafted, looted, mailed or traded) and where it went (fired, by ammo; sold or destroyed; in the quiver now), beside the quiver | Stack: the pet that was out by level, beside pets by hours with feeds, revives and the happy share. Stack: the aspect that was up by level, beside shots by casts. Traps. Feign Death (casts, deaths within 5 seconds after one). Tracking by hours |
| Rogue | A cellar at night: a chest, a rack of poison vials and a coin sack under a lantern. Callouts: locks picked with the lockpicking skill, the poison applied most, pockets picked with the gold lifted | Hours in stealth, openers from stealth, pockets picked, locks picked, poisons applied, Vanishes | Link: how a fight opened (Cheap Shot, Ambush, Garrote, Sap, no opener, not stealthed) to its first finisher (Eviscerate, Slice and Dice, Kidney Shot, Rupture, Expose Armor, none), beside stealth (hours, share of played time, seconds stealthed before the opener) | Stack: the finisher used by level, beside Pick Pocket (pockets, gold, what came out). Attacks used, beside locks (picked, lockboxes opened, skill). Poisons applied. Escapes. Kicks against interrupts landed |
| Priest | A chapel corner: a lectern, an altar, a pew and candles. Callouts: the heal cast most, players raised, shields cast with the damage they absorbed | Healing done, shields cast, players raised, Fortitudes cast, hours in Shadowform, hours of wand | Mirror: healing and damage a fight by level, beside where the healing was done (dungeons, raids, battlegrounds, the open world) with the share of the group's healing | Stack: the heal cast by level, beside healing by spell. Shadow and Holy damage spells by casts with the hours in Shadowform, beside the wand (hours, share of fight time, against other classes). Shields. Control. Buffs kept up |
| Shaman | Open ground under a storm sky: one totem of each element. A callout on each: the element's most dropped totem, its drops and the hours it stood | Totems dropped, hours a totem stood, shocks cast, hours as a Ghost Wolf, Reincarnations, Astral Recalls | Totems by element: four ranks side by side (fire, earth, water, air), each totem with its drops and hours standing | Link: the element to how the totem ended (stood its full time, replaced, gone early, still standing when the fight ended), beside totems in a fight (fights with all four down, the median, the most). Stack: elements with a totem down by level, beside shocks. Weapon imbues. Reincarnation (taken, Ankhs bought, share of deaths walked off). Ghost Wolf and Astral Recall against mounted hours |
| Mage | A tower floor at night: a portal, a sheep, a table of bottles and a bookshelf. Callouts: portals opened with the city opened most, sheep made, water conjured with how much was handed to others | Water conjured, portals opened, teleports, sheep made, Blinks, Frost Novas | Flow: where conjured water and food went (drunk, eaten, handed to another player, gone at logout, destroyed, in the bags now), beside the bags | Stack: damage by school by level, beside portals and teleports by city. Spells cast, beside the big pull (most kills in one fight, fights with five or more, hours of Blizzard). Polymorph. Escapes. Mana (Evocation, mana gems, time spent drinking against other classes) |
| Druid | A moonlit grove with the druid's forms, each form's model: bear, cat, travel and aquatic. A callout on each: the hours worn and its share of time in a form | Shapeshifts, hours as a bear, hours as a cat, hours in Travel Form, Innervates, Rebirths | Stack: the form worn by level, beside forms by hours with shifts a fight | Link: the form left to the form entered, with the shifts back into the same form inside 2 seconds, beside travelling (Travel Form, Aquatic Form and mounted hours). What each form did: bear, cat and caster attacks, beside healing by spell. Control. Prowl. Teleport: Moonglade and the buffs |

Talent modules for the builds the mocks show: Nightfall, Shadowburn and Siphon Life (Warlock, Affliction 30 / 0 / 12);
Mortal Strike, Sweeping Strikes and Enrage (Warrior, Arms 31 / 20 / 0); Seal of Command, Repentance and Consecration
(Paladin, Retribution 17 / 0 / 31); Bestial Wrath, Intimidation and Aimed Shot (Hunter, Beast Mastery 31 / 20 / 0);
Blade Flurry, Adrenaline Rush and Riposte (Rogue, Combat 5 / 31 / 15); Inner Focus, Spirit of Redemption and Lightwell
(Priest, Holy 14 / 32 / 0); Stormstrike, Elemental Focus and Flurry (Shaman, Enhancement 20 / 31 / 0); Ice Barrier,
Cold Snap and Ice Block (Mage, Frost 12 / 0 / 35); Feral Charge (Bear), Omen of Clarity and Leader of the Pack (Druid,
Feral Combat 11 / 31 / 1). Each build was checked against the client's talent table (`Talent`, `TalentTab`): a talent
on tier N needs 5 times N points in its tree before it. A talent gets a module when the character can see it work: a
spell it casts, a buff it gains or a proc it uses. A talent that only adds a percentage gets none. The list of
talents with a module is one table in code beside the feats, keyed by the talent's spell. Beyond the three of the
mock's build, a class lists at least two talents of each of its other trees, shown after them while the build holds
them. The Warlock adds Fel Domination, Demonic Sacrifice and Soul Link (Demonology, 10, 20 and 30 points before them)
and Conflagrate (Destruction, 30, beside Shadowburn at 10); the Mage and the Druid lists are in their sections above.

**Where the numbers come from.** Every module reads lines the addon already writes; the page needs no new line.

| What the page shows | Lines |
| --- | --- |
| Casts of a spell, in or out of a fight, by level and day | `C`, with `F` for the fight and `L` for the level; `CH` for the seconds of a channel |
| Time under a buff: a stance, a form, an aspect, an aura, a seal, Stealth, Shadowform, Ghost Wolf, a drink | `A` gained and ended, with its seconds |
| Things made and used up: soul shards, ammo, conjured water and food, healthstones, Ankhs, reagents | `I` with where it came from or went, `DI` for what was destroyed, `BC` for what the bags hold, `TR` for what was handed to another player |
| The pet or demon that was out, its name and family, the stable | `PT`, `PD`, `SG` |
| Totems: each drop and how long it stood | `TW` |
| Auto Shot and wand time | `AR` |
| Damage, healing, damage taken, absorbs, interrupts and dispels a fight, and the top five spells | `DM`, `DS`, and `DP` for the group's totals |
| What finished a kill, and kills inside one fight | `K` with the `C` before it, inside the `F` span |
| Deaths after a spell, and deaths walked off | `D` with the `C` before it, `SR` |
| Teleports, portals, hearthstones | `TE` |
| Mounted hours, weapon in hand, lockpicking skill, tracking | `MO`, `G` with `II`, `SL`, `MP` |
| Whether the build has a talent, and the points in it | `TA` |

Four limits, stated on the page where they bite:

- **An amount the client does not report is estimated.** Life Tap's health and mana are the rank's own numbers from
  its description (`SD`) times the taps, and the module says "estimated". No other module estimates.
- **Not recorded, so not shown.** Auras on an enemy (how long a curse or a sheep lasted), who a spell was cast on
  (who was blessed, healed or took a portal), a pet's own damage, range to the target, and rage, energy and combo
  points. A page shows none of these until the addon writes them, and the design holds no placeholder for them.
- **The top five.** The damage meter names a fight's five largest spells, so a "by spell" rank says so under it.
- **A death walked off.** `SR` says a soulstone or Reincarnation was on offer, not that it was taken. The build
  settles how a taken one is read from the lines after the death before the soulstone and Reincarnation modules
  count anything.

The web reads the lines from the six class tables the decode fills (docs/ROWS.md, "The class tables"): casts by spell and
level, time under auras and in fights, pet spans, bag changes, damage and healing meters, and the tallies of what
finished a kill, what a death followed and what a spell was cast under. The comparison against other characters of the
class reads `class_standings`, the figures of each character that its rows can answer, not a rollup. The app reads the same lines from the
character's own log and needs neither.

**Class feats.** The first design gave each class six feats of its own (most soul shards held at once, deaths within
5 seconds of a Life Tap). The class, talent-tree and role feats landed the same day, so the page shows those and the
six were dropped; what they counted stays on the page as modules. Zealot counts Repentance: Crusader Strike exists
on build 1.60.1.71037 only as a creature spell, so a rule naming it could never be earned.

**The app.** The Scarlet app gets a view named for the character's class, after Talents in its navigation. It
does not compute the charts from the local log: it reads the page from the site over the install's signed request,
`GET /installs/me/class-page/{character}` (docs/API.md), as its Feats view reads feats, so the decoder keeps the one
copy of the nine classes' derivations (the app cannot share its crate). `ClassPageJson` writes the same
`ClassPage` the web page draws, one case for each block type. The view is shown only for a character the site
answered a page for, so it is missing with uploading off, for a character not uploaded yet and for a class or a
character with no page. It draws a banner in the class colour with the class name, the character line and the link to
the page on the site, the six figures as cards, the first chart and the rows below it in panels, the talent modules,
the party rows, the comparison and the feats. Icons load from the addresses the answer gives and a card without an icon
is a plain card. The app draws no still of the head's scene: the answer names the scene and the banner stays plain.

### Search

Search finds public characters, creatures, items, objects, zones, quests and the site's own pages by name. One Action,
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

A number finds the creature, item, object, quest or zone with that id as an `exact` hit. `fuzzy` runs only when
nothing matched any other way, in any kind asked for, and the text has four characters or more, so a correct spelling
costs what it cost before and never pulls in look-alikes. It uses the trigram indexes the search already has (`word_similarity` at 0.6, PostgreSQL's default:
0.5 let "stats" find Starving Mountain Lion), with no new index. It finds a dropped, doubled or swapped letter inside a
longer word; two letters swapped in a four-letter word share too few trigrams to be found. Close spellings list the
closest first.

**Order inside a tier** is by how much players did with the thing, most first, then the shorter name: kills for a
creature, turn-ins for a quest, hours played for a zone, sources for an item, times opened for an object, time played for a character. An object with no score is not found. The
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
- **Phone header**: Stats, Map and Loot stay in the landing and demo header on a phone; the other pages drop out first.
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

**Personal maps.** `/map?character=Name-Surname` and `/account/map` draw one character's trails, or every character of the signed-in account together, on the same viewer, from `/c/{name}/trails` and `/account/map/trails` (`MapTrails`), which the page reads after the first paint: every cell of the finest path grid (1536 columns, no threshold) with its points counted three ways, on foot (`path_points.mode` running, walking or swimming), mounted (mounted or flying) and of no known mode (a session decoded before version 16, or a stretch under a yard), and each flight route (`flights`) once with the times it was flown, as a straight line from where the flight began to where it landed, since a flight records no points on the way. A **Travel** switch (All travel, On foot, Mounted) keeps the cells of its modes; All travel counts the unknown ones too. A **Flight paths** switch, on by default, draws the routes as dashed blue lines over the scarlet paths. Both switches are the page's own and change no request: the address keeps them as `travel=foot|mounted` and `flights=off`. Visibility is the character page's: a character private to the viewer, or unknown, is a 404 on the page and on its trails. `character` is one of the query parameters the page cache keeps, so a visitor's cached answer for one character is never another's or the world's. A signed-in viewer gets a **Whose map** switch on `/map` and on the personal maps: Everyone, All my characters (with more than one), and each of their characters, plus the character on show when it is someone else's; more than four choices become a drop-down. The busiest-zones list and the readout count the subject's own play (`MapZones` over a `CharacterScope`). The character page's map legend links its world map. Everyone's map has no travel or flight switch: the public heat has no mode, and a public flight layer would need its own minimum-characters rule.

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
