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
instance type or the instance id changes; zero-length spans are not written. `at` is the span start.

| Key | Type | |
| --- | --- | --- |
| `end` | int | ms |
| `ms` | int | `end - at` |
| `afk_ms` | int | time inside the span between `AFK 1` and `AFK 0` |
| `zone` | string or null | zone name of the `Z` line |
| `instance_type` | string or null | `none`, `party`, `raid`, `pvp`, `arena`, `scenario` |
| `instance_id` | int or null | `instanceMapID` |

### `level_ups` (core: `L`)

| Key | Type | |
| --- | --- | --- |
| `reached` | int | the level reached (the row's `level` is the same) |
| `since_ms` | int | ms since the previous `L` or the session start: time at the old level in this session |
| `tp_level_s` | int or null | `TP` levelSeconds, the latest one before this line in the session |

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
| `bosses` | int | `B` lines while inside |
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

### `creature_sightings` (world: `NS`)

`npc_id`, `npc_level` (int or null; -1 when the client hides it), `classification` (string or null),
`creature_type` (string or null), `family` (string or null), `reaction` (int or null), `map`, `cell_x`, `cell_y`
(int or null; cells are 0.015 of the map).

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
| `start`, `end` | int | ms. `start` is the `S` line (the first line when there is none); `end` is the latest time of any line up to `ES`, except `TP` and `DP` |
| `has_start`, `ended` | bool | an `S` line exists; an `ES` line exists |
| `played_ms`, `played_s` | int | `end - start`; seconds rounded half up. This is OVERVIEW.md's played time |
| `lines` | int | lines in all parts of the session, `pn` lines and addon lines included |
| `groups` | string[] | the group keys that had a part |
| `build`, `class`, `race`, `faction`, `addon_version` | string or null | `S` fields 1, 2, 3, 5, 6 |
| `level_start`, `level_end` | int or null | the level at `S`; the level after the last `L` |
| `xp`, `xp_max` | int or null | the experience bar, last seen (`S` or `X`) |
| `money_start`, `money_end` | int or null | copper at `S` (or the first `ST`); the balance after the last money line |
| `kills`, `deaths`, `quests_done` | int | counts of `K`, `D`, `Q 2` |
| `copper_earned`, `copper_spent` | int | sums over `M` lines |
| `xp_total` | int | sum of `X` amounts |
| `zones` | int | distinct zone names seen |
| `dungeon_runs` | int | runs written in this session (before the joining rule above) |
| `tp_total_s`, `tp_level_s` | int or null | the last `TP` |
| `gr_preset` | int or null | `GR` preset, the first one in the session |
| `gr_hardcore` | bool or null | `GR` hardcore |
| `war_mode` | string | `on`, `off` or `unknown` from the first `PB` line (warModeActive) |
| `xp_off` | bool or null | the first `XD` line |
| `game_mode` | int or null | the first `GA` line |
| `sex`, `class_id`, `race_id` | int or null | the first `CD` line |
| `flags` | string[] | plausibility flags, below |

**Ruleset** of a session: (`gr_preset`, `gr_hardcore`, `war_mode`, `xp_off`, `game_mode`), the signals of DESIGN.md
"Rulesets". The `rulesets` row is looked up or created from them.

**Plausibility flags** (a character with any flag in a session stays out of leaderboards and rollups until the
flags stop; thresholds are the decoder's):

| Flag | Raised when |
| --- | --- |
| `time_order` | a part has a line earlier than the one before it (a negative `dt`) |
| `session_over_24h` | `played_ms` is more than 24 hours |
| `xp_over_level` | the XP gained at one level (below 60) is more than 1.5 times the XP that level needs (OVERVIEW.md table) |
| `level_skip` | an `L` line raises the level by more than 1 |
| `levels_too_fast` | `played_ms` divided by the levels gained in the session is under 20 seconds |
| `ruleset_changed` | a later `GR`, `PB`, `XD` or `GA` line differs from the first one |

## Counting rules

The decoder follows docs/OVERVIEW.md of the Scarlet repo. Level and zone splits: time, XP, kills, deaths and quests
go to the level and zone current when they happen (`level` and `zone_id` on the row; the zone's time is the sum of
`play_spans` by `zone` name). A line before the session's `S` line is not counted. Nothing after `ES` is read.

### Ties

The parts keep each group's lines in recorded order but not the order between groups. Lines in the same millisecond
are merged by group (core first, then the order of `groups.tsv`) with `ES` last. This is exact for live lines, which
are recorded to 10 ms, and for ties inside one group. It can put a line one position off when a core line and a line
of another group share a millisecond. Measured against the companion's overview on the three real characters
(MEASUREMENTS.md): every per-session total matches; the only differences are in the per-level split, where a quest
turn-in and a level-up in the same second land on the other side of the level boundary in a few sessions.
