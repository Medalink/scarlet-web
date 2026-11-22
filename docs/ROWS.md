# Decoded rows

`scarlet-part decode` reads the stored parts of **one session** (any subset of its groups) and writes the rows of
DESIGN.md "Decoded facts" as newline-delimited JSON on stdout: one object per line, `"t"` names the table, keys always
in the order below, every key always present (`null` when there is no value). The ingest job builds its tables from
this file.

```
scarlet-part decode <part file>... | --parts-dir <dir> [--session <n>] | --stdin
```

- A part file is the part text, or its storage form (zstd; the binary tells them apart by the zstd magic).
- `--stdin` reads stored parts, each as a 4-byte little-endian length and the zstd body (no outer compression), so
  the job can pipe a session's `parts.body` values in without touching files.
- Parts of more than one session in one call are decoded one session after another, in session order.
- Exit status 0 and all rows written, or non-zero and a message on stderr. A part with a bad header, another session
  or character in the same call, or an unknown group key is an error. A line the decoder does not know is skipped.
- Decoding is a pure function of the parts given. Run it again on the same parts and the output is byte-identical.
  The job deletes the session's rows first and inserts the new ones in one transaction.
- The `sessions` row of a session is its **last** line; a session whose output has no `sessions` row did not finish.

## Types and units

- `int`: JSON integer, never a float. `string`, `bool`. `null` where noted.
- Times are **epoch milliseconds** (`at`, `start`, `end`, `entered`, `left_at`), durations `*_ms` milliseconds,
  `*_s` seconds. `session` is the part key: the session start in epoch **seconds**.
- Coordinates `x`, `y` are the map position times 10000. Money is copper.
- Strings are UTF-8, escaped as JSON. A name of another player is a `~` pseudonym unless the row came from a `social`
  part (`pn` rows give the clear name; they exist only when `social` was uploaded).

## Common keys

Fact rows start with the same five keys (the job adds `character_id` and `ruleset_id` from the session):

| Key | Type | Meaning |
| --- | --- | --- |
| `t` | string | table name |
| `session` | int | session key (seconds) |
| `at` | int | time of the row, ms |
| `level` | int or null | level at that time (`S` field 4, then each `L`); null before it is known |
| `zone_id` | int or null | the zone at that time: `uiMapID` of the last `Z` line that named a zone; for an instance, which has no uiMapID, the **negative** of its `instanceMapID`; null before the first `Z` |

`names`, `items`, `pn` and `sessions` carry `session` only (and `sessions` has its own time keys).

## Tables

Keys after the common ones, in order. "From" is the line code; groups are those of `groups.tsv`.

### `play_spans` (core: `S`, `ES`, `L`, `Z`, `AFK`)

A stretch with one level, zone and instance. A new span starts when the level, the zone name, the zone id, the
instance type or the instance id changes, and at every UTC hour boundary, so a row lies inside one UTC clock hour
and `afk_ms` is the AFK time inside that row. A session is cut at 168 hour boundaries at most (a week, beyond the
24 hours that flag it `session_over_24h`); the rest of its last span stays one row. Zero-length spans are not
written. `at` is the span start.

| Key | Type | |
| --- | --- | --- |
| `end` | int | ms |
| `ms` | int | `end - at` |
| `afk_ms` | int or null | time inside the span between `AFK 1` and `AFK 0` |
| `zone` | string or null | zone name of the `Z` line |
| `instance_type` | string or null | `none`, `party`, `raid`, `pvp`, `arena`, `scenario` |
| `instance_id` | int or null | `instanceMapID` |

### `level_ups` (core: `L`)

| Key | Type | |
| --- | --- | --- |
| `reached` | int | the level reached (the row's `level` is the same) |
| `since_ms` | int | ms since the previous `L` or the session start: time at the old level in this session |
| `tp_level_s` | int or null | `TP` levelSeconds, the latest one before this line in the session |
| `ding` | bool | the level before the line was known and lower than `reached`. False for an `L` line that repeats the level (an import writes one at its start), so it is no level-up Scarlet saw and the character was already at that level when first seen |

### `xp_gains` (xp: `X`)

| Key | Type | |
| --- | --- | --- |
| `amount` | int | `X` field 1 |
| `source` | string | `k` kill outside a dungeon or raid, `d` kill while the last `Z` is `party` or `raid`, `q` quest, `e` exploring, `o` anything else |
| `source_id` | int or null | npcID for `k`/`d`, questID for `q`, null otherwise |
| `xp`, `xp_max` | int or null | the bar after the gain |

### `kills` (combat: `K`)

| Key | Type | |
| --- | --- | --- |
| `npc_id` | int or null | null for a player (`K p`) |
| `player` | bool | `K` npcID is `p` |
| `by` | int or null | `1` you or your pet, `0` a party member, `2` unattributed |

### `deaths` (combat: `D`, `DL`, `DR`)

One row per `D`, at its time. `DL` is joined when it follows within 60 s and the death has none yet; the `DR` line
with pos `1` the same way. Older logs have no killer fields, a death without a recap has them null.

| Key | Type | |
| --- | --- | --- |
| `map_id`, `x`, `y` | int or null | `DL` |
| `killer_kind` | string or null | `p` player, `c` creature |
| `killer` | string or null | `DR` source: a creature name, a pseudonym for a player, or an environment name |
| `killer_level` | int or null | |
| `killer_class` | string or null | class file of a player, classification of a creature |
| `killer_race`, `killer_guild` | string or null | player only; the guild is a pseudonym outside `social` |
| `spell_id`, `damage`, `overkill`, `hits` | int or null | `DR` fields 1, 2, 6, 16 |

### `dungeon_runs` (combat: `Z`, `B`, `D`, using core's `Z`)

The runs of this session by the rule of OVERVIEW.md: a `Z` whose instanceType is `party` or `raid` starts a run of its
`instanceMapID`, unless the last run started was the same instance and was left less than 30 minutes ago (then it is
the same run: `entries` counts the stays). `at` is the first entry; level and zone are those at that time.

| Key | Type | |
| --- | --- | --- |
| `index` | int | order in the session, from 0 |
| `instance_id` | int | `instanceMapID` |
| `name` | string or null | zone name |
| `instance_type` | string or null | `party` or `raid` |
| `difficulty_id` | int or null | |
| `left_at` | int or null | ms of the last leave. Null when the run was never left by a `Z` (a direct move to another dungeon) |
| `left_by_session_end` | bool | the session ended inside; `left_at` is then the session end |
| `inside_ms` | int | time spent inside, all stays |
| `entries` | int | stays |
| `bosses` | int | distinct bosses among the `B` lines while inside, by the line's id or, when it has none, its name |
| `deaths` | int | `D` lines while inside |
| `group_size` | int or null | group size at the first entry (`GP`, `LM`) |

**Joining sessions.** The rule needs the previous session, which one decode cannot see, so the job applies it:
take a character's sessions in order; remember the instance and `left_at` of the last run of the last session that
had a run. For the first run of the next session: if its `instance_id` equals the remembered one, the remembered
`left_at` is not null and `at - left_at < 1_800_000` ms, it is the same run, not a new one (it is not counted as
started, and its time and bosses are added to the earlier run). Runs after the first are as the decoder wrote them.
The run count of OVERVIEW.md (`r`) is the number of rows left after this.

### `quest_events` (quests: `Q`)

| Key | Type | |
| --- | --- | --- |
| `quest_id` | int or null | |
| `event` | string | `accepted` (`Q 1`), `turned_in` (`Q 2`), `abandoned` (`Q 3`) |
| `xp`, `copper` | int or null | `Q 2` rewards |

### `money_changes` (economy: `M`; core: `S`)

| Key | Type | |
| --- | --- | --- |
| `src` | string | `M` a money line; `S` the balance at the session start (only when the `S` line has it, all amounts 0) |
| `copper` | int | signed change; with a transfer included |
| `transfer` | int | the part moved to or from your own characters |
| `earned` | int | `max(copper - transfer, 0)` |
| `spent` | int | `max(transfer - copper, 0)` |
| `balance` | int or null | copper carried after the line: the line's own balance, else the last known balance plus `copper`. Null until a balance is known in this session. `build` group's `ST` lines also set the balance |

OVERVIEW.md counts `earned` and `spent` of the `M` rows. The balance before the first anchor is not walked back;
the job may do so from the following sessions.

### `loot_opens` (loot: `LO`)

| Key | Type | |
| --- | --- | --- |
| `source_kind` | string | `c` creature, `g` object, `i` item from the bags, `o` other |
| `source_id` | int or null | npcID, objectID or itemID |
| `first_read` | bool or null | `again` 0 true, 1 false, null for an item |
| `slots` | int or null | |
| `has_items` | bool | the line lists items (lines from before 2026-11-20 15:00 do not) |
| `loot_rule`, `threshold`, `group_size` | string, int or null, int or null | the rule in force: `LM` method (`unknown` until a line says), threshold, and the group size from `GP`/`LM` |

### `loot_drops` (loot: `LO`, `O`)

| Key | Type | |
| --- | --- | --- |
| `via` | string | `window`: an item in a loot window (`LO` items); `receipt`: an `O` line |
| `source_kind`, `source_id`, `first_read` | | as `loot_opens`; for a receipt, the last `LO` within 5 s, else null |
| `item_id` | int | |
| `quantity` | int | |
| `loot_rule`, `threshold`, `group_size` | | as `loot_opens` |

Drop rates count `window` rows only (coin and currency entries of a window are not rows). `receipt` rows give the
attribution for logs that have no item list.

### `skill_ups` (professions: `SK`, `SL`)

| Key | Type | |
| --- | --- | --- |
| `kind` | string | `SK` (a skill went up; named) or `SL` (a skill line, by id) |
| `skill` | string or null | `SK` name |
| `skill_id` | int or null | `SL` skillID |
| `rank` | int or null | |
| `max` | int or null | `SL` |
| `baseline` | bool or null | `SL` baseline `s`; null for `SK` |

### `crafts` (professions: `CR`)

`item_id`, `quantity`, `crit`, `multicraft`, `recipe_id` (recipeSpellID), `quality`: int or null each.

### `path_points` (movement: `P`)

`map`, `x`, `y`, `facing`: int or null each. By far the most rows (about 80 percent).

### `movement_stretches` (movement: `W`)

A stretch of movement ended: one row per `W` line. `mode` (string or null) is `f` running, `w` walking,
`m` mounted, `s` swimming or `a` flying; `yards` (int or null) is the length of the stretch in yards,
`seconds` (int or null) how long it lasted and `jumps` (int or null) how many jumps were in it. The
`jumpAt` field, the second of each jump, is not kept.

### `emotes` (social: `EM`)

One row per emote use. `token` (string or null) is the emote the way the addon writes it (`LOL`, `WAVE`).

### `hardcore_deaths` (social: `HD`)

The server's notice that a hardcore character died. The same notice reaches every uploader online, so each of them
writes a row and the pages count a death once: by `who` (the notice when it names nobody), `died_level` and the minute.
`level` and `zone_id` are the uploader's, as on every fact row.

| Key | Type | |
| --- | --- | --- |
| `who` | string or null | the Name-Surname in the notice |
| `died_level` | int or null | the level the notice ends with |
| `notice` | string or null | the notice text, 200 characters at most |

### `who_results` (social: `WH`)

One row of a /who result: `name`, `player_level` (int or null), `class_file`, `race`, `zone_name`, `guild`
(strings or null).

### `inspected_gear` (social: `IG`)

`name` and `items` (string or null): the `slot:itemID:enchantID:suffixID` list, joined by commas.

### `battleground_matches` (pvp: `BM`)

One row for each `BM 0`, a match that ended, at its end. The zone name, `rated` and `arena` come from the `BM 1` of the
same instance when the session has it (`arena` from the end line first).

| Key | Type | |
| --- | --- | --- |
| `instance_id` | int or null | `instanceMapID` |
| `zone_name` | string or null | |
| `rated`, `arena`, `affects_rating` | bool or null | |
| `team` | int or null | the uploader's side: `0` Horde, `1` Alliance |
| `winner` | int or null | the winning side as the client reports it |
| `duration` | int or null | the client's number |
| `result` | string or null | `w` won, `l` lost, `d` drawn |

### `battleground_scores` (social: `BO`)

Another player's scoreboard row. `match_at` (ms) is the end of the match: the `BM 0` of the session within the 10
minutes before the row. When the session has none, the first row of a run of rows less than 2 minutes apart gives
`match_at`, and `instance_id` is null. One uploader's rows of one match share `match_at`.

| Key | Type | |
| --- | --- | --- |
| `match_at` | int | ms |
| `instance_id` | int or null | |
| `name` | string or null | Name-Surname, in clear (social group) |
| `faction` | int or null | `0` Horde, `1` Alliance |
| `class_file` | string or null | |
| `killing_blows`, `honorable_kills`, `deaths`, `honor_gained`, `damage_done`, `healing_done` | int or null | `?` is null |
| `stats` | string or null | the mode's objective numbers, `id:value` joined by commas |

`BS`, the uploader's own row, is not decoded.

### `camp_stays` (combat: `A`, `C`, `D`; xp: `AFK`; professions: `CR`; movement: `P`)

One row per stay by a campfire: the span the character holds the aura Campfire Nearby (1283391), which the client
gives only while a fire is in range. `at` is the start of the stay, and `level` and `zone_id` are those at that time.
The spell ids and their kinds are one table in `part/src/camp.rs` (`App\Support\CampSpell` on the PHP side). An `A 0`
for an aura that is not open is ignored, and so is an `A 1` for a stay, sit or tent aura that is already open. An `A 1`
for a Camp Benefits aura or benefit buff that is already open is a new gain: it closes the held row at that time and
opens the next. A stay still open at the session's end closes at the session's end. The `CP` camp-visit line is not read: it also counts the hour-long Camp Benefits buff, so
it overstates the time at a camp (Medalink/scarlet#2).

| Key | Type | |
| --- | --- | --- |
| `seconds` | int | length of the stay: the `seconds` of the `A 0` line, rounded; else the time to the session's end |
| `carried` | bool | the aura was on at the session start (`A 1` initial `s`): the row continues a stay of the session before |
| `ended` | string | `w` the aura went while the session ran, `s` the session ended near the fire |
| `afk_seconds` | int or null | time between `AFK 1` and `AFK 0` inside the stay. Null without an `xp` part |
| `sat_seconds` | int | time inside the stay that a sit timer aura (Welcoming Campfire, 1229739 or 1289723) was held |
| `benefits` | bool | an `A 1` of Camp Benefits (1229741) fell inside the stay. A gain that was already on at the session start does not count. A sit that finishes while the buff is held logs no `A 1` in the recordings, so such a stay is false |
| `tent_seconds` | int | time inside the stay that the tent rest aura (1229487) was held |
| `campfire_spell_id` | int or null | the campfire the character placed (1307227 Basic, 1307252 Journeyman, 1307237 Expert): the last `C` of one from 60 s before the stay to its end, and not before the end of the stay before it, so one cast counts for one stay. Null when the fire was someone else's or already there |
| `placed_tent`, `placed_chair` | bool | the same for the Camp Tent (1307230) and Camp Chair (1307229) casts |
| `crafts` | int or null | `CR` lines inside the stay. Null without a `professions` part |
| `died_after` | bool | a `D` from 2 s before the stay's end (the `D` line can come before the `A 0`) to 300 s after it. False for a stay the session ended |
| `map`, `x`, `y` | int or null | the last `P` at or before the start of the stay, as `path_points` reads it. Null without a `movement` part or before a first `P` |

The ingest adds `u` and `v` from `map` and `x`, `y` the way it does for `path_points`.

### `camp_benefits` (combat: `A`, `D`)

One row per Camp Benefits aura (1229741) or benefit buff held, whether or not a stay of the same session holds it: the
nine buffs of the objects placed at a camp are 1229519 Camp Chair, 1230124 First Aid Kit, 1230172 Sharpening Wheel,
1230164 Lodestone, 1230587 Mana Well, 1229513 Incense Candle, 1229718 Faction Banner, 1230098 Fish Bowl and 1230653
Enchanted Lute. `at` is when it was gained.

| Key | Type | |
| --- | --- | --- |
| `spell_id` | int | |
| `seconds` | int | held: the `seconds` of the `A 0` line, rounded; else the time to the next gain of the same spell or to the session's end |
| `carried` | bool | already on at the session start (`A 1` initial `s`) |
| `lost_to_death` | bool | a `D` line within 2 s of the aura ending, before or after. False when the session ended it or a new gain replaced it |

### `quest_baselines` (quests: `QB`)

One row per read of the quests the client says the character completed: a lifetime list, whatever Scarlet saw. The
`QB` lines of one read come in a row with the same total, 400 ids a line, so the decoder joins them: a read ends when
its ids reach `total`, when a `QB` of another total comes, or when the session ends. `at` is the first line of the read.

| Key | Type | |
| --- | --- | --- |
| `total` | int | the size of the whole list |
| `ids` | string | the questIDs of the read, comma-separated, in the order the lines gave them. Shorter than `total` when a line is missing |

### `title_baselines` (collections: `KT`)

One row per `KT` line: the titles the character knows. `total` (int) is how many it knows and `ids` (string) their ids,
comma-separated.

### `explored_baselines` (world: `EB`)

One row per read of how much of each zone map the character uncovered. The `EB` lines of one read follow each other
(at most 150 maps a line), so the decoder joins the lines that come within 5 seconds of the one before. `at` is the
first line of the read.

| Key | Type | |
| --- | --- | --- |
| `zones` | int | the maps of the read with at least one explored overlay |
| `maps` | string | `uiMapID:overlays` for each map, comma-separated, in map order; a map named twice keeps its larger count |

### `instance_locks` (combat: `IS`)

One row per `IS` line: an instance lock from the client's raid info list, or a world boss lock. Keys after the common ones:

| Key | Type | |
| --- | --- | --- |
| `name` | string or null | the instance's or world boss's name |
| `instance_id` | int or null | the instance's map id, or the client's world boss id for raid `2`. Null when the client gives none |
| `difficulty_id`, `max_players`, `done`, `total` | int or null | empty for a world boss lock |
| `reset_seconds` | int or null | the time until the lock resets, counted from `at` |
| `locked`, `extended` | bool | |
| `raid` | int | `1` a raid, `2` a world boss lock, `0` otherwise |
| `why` | string or null | `s` the first read of a session, `c` a lock that is new or whose `done` moved |

The client leaves out a lock that has run out and is not extended, so a lock still holds while its latest row has
`at + reset_seconds` ahead and `locked` or `extended`. A lock that was reset early still shows until then, since nothing
is written for it.

### `creature_sightings` (world: `NS`)

`npc_id`, `npc_level` (int or null; -1 when the client hides it), `classification` (string or null),
`creature_type` (string or null), `family` (string or null), `reaction` (int or null), `map`, `cell_x`, `cell_y`
(int or null; cells are 0.015 of the map).

### `talent_changes` (build: `TA`)

A talent point picked, refunded or moved after the first read of the session (`TA` with why `c`). The baseline `TA`
lines of why `s` are the ranks held at the first read: they have no order, so they write no row; they set the ranks
the later rows count from. Keys after the common ones:

| Key | Type | |
| --- | --- | --- |
| `spec_group` | int | the talent group, `1` or `2` |
| `node_id`, `entry_id` | int or null | the node and the entry it holds |
| `rank`, `old_rank` | int | rank after and before |
| `max_ranks`, `spell_id` | int or null | |
| `kind` | string | `pick` (rank rose), `refund` (rank fell), `swap` (a choice node changed entry at the same rank) |
| `point_no` | int or null | for a pick: the points the group holds after it, so the ordinal of the point in the build. Null when the session had no baseline of the group first |
| `respec_index` | int or null | the respec of this session the row belongs to (below); null for a point earned by levelling |

### `talent_respecs` (build: `TA`, `TQ`)

Refunds that came in one read (same `at`) are one respec; the picks that follow, at the same level, belong to it until
the group holds the points it held before. A level up ends it. One row per respec, written at the end of the session.

| Key | Type | |
| --- | --- | --- |
| `spec_group` | int | |
| `kind` | string | `reset` (a `TQ r` within 60 s before), `spec` (a `TQ i` within 60 s before), else `refund` |
| `cost_copper` | int or null | the cost of a `TQ p` offer within 10 minutes before |
| `points_moved` | int | points refunded |
| `hash_before`, `hash_after` | string | the build hash before the refunds and when the respec ended (the points were back, a level up, or the session ended) |
| `respec_index` | int | order in the session, from 0; the same number as on `talent_changes` |

### `talent_snapshots` (build: `TG`, `TA`)

One row per `TG` line whose build or loadout differs from the group's last row of the session (the first `TG` of a
group always writes one). A `TG` whose group is not a number (older logs) writes none.

| Key | Type | |
| --- | --- | --- |
| `spec_group`, `groups` | int or null | the active group and how many exist |
| `spec_id`, `spec` | int, string or null | |
| `tree_id` | int | |
| `tab_points` | string or null | the `TG` tabs field, `groupID:spent:available` comma-joined |
| `spec_tab_id` | int or null | the tab with the most points spent; null when none or two tie |
| `class_points`, `spec_points`, `config_id` | int or null | |
| `build_hash` | string | 16 hex digits, FNV-1a of the sorted `node:entry:rank` list. Equal ranks give equal hashes |
| `ranks` | string | `node:entry:rank:spell:tab` for every node with a rank, comma-joined, sorted by node, from the `TA` lines read so far; spell and tab are empty when the line had none |
| `loadout`, `why` | string or null, string | the `TG` fields |

### `talent_group_spans` (build: `TG`)

One row when the session's first build is read (`TG` why `s`) and when the active group changes (why `w`): the time
from the row to the next row of the session, or to the session's end, was played with `spec_group` active. The
character page's play time per spec group sums those. A `TG` whose group is not a number (older logs) writes none,
and a row whose group the last one already named writes none.

| Key | Type | |
| --- | --- | --- |
| `spec_group` | int | the talent group, `1` or `2` |

### `talent_trees`, `talent_nodes` (build: `TV`, `TD`; names group)

The layout of a class tree, which is the same for every player of a client build. Like `names`, these rows carry
`session` only. `build` is the client version of the `S` line (a string such as `1.60.1`, null when the line has none). A `TD` whose tree has no `TV` earlier in the session is
skipped. `layout_hash` is the `TV` hash.

`talent_trees`: `build`, `tree_id`, `class_id`, `node_count`, `layout_hash`.
`talent_nodes`: `build`, `tree_id`, `layout_hash`, `node_id`, `pos_x`, `pos_y` (the client's raw numbers, drawn at
`pos_x / 10` and `-pos_y / 10`), `node_type`, `max_ranks`, `tab_id`, `entries` (`entryID:spellID:maxRanks`
comma-joined), `edges` (`targetNode:type` comma-joined).

### `zones` (core: `Z`)

One row per distinct (zone id, zone name) in the session. `at` is the session start, `level` and `zone_id` are null.

| Key | Type | |
| --- | --- | --- |
| `id` | int | the zone id as defined under Common keys |
| `name`, `subzone` | string or null | of the first `Z` line that had them |
| `instance_id` | int or null | |
| `instance_type` | string or null | |
| `difficulty_id` | int or null | |

### `names` (names group)

`session`, `kind` (`npc`, `spell`, `item`, `quest`, `currency`, `skill`, `statistic`, `achievement`, `faction`,
`gossip`, `stat`, `flight`, `talenttab`), `id` (int), `name` (string). One row per distinct (kind, id) of the
session. A name becomes the shown one for an id when most uploads agree on it (DESIGN.md).

### `items` (names group: `II`, `IT`, `IQ`)

`session`, `item_id`, `class_id`, `subclass_id`, `equip_loc`, `item_level`, `required_level`, `sell_price`,
`bind_type`, `stack`, `quality`, `tooltip`: null where the session had no such line.

### `pn` (social)

`session`, `pseudonym`, `name`: the clear name behind a `~` pseudonym, from the part's `pn` lines.

### `sessions`

One per decode, last. Keys in order:

| Key | Type | |
| --- | --- | --- |
| `session` | int | key |
| `start`, `end` | int | ms. `start` is the `S` line (the first line when there is none); `end` is the latest time of any line from `S` through `ES`, except `TP` and `DP`. Name lines count too |
| `has_start`, `ended` | bool | an `S` line exists; an `ES` line exists |
| `played_ms`, `played_s` | int | `end - start`; seconds rounded half up. This is OVERVIEW.md's played time before the reload credit, which needs the session before and is applied across sessions (`counter_*`, below) |
| `lines` | int | lines in all parts of the session, `pn` lines and addon lines included |
| `decoder_version` | int | `DECODER_VERSION` of the decoder that wrote the rows; bumped whenever the rows it writes change |
| `groups` | string[] | the group keys that had a part |
| `build`, `class`, `race`, `faction`, `addon_version` | string or null | `S` fields 1, 2, 3, 5, 6 |
| `level_start`, `level_end` | int or null | the level at `S`; the level after the last `L` |
| `xp`, `xp_max` | int or null | the experience bar, last seen (`S` or `X`) |
| `money_start`, `money_end` | int or null | copper at `S` (or the first `ST`); the balance after the last money line. `money_end` is null without an `economy` part |
| `kills`, `deaths` | int or null | counts of `K`, `D`. null without a `combat` part |
| `quests_done` | int or null | count of `Q 2`. null without a `quests` part |
| `copper_earned`, `copper_spent` | int or null | sums over `M` lines. null without an `economy` part |
| `xp_total` | int or null | sum of `X` amounts. null without an `xp` part |
| `zones` | int | distinct zone names seen |
| `dungeon_runs` | int or null | runs written in this session (before the joining rule above). null without a `combat` part |
| `tp_total_s`, `tp_level_s` | int or null | the last `TP` |
| `counter_start_ms`, `counter_end_ms` | int or null | the client's played counter at `start` and at `end`: the first `TP` read's total less the time from `start` to the read, and the last read's total plus the time from the read to `end`. null without a `TP` read. The client keeps counting through a reload, so the gap between two sessions' counters is played time that `played_ms` does not hold |
| `gr_preset` | int or null | `GR` preset, the first one in the session |
| `gr_hardcore` | bool or null | `GR` hardcore |
| `war_mode` | string | `on`, `off` or `unknown` from the first `PB` line (warModeActive) |
| `xp_off` | bool or null | the first `XD` line |
| `game_mode` | int or null | the first `GA` line |
| `mode_record` | int or null | `RU` record, the game mode record id the realm picker switches by; the first one in the session |
| `server_id` | int or null | `RU` server, the realm id inside the character's GUID; the first one in the session. The latest one of a character is its `home_realm_id`, which names its ruleset when the Armory has not (docs/DESIGN.md, Rulesets) |
| `sex`, `class_id`, `race_id` | int or null | the first `CD` line |
| `flags` | string[] | plausibility flags, below |

**Ruleset** of a session: the kind of its character (hardcore flag, then Armory, declared kind, home realm), DESIGN.md
"Rulesets". The `rulesets` row of that kind is looked up or created. `gr_preset`, `gr_hardcore`, `war_mode`, `game_mode`
and `mode_record` stay in the session's `extra`; `xp_off` is the column `sessions.xp_off`.

**Plausibility flags** (a character with any flag in a session stays out of leaderboards and rollups until the
flags stop; thresholds are the decoder's):

| Flag | Raised when |
| --- | --- |
| `time_order` | a part has a line earlier than the one before it (a negative `dt`). The first line of a part has none before it: its `dt` counts from the session's start, and a line from just before the session began is not a step back |
| `session_over_24h` | `played_ms` is more than 24 hours |
| `xp_over_level` | the XP gained at one level (below 60) is more than 1.5 times the XP that level needs (OVERVIEW.md table) |
| `level_skip` | an `L` line raises the level by more than 1 |
| `levels_too_fast` | `played_ms` divided by the levels gained in the session is under 20 seconds. An `L` line that repeats the current level gains none |
| `ruleset_changed` | a later `GR`, `PB`, `XD`, `GA` or `RU` (record) line differs from the first one |

## Missing groups

A session with no part for a group (the player switched it off, or the session had no line of it) cannot be told from
a session where the thing did not happen, except by `groups` on the `sessions` row. The decoder therefore writes
nothing it cannot know: the `sessions` counters of a missing group are null, not 0; `play_spans.afk_ms` is null
without an `xp` part; no `dungeon_runs` row and no `money_changes` row of `src` `S` is written without a `combat`
or an `economy` part. A job that sums counters treats null as "not sent", never as 0.
Removing a group from the server nulls the same counters (`SessionRows::GROUP_COUNTERS`, `distance` for `movement`
included) and `play_spans.afk_ms` for `xp` (`FactTable::groupColumns`), so a removed group reads as one never sent.
The same holds for `camp_stays`: `afk_seconds` is null without `xp`, `crafts` without `professions`, and `map`, `x`, `y`,
`u`, `v` without `movement`.

## Counting rules

The decoder follows docs/OVERVIEW.md of the Scarlet repo. Level and zone splits: time, XP, kills, deaths and quests
go to the level and zone current when they happen (`level` and `zone_id` on the row; the zone's time is the sum of
`play_spans` by `zone` name). A line before the session's `S` line is not counted, except that the signal lines `GR`, `PB`, `XD`, `GA`, `RU`, `CD`, `GP`
and `LM` and the name lines are read from the login lines that precede `S`, so the ruleset is known. Nothing after
`ES` is read. Lines the builder holds back after the last `ES` are not in any part.

### Ties

The parts keep each group's lines in recorded order but not the order between groups. Lines in the same millisecond
are merged by group (core first, then the order of `groups.tsv`) with `ES` last. This is exact for live lines, which
are recorded to 10 ms, and for ties inside one group. It can put a line one position off when a core line and a line
of another group share a millisecond. Measured against the companion's overview on the three real characters
(MEASUREMENTS.md): every per-session total matches; the only differences are in the per-level split, where a quest
turn-in and a level-up in the same second land on the other side of the level boundary in a few sessions.
