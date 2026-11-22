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
| `at` | int | time of the row, ms. A part whose session, or a line whose running time, is outside year 4713 BC to 294276 (`timestamptz`) is refused: `a part has no valid header` or `bad line in a <group> part` (decoder version 14) |
| `level` | int or null | level at that time (`S` field 4, then each `L`); null before it is known. A level outside 1 to 32767 (`smallint`) counts as unknown: an `L` line with one is skipped and an `S` field 4 with one gives null (decoder version 14) |
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

### `kills` (combat: `K`; loot: `LM`; social: `GP`)

| Key | Type | |
| --- | --- | --- |
| `npc_id` | int or null | null for a player (`K p`) |
| `player` | bool | `K` npcID is `p` |
| `by` | int or null | `1` you or your pet, `0` a party member, `2` unattributed |
| `loot_rule`, `group_size` | string or null, int or null | the rule in force at the kill, as on `loot_opens`: the `LM` method (`unknown` until a line says) and the group size from `GP`/`LM`. No threshold. Both null without a `loot` part, and a removed `loot` nulls them (`FactTable::groupColumns`) (decoder version 10) |
| `group_at` | int or null | the `at` of the `group_spans` row in force (a timestamp column). Null while solo, and without a `social` part; a removed `social` nulls it (decoder version 11) |
| `corpse_key` | string or null | the last field of `K`: 10 lowercase hex digits that name the creature's corpse (see "Corpse key" below). Null for a line from before 2026-11-22, for a player (`K p`) and for any other text (decoder version 12) |

### `deaths` (combat: `D`, `DL`, `DR`)

One row per `D`, at its time. A `D` within 3 s of the one before is the same death (lines before 2026-11-22 could
write one twice) and adds no row, count or run death. `DL` is joined when it follows within 60 s and the death has
none yet; the `DR` line with pos `1` the same way. Older logs have no killer fields, a death without a recap has them
null.

| Key | Type | |
| --- | --- | --- |
| `map_id`, `x`, `y` | int or null | `DL` |
| `killer_kind` | string or null | `p` player, `c` creature |
| `killer` | string or null | `DR` source: a creature name, a pseudonym for a player, or an environment name |
| `killer_level` | int or null | |
| `killer_class` | string or null | class file of a player, classification of a creature |
| `killer_race`, `killer_guild` | string or null | player only; the guild is a pseudonym outside `social` |
| `spell_id`, `damage`, `overkill`, `hits` | int or null | `DR` fields 1, 2, 6, 16 |
| `event` | string or null | the killing blow's combat event (`DR` field 17): `SWING_DAMAGE`, `RANGE_DAMAGE`, `SPELL_DAMAGE`, `SPELL_PERIODIC_DAMAGE` or `ENVIRONMENTAL_DAMAGE` |
| `environmental_type` | string or null | what the world did when the killing blow was environmental (`DR` field 18): `Falling`, `Drowning`, `Fatigue`, `Fire`, `Lava` or `Slime` |
| `killer_npc_id` | int or null | the creature id of the killing blow's source (`DR` field 23), when it was in sight |

### `death_hits` (combat: `D`, `DR`)

One row per hit of a death's recap, with the `at`, `level` and `zone_id` of its death, so a hit joins its death on
`character_id`, `session_start` and `at`. One row per `pos` of a death: a recap written twice adds nothing. Hits
come in `pos` order, 1 to 20 at most; a `DR` line with no pos (lines before the position existed) is not a hit row.
A `DR` line joins the last death when it follows within 60 s. Fields a line did not have are null, and `?` (the
client kept it secret) is null too. A source that names a player is a pseudonym by the rule of
docs/player-fields.tsv, on every pos.

| Key | Type | |
| --- | --- | --- |
| `pos` | int | `1` the killing blow, `2` the hit before it |
| `spell_id`, `amount`, `overkill`, `hp`, `max_hp`, `school` | int or null | `DR` fields 1, 2, 6, 7, 8, 10 |
| `source` | string or null | `DR` field 3: a creature name, a pseudonym for a player, or empty for the environment |
| `environmental` | bool | field 4 is `1` |
| `environmental_type`, `event` | string or null | fields 18 and 17 |
| `before_ms` | int or null | field 9, seconds before the killing blow, in milliseconds |
| `absorbed`, `resisted`, `blocked` | int or null | fields 19 to 21 |
| `flags` | string or null | field 22: `a` avoidable, `d` deadly, `h` caster hidden |

### The class tables

`spell_casts`, `meter_totals`, `meter_spells`, `state_times`, `item_flows` and `class_tallies` hold what the class
pages read, as aggregates of one session: a page sums them with `SUM ... GROUP BY`. Their rows have a key, and a
session writes one row per key, in key order, at the end. The row's `at` and `zone_id` are those of the first line that
touched the key, and `level` is the level when the line was read. A `state_times` span that crosses a level-up is
split at the `L` line. A **fight** is the time from `F 1` to `F 0`; an `F 1` while a fight is open and an `F 0` with
none open are ignored, and a fight still open at the session end closes there. These tables came with decoder version
14 (`spell_casts` and `meter_totals` with 13); the sessions already stored fill them when the scheduler decodes them
again.

The spell id sets that `class_tallies` matches (`form`, `stance`, `aspect`, `pal_aura`, `seal`, `judge`, `opener`,
`finisher`, `stealth`, `watch`) are ids, because the decoder cannot rely on a session's name lines.
`tools/class-spells/build.mjs` writes them to `part/src/sets.rs` from the client's `SpellName` table and the name
patterns of `tools/class-spells/sets.mjs`, every rank of a name. Edit the patterns and run the tool again; never edit
`sets.rs`.

**Values a line may carry.** The six tables read lines that an addon or a hand-made part can fill with anything, so a
value is held to what its column stores:

- A line's seconds read from 0 to the session's length (the time from the `S` line to the `ES` line, or to the last
  line without one): a negative figure reads as 0 and a longer one as the whole session. The line still counts. A
  span's time is held to the same length.
- An id (`spell_id`, `item_id`, `ref_id`, and the `a` and `b` of a tally) outside 0 to 2147483647 writes no row. A `C`,
  `CH`, `CI`, `DS`, `I`, `DI`, `A` or `SR` line with such an id is not read by any other machine either; a state with
  such an id (an `AR`, `MO`, `TW`, `G` or a numeric `MP` type) is not recorded, and a `TW` or `G` line that carries one
  ends the slot's span and starts none.
- A `name` or `extra` is cut to its first 255 characters.
- Sums stop at 9223372036854775807 and do not wrap. A negative total, amount or count (`DM` and `DP` totals, `DS`
  amounts, a `DI` count) makes the line write nothing; an `I` line's quantity is its delta without the sign.

### `spell_casts` (combat: `C`, `CH`, `CI`, `F`)

Key `spell_id`, `level`. One row per spell and level the session cast, at the first line of the spell at that level.
A `C` line is a cast that succeeded, a `CH` line a channel that ended and a `CI` line a cast that was interrupted. A
spell with only `CH` or `CI` lines at a level still gets a row, with `casts` 0. `cast_ms` is 0 when every cast of the
spell was instant.

| Key | Type | |
| --- | --- | --- |
| `spell_id` | int | |
| `casts` | int | the `C` lines of the spell |
| `cast_ms` | int | those lines' cast seconds, in milliseconds |
| `fight_casts` | int | the `C` lines read while a fight was open |
| `channel_ms` | int | the `CH` seconds of the spell, in milliseconds |
| `interrupts` | int | the `CI` lines of the spell |
| `streak` | int | the longest run of `C` lines of this spell with no `C` of another spell between them. `CH` and `CI` lines do not break a run, and a run goes on across a level-up: the row holds the longest run that reached a cast at its level (decoder version 14) |

A page that wants a spell's casts in the session sums `casts` over the spell's rows, and takes the largest `streak`.

### `meter_totals` (combat: `DM`, `DP`)

Key `meter`, `level`, `place`. One row per meter, level and place the session reported, at its first `DM` line: what
the client's damage meter wrote after each fight, summed over the fights. A `DM` line with an unknown meter letter or a
total that is not a number writes nothing.

| Key | Type | |
| --- | --- | --- |
| `meter` | string | `d` damage, `h` healing, `t` damage taken, `a` absorbs, `i` interrupts, `x` dispels |
| `place` | string | the instance type in force at the `DM` line: `w` open world (`none`, or no `Z` line yet), `d` dungeon (`party`, `scenario`), `r` raid, `b` battleground or arena (`pvp`, `arena`). The column is null on a row stored before decoder version 14 |
| `total` | int | the fights' totals summed |
| `best` | int | the largest single fight total |
| `fights` | int | the meter's `DM` lines |
| `fight_ms` | int | the fights' seconds, in milliseconds |
| `group_total` | int | over the fights of this key that had `DP` lines for the meter: the group's total with the uploader counted once. 0 without any |
| `group_own` | int | the uploader's own total in those fights, 0 for a fight with no `DM` line |
| `group_fights` | int | how many such fights |

The addon writes the uploader's own total as `DM` and the other members as `DP` lines, up to 10 for a fight and only
while in a group, and never the uploader as a `DP` (it skips the local player and enemy sources). The decoder reads one **report** for each meter: the `DM` line and the `DP` lines of that meter
that come within 1 s of the report's first line. A second `DM` line of the meter starts the next report. A report with
at least one `DP` line is a group fight: `group_fights` goes up by 1, `group_own` by the report's `DM` total (0 when it
has none) and `group_total` by that total plus the `DP` totals. The report counts for the level and place of its `DM`
line. A session without `DP` lines has all three at 0 (decoder version 14).

### `meter_spells` (combat: `DS`)

Key `meter`, `spell_id`, `level`. The spells that the client's damage meter listed among the top 5 of a fight, summed:
one row per meter, spell and level, at the first line.

| Key | Type | |
| --- | --- | --- |
| `meter` | string | as `meter_totals` |
| `spell_id` | int | |
| `amount` | int | the lines' amounts summed |
| `fights` | int | the `DS` lines |

### `state_times` (combat, movement, build: see the kinds)

Key `kind`, `ref_id`, `name`, `level`. The time the character spent in a state, and how much of it lay inside fights.
A row is written when it has a start or time; a split at a level-up that left a row with neither writes none.

| Key | Type | |
| --- | --- | --- |
| `kind` | string | the state, below |
| `ref_id` | int | a spell, an item, a happiness, 0 when the state has none |
| `name` | string or null | |
| `extra` | string or null | |
| `starts` | int | how many times the state began |
| `ms` | int | the time in the state, in milliseconds |
| `fight_ms` | int or null | the part of `ms` inside fights. Null for the kinds of `movement` and `build` in a session with no `combat` part, which cannot tell fights |

| Kind | From | `ref_id` | `name`, `extra` | Rules |
| --- | --- | --- | --- | --- |
| `a` | `A`, helpful, by `1` | spell | | An aura you cast. `ms` is the `A 0` seconds (the whole time, not the time since the last level-up). An aura on at the session end closes there. An `A 1` with initial `s` starts at the session start and is not a start. An `A 0` with no gain in the session adds its seconds, to the kind an earlier `A 1` of the spell showed, and writes nothing when there was none |
| `b` | `A`, helpful, by `0` or empty | spell | | A buff from others. As `a` |
| `x` | `A`, not helpful | spell | | A debuff on you. As `a` |
| `r` | `AR` | spell, 0 when empty | | Auto Shot and wand time, from the `AR 1` line to the `AR 0` line (measured from the lines' times, not the seconds the end line gives). An `AR 1` for another spell ends the one running. One running at the session end closes there |
| `m` | `MO` | the mount spell, 0 when empty | | A mount from `MO 1` to `MO 0`, by the lines' times. One with baseline `s` starts at the session start and is not a start. One on at the session end closes there |
| `p` | `PT`, `PD` | 0 | `name` the pet, `extra` its family | From the `PT` line to the next `PT` or the session end. An empty name ends it. A `PD` with baseline `s` for a pet that `PT` has not named starts the span with no family, and a later `PT` fills it |
| `h` | `PD` | happiness, 1 to 3 | `name` the pet | The time the pet that is out spent at that happiness. A `PD` line with another happiness ends the span, and one with none starts nothing |
| `t` | `TW` | the totem spell | `extra` the slot, `1` to `4` | `starts` are drops. A drop runs to the slot's next `TW` line, capped at its duration and at the session end. A drop with no duration runs to the next line |
| `w` | `G`, slot 16 | the item | | The main hand, from the `G` line to the slot's next `G` or the session end. The addon writes no `G` line at the session start, so the time before the first one is unknown and not counted. An item 0 ends the span and starts none; the same item again changes nothing |
| `o` | `G`, slot 17 | the item | | The off hand. As `w` |
| `k` | `MP` | the spell id when the type is a number, else 0 | `name` the type as written | One row per type that is on, from the `MP` line that shows it to the first one that does not, or the session end. A type in the login line (why `s`) is not a start |
| `f` | `F` | 0 | | `starts` fights, `ms` and `fight_ms` their time |

An aura that the client lists again (an `A 1` of a spell that is on) after a loading screen or a refresh is not a new
start and does not move the gain. An `A 0` within 2 s after such a line ends the instance that the line replaced, not
the aura: its seconds are added to the seconds of the `A 0` that does end it.

### `item_flows` (loot: `I`, `DI`; combat: `C`, `CH`, `AR`)

Key `item_id`, `dir`, `place`, `spell_id`, `level`. What went into and out of the bags, summed.

| Key | Type | |
| --- | --- | --- |
| `item_id` | int | |
| `dir` | int | `1` into the bags, `-1` out |
| `place` | string | the `I` line's `where` (`v` vendor, `b` bank, `m` mail, `t` trade, `l` loot, `a` auction house, `c` profession window, `g` guild bank), `o` when it is empty or anything else, and `x` for an item destroyed (`DI`) |
| `spell_id` | int or null | for `place` `o` only: the spell of the last `C` or `CH` line in the 1.5 s before the `I` line, else the spell of the `AR` that is running, else null. A soul shard reads as Drain Soul or a summon, conjured water as the conjure or a drink, ammo as Auto Shot. Null without a `combat` part, and a removed `combat` nulls it |
| `events` | int | the lines |
| `quantity` | int | the units, always positive |

An `I` line with no item or a delta of 0 writes nothing. A `DI` line counts its units as `x`, and the `I` line for the
same item and count (empty `where`, a negative delta) within 5 s after it is not counted again. The items of a trade
arrive as `I` lines with `where` `t`, so `TR` adds nothing here.

### `class_tallies` (combat: see the keys)

Key `key`, `a`, `b`, `level`. Counts of sequences, one small machine for each key. A row is filed under the `at`,
`level` and `zone_id` of the line it settled on, or, for a fight, of the `F 1` line, for a seal of its gain line and for
a totem drop of its drop line. A tally that waits for an outcome keeps a deadline in line time, so a row never depends
on which line settled it.

| Key | Type | |
| --- | --- | --- |
| `key` | string | the tally, below |
| `a`, `b` | int | what the tally keys on; 0 when it has none |
| `n` | int | how many of what the tally counts |
| `sum` | int | milliseconds some keys add up, else 0 |
| `best` | int | the largest `sum` of one |

| `key` | `a` | `b` | Counts (`n`) |
| --- | --- | --- | --- |
| `kill_blow` | the spell of the last `C` in the 3 s before a `K` with `by` 1; 0 when none | 0 | kills |
| `fight_kills` | the `K` lines with `by` 1 in one fight, at most 20 | 0 | fights |
| `fight_first` | the first `C` from 1.5 s before `F 1` to 3 s after it; 0 when none | 0 | fights |
| `death_after` | a spell cast in the 10 s before a `D`, each spell once at its latest cast | the whole seconds from that cast to the death | deaths |
| `death_after_aura` | a helpful aura that ended in the 10 s before a `D`, each spell once at its latest end | the whole seconds from its end to the death | deaths |
| `cast_under` | an aura of the `watch` set that was on at a `C`, or ended up to 0.3 s before it | the spell cast | casts |
| `shift_form`, `shift_stance`, `shift_aspect`, `shift_pal_aura`, `shift_seal` | the aura of the set that the character left: the one still on, or one that ended in the 2 s before; 0 when none | the aura of the set gained | gains. Leaving a form and entering the same one inside 2 s gives `a` equal to `b` |
| `open_finish` | the first `C` of the `opener` set in a fight, counted from 1.5 s before `F 1`; 0 when none | the first `C` of the `finisher` set after it in that fight, or the first in the fight when there was no opener; 0 when none | fights |
| `stealth_open` | the first `C` of the `opener` set while an aura of the `stealth` set is on, or up to 1 s after it ended. An instance of the aura is used once | 0 | openers; `sum` the time from the aura's gain to that cast |
| `seal_fate` | a `seal` aura that was gained | its fate, the first that happens, below | seals; `sum` the time from its gain to the fate |
| `totem_fate` | the slot, 1 to 4 | the fate of one drop, below | drops |
| `fight_totems` | how many of the four slots held a totem at any time in a fight, 0 to 4 | 0 | fights |
| `res_offer` | the `SR` option type | the `SR` id | deaths that offered it |
| `res_taken` | the `SR` option type | the `SR` id | of those, the deaths followed by a `U` with no `R` and no `SH` between |

Fates of a seal: `1` judged (a `C` of the `judge` set in the 0.5 s before it ended), `2` replaced (another seal gained
within 0.5 s of its end), `3` ran out (it ended within 1 s of its duration), `5` the fight it was gained in ended with
it on, `4` anything else. A seal gained outside a fight and still on at the session end has no fate and writes
nothing. Fates of a totem drop: `1` stood its full time (the slot's next line came at least the duration, less 1 s,
after the drop), `2` replaced by another totem in the slot, `3` gone before its time, `5` the fight it was dropped in
ended with it standing. A drop outside a fight with no later line has no fate. A totem counts as held until its slot's
next `TW` line, so one that ran out in the fight counts. A `D` within 3 s of the one before is the same death, as
for `deaths`. A `SR` pair repeated for one death counts once. An `A 1` with initial `s` is not a gain for `shift_*`,
`seal_fate`, and for `stealth_open` its gain time is the session start.

Rows with `n` 0 are not written.

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

### `loot_opens` (loot: `LO`, `LM`; combat: `C`, `CH`; movement: `P`; social: `GP`, `GM`, `GL`)

| Key | Type | |
| --- | --- | --- |
| `source_kind` | string | `c` creature, `g` object, `i` item from the bags, `o` other |
| `source_id` | int or null | npcID, objectID or itemID |
| `first_read` | bool or null | `again` 0 true, 1 false, null for an item |
| `slots` | int or null | |
| `has_items` | bool | the line lists items (lines from before 2026-11-20 15:00 do not) |
| `loot_rule`, `threshold`, `group_size` | string, int or null, int or null | the rule in force: `LM` method (`unknown` until a line says), threshold, and the group size from `GP`/`LM` |
| `read` | string or null | what the window was read for. `herb`, `ore`, `fish` or `skin` when a cast of that kind ended within 2 s before the window and the window is of the kind's source (`g`, or `c` for skinning): the rule `gathers.looted` uses, taken from the same match, so a `skin` window is one a skinning `gathers` row counts as looted. Otherwise `loot` for a creature or object, `bag` for kind `i`, `other` for kind `o`. Casts are `combat` lines: null without a `combat` part, and a removed `combat` nulls it (decoder version 10) |
| `item_rows` | int or null | the item entries of the window, a stack listed twice counted once, so the `window` rows of `loot_drops` that the window writes number the same; 0 is coin or currency only. Null when the line lists nothing (`has_items` false) (decoder version 10) |
| `map`, `x`, `y` | int or null | the last `P` at or before the window, as `gathers` reads it. Null without a `movement` part or before a first `P`. The ingest adds `u` and `v` from `map` and `x`, `y` the way it does for `path_points` (decoder version 10) |
| `group_at` | int or null | as on `kills`: the `at` of the `group_spans` row in force, null while solo and without a `social` part (decoder version 11) |
| `corpse_key` | string or null | the last field of `LO`, written for kinds `c` and `g`: the key of the corpse or object (see "Corpse key" below). Null for a line from before 2026-11-22, for kinds `i` and `o` and for any other text (decoder version 12) |

### `loot_drops` (loot: `LO`, `O`; combat: `C`, `CH`)

| Key | Type | |
| --- | --- | --- |
| `via` | string | `window`: an item in a loot window (`LO` items); `receipt`: an `O` line |
| `source_kind`, `source_id`, `first_read` | | as `loot_opens`; for a receipt, the last `LO` within 5 s, else null |
| `item_id` | int | |
| `quantity` | int | |
| `loot_rule`, `threshold`, `group_size` | | as `loot_opens` |
| `read` | string or null | as `loot_opens`; for a receipt, that of the source window, null without one (decoder version 10) |
| `corpse_key` | string or null | as `loot_opens`; a window's items carry their window's key, a receipt that of the source window, null without one (decoder version 12) |

Drop rates count `window` rows only (coin and currency entries of a window are not rows). A window writes one row per
item: stacks of one item listed in one window are added into one row, in the order the items first appear (decoder
version 10; a window that listed an item twice wrote two rows before). `receipt` rows give the attribution for logs that
have no item list.

### `peer_opens`, `peer_drops` (loot: `LW`; social: `GP`, `GM`, `GL`)

The loot windows that group members who run Scarlet read and sent to the uploader's group. One `peer_opens` row per `LW`
line of kind `c` or `g`, and one `peer_drops` row per item of its list, stacks of one item added, as `loot_opens` and
`loot_drops` write a window. They are their own tables, so no query of `loot_opens` or `loot_drops` counts them. When the
member also uploads, the window is also that member's own `LO`; the pseudonyms of two installs differ, so only
`corpse_key` tells the two lines are one corpse. `at` is the time the `LW` line was written: the member read the window
`late_s` seconds before it. `level` and `zone_id` are the uploader's at `at`, and no position is kept, since the
uploader was not where the corpse was.

| Key | Table | Type | |
| --- | --- | --- | --- |
| `source_kind`, `source_id` | both | string, int or null | `c` creature (npcID) or `g` object (objectID), read from the GUID in the message. A line of another kind is not a row |
| `first_read` | both | bool or null | `again` 0 true, 1 false: the sender had read the corpse, or the uploader or another member had in the last 5 minutes. One corpse has one first read across `LO` and `LW` |
| `slots` | `peer_opens` | int or null | null when outside 0 to 32767 |
| `has_items` | `peer_opens` | bool | the line lists items |
| `item_rows` | `peer_opens` | int or null | the item entries kept, as on `loot_opens`. Null when the line lists nothing |
| `item_id`, `quantity` | `peer_drops` | int | an entry with an item id or quantity past 2147483647 is not a row |
| `loot_rule`, `threshold`, `group_size` | both | | the rule in force when the line was written, as on `loot_opens` |
| `read` | both | string or null | `fish` for the fishing bobber (object 35591), else null: the window does not say what the sender read it for, and the uploader's casts are not theirs. A query for `loot` or `skin` reads no peer row. The keyed loot tables give a peer window a read when they count it: `loot` for a creature, and for an object the read its own windows have most (docs/LOOT.md, Units of counting, rule 7) |
| `group_at` | `peer_opens` | int or null | as on `kills`; null while solo and without a `social` part, and a removed `social` nulls it |
| `who` | both | string | the sender as uploaded: a pseudonym. A line whose sender is longer than 64 characters is not a row |
| `late_s` | both | int or null | seconds between their read and the send. Null outside 0 to 86400 |
| `corpse_key` | both | string or null | the last field of `LW`, as on `loot_opens` (decoder version 12) |

### Corpse key

The last field of `K`, `LO` (kinds `c` and `g`) and `LW`: the same 10 hex digits on every client for one creature or
object, so the member who killed it, each member who opened it and each `LW` they sent carry one key. The decoder keeps
a key only when it is exactly 10 lowercase hex digits; anything else is null. The key is 40 bits, so a corpse is
`(source_kind, source_id, corpse_key)`: `kills` has no `source_kind`, and its corpses are creatures. An object that
respawns can keep its GUID and so its key, so for an object the same key more than 5 minutes apart is a second read of
a respawned object, not the same read; a creature gets a new GUID. From decoder version 12 a looted corpse counts once
by `count(distinct (source_kind, source_id, corpse_key))` over the windows of `loot_opens` and `peer_opens` where the
key is present; a window without a key falls back to the first-read rule (docs/LOOT.md, Units of counting). `kills` is
not a window: it joins the windows on `npc_id` and `corpse_key`, and a keyed kill with no window is a corpse nobody
looted or that held nothing.

### `group_spans` (social: `GP`, `GM`, `GL m`, `GL s`; professions: `SL`)

One row per stretch of a session in which the uploader was in a group and nothing below changed. A change closes the
row and opens the next; lines of one millisecond make one change. `at` is the start of the stretch, the session's
start when the group was there at login, and `end` its end (the column is `ended_at`). No name is stored. `kills.group_at`
and `loot_opens.group_at` hold the `at` of the row in force, so a kill joins its group on character, session and that
time. A session that ends in a group closes its last row at the session's end.

| Key | Type | |
| --- | --- | --- |
| `end` | int | ms |
| `size` | int or null | head count: the uploader and the members `GM` and `GL m` name; the `GP` size until one is named (`GP` is written when the character joins or leaves a group, not when its size changes) |
| `raid` | bool or null | the last `GL s`; null before one |
| `members_known` | int | members with a `GL m` line, the uploader included |
| `classes` | string or null | the class files of those members, one each, sorted, comma-joined: `PRIEST,ROGUE,WARRIOR` |
| `tanks`, `healers`, `damage`, `no_role` | int | those members by `GL m` role (`t`, `h`, `d`; anything else is no role) |
| `level_min`, `level_max` | int or null | their levels |
| `guild_mates`, `friends` | int | their `GL m` flags |
| `skinners` | int or null | uploader only: 1 when the uploader has a rank in Skinning (`SL`), else 0; other members' professions are not recorded. Null without a `professions` part. The skill scan at the session's start (`SL` baseline `s`) does not split a row |

### `group_loot` (loot: `LT`, `LW`; social: `GL m`, `pn`)

What another member of the uploader's group received (the uploader's own receipts are `loot_drops`). One row per `LT`
line.

| Key | Type | |
| --- | --- | --- |
| `kind` | string | `l` looted (by the rule, a roll win or a master looter's pick), `p` the client pushed the item to them |
| `who` | string | the receiver as uploaded: a pseudonym |
| `class_file` | string or null | the class file of the member `who` names, found through the session's `pn` lines and the `GL m` line of that name. Null without a `social` part; a removed `social` nulls it |
| `item_id`, `quantity` | int | |
| `loot_rule`, `threshold`, `group_size` | | as `loot_opens` |
| `source_kind`, `source_id` | string or null, int or null | the source of the `peer_opens` window of the same `who` that lists the item and was read nearest the receipt, within 60 s of it (the read is `late_s` before the `LW` line). A window is not used up by a receipt, so two receipts of one item can name one source. Null when no such window was sent, so a member who does not run Scarlet leaves it null (decoder version 12) |

### `loot_rolls` (loot: `LD`, `RW`, `RL`; social: `GL m`, `pn`)

One row per player per roll. A roll is one item the group rolled for; `roll_no` counts the rolls of the session from 1
and every player's row of one roll shares it. A player's lines for one item within 60 s are one row, and a roll that
came from the chat and from the roll history (`LD` src `c` and `h`) is one row. A line more than 2 s after a roll's
outcome starts a new roll, so the history's repeat of a win belongs to the roll the chat gave and a second copy of the
item is a roll of its own.

| Key | Type | |
| --- | --- | --- |
| `roll_no` | int | |
| `item_id` | int | |
| `who` | string | the character's own name for the uploader's row (`mine`), else a pseudonym |
| `mine` | bool | the uploader's own row (`RL`, `RW`) |
| `class_file` | string or null | as on `group_loot` |
| `roll_type` | string or null | `pass`, `need`, `greed`, `disenchant` or `transmog`; null when no line said |
| `roll` | int or null | the number rolled |
| `outcome` | string or null | `all_passed` when everyone passed (`LD a`); else `won`, `lost` (rolled, and someone else won) or `passed`. Null when the session has no winner for the roll |
| `decide_ms` | int or null | the uploader's own rows: the `RW o` extra, the time the client gave to decide |
| `loot_rule`, `threshold`, `group_size` | | as `loot_opens`, when the row's first line was read |

### `boss_loot` (loot: `EL`)

Someone in the uploader's group received an item from a boss encounter. Every member of the group who uploads writes
a row for the same drop, so a page counts a drop once (DESIGN.md).

| Key | Type | |
| --- | --- | --- |
| `encounter_id` | int or null | the client's encounter id |
| `item_id` | int or null | |
| `quantity` | int or null | |
| `who` | string or null | the receiver: the uploader's own `Name-Surname` for their loot, a `~` pseudonym for another player unless the social group is on |
| `class_file` | string or null | the receiver's class file name |

### `skill_ups` (professions: `SK`, `SL`)

| Key | Type | |
| --- | --- | --- |
| `kind` | string | `SK` (a skill went up; named) or `SL` (a skill line, by id) |
| `skill` | string or null | `SK` name |
| `skill_id` | int or null | `SL` skillID |
| `rank` | int or null | |
| `max` | int or null | `SL` |
| `baseline` | bool or null | `SL` baseline `s`; null for `SK` |

### `crafts` (professions: `CR`; with `CB`, `RD`, `TS`, `SL`, `CS`)

One row per crafted item. `item_id`, `quantity`, `crit`, `multicraft`, `recipe_id` (recipeSpellID), `quality`: int or
null each. A lure is not a craft: an item's spell cast is followed at once by a `CR` for the fishing pole, with `enchant`
`1`, an empty recipe and no `CB` before it. A `CR` with `enchant` `1`, no `CB` since the last `CR` or `CS`, and an item
that is the main hand item (the last `G` of slot 16) is such an application and writes no row. So is one with an empty
recipe in a session that has no `G` of slot 16 yet: the addon logs `G` only when the slot changes, so the main hand is
not known, and the item of that `CR` is taken as the main hand from then on (the pole the lure is on). A weapon
enchant such as a spellstone reads the same way and writes no row either. Not every real craft has a `CB` before it
(2 of 54 in the first recording), so a craft with `enchant` `1` is decided by the main hand and the recipe, not by `CB` alone.

| Key | Type | |
| --- | --- | --- |
| `profession_id` | int or null | the professionID of the last `TS 1` of the session, or its parentProfessionID when it has one (`0` is none). Null when no window opened before the craft |
| `difficulty` | int or null | the recipe's last `RD` difficulty: `0` optimal, `1` medium, `2` easy, `3` trivial. Null when no `RD` named the recipe |
| `skill` | int or null | the rank in the profession's skill line before the craft, from the last `SL` of the line (a child line counts as its parent). Null when no `SL` of it came before |
| `skill_up` | bool or null | an `SL` of the line raised the rank within 2 s after the craft. A rise belongs to the last craft before it |
| `enchant` | bool or null | the `CR` enchant field. Null on older lines |
| `sitting_gap` | number or null | seconds since the previous craft of the same profession, when that is 120 s or less. Null starts a new sitting |

`camp_stays.crafts` counts these rows only.

### `gathers` (professions: `SL`; combat: `C`, `CH`, `F`; loot: `LO`; build: `G`; movement: `P`)

One row per gathering attempt: fishing, herbalism, mining and skinning. `at` is when the cast began: the `C` line for
fishing, and for the others the `C` line minus its cast seconds, since the client logs those casts when they succeed. A
cast that fails or is cancelled is not logged, so herb, ore and skin rows are successes. Fishing logs the cast at its
start and a `CH` when the channel ends; a `CH` with its cast is one row. A fishing channel under 2 s with no loot window
is a cast the player cancelled and writes no row; without a `loot` part it cannot be told from a bite, so it is kept.
A loot window belongs to the last attempt that ended before it, within 2 s, and to no other. The spell ids are one table in
`part/src/professions.rs` (`App\Support\GatherKind` on the PHP side). Skinning is a cast, then an `LO c` of the creature
(FORMAT.md). Recordings on the server confirm it (2026-11-22): skinning windows of one creature carry skin rows in
`gather_items`. `loot_opens.read` is `skin` for the same windows by the decoder's rule.

| Key | Type | |
| --- | --- | --- |
| `kind` | string | `fish`, `herb`, `ore` or `skin` |
| `spell_id` | int | the gathering spell cast |
| `seconds` | number or null | fishing: the `CH` seconds, the wait for the bite. Others: the `C` cast seconds |
| `looted` | bool or null | a loot window of the right source (`g` for fishing, herbs and veins; `c` for skinning) within 2 s after the end of the cast (the `CH`, or the `C` of the others). Null without a `loot` part |
| `source_id` | int or null | the `LO` id: the bobber (35591) or a school's object, the herb or vein object, the skinned creature. Null without a window |
| `again` | bool or null | the `LO` again flag: the same vein or creature was read in the last 5 minutes, which is how a second strike on one vein reads. Null on older lines |
| `skill` | int or null | the rank in the kind's skill line (Fishing 356, Herbalism 182, Mining 186, Skinning 393; a child line counts as its parent) before the attempt, from the last `SL` of the line. Null when the session has not said |
| `skill_up` | bool | the rank rose within 2 s after the loot window (after the end of the cast without one). Read from the `SL` rank, not from `SK`, which is named by locale |
| `gap` | number or null | seconds from the end of the previous attempt of the same kind (its loot window when it had one) to `at`, when that is 120 s or less and not negative. Null starts a new trip |
| `lure_spell` | int or null | fishing: the spell cast for the last lure applied to the pole in the main hand, in this session |
| `lure_age` | number or null | fishing: seconds from that application to `at`. A page decides how long a lure lasts: `GatherKind::LURE_SECONDS` is 600, the classic length, and no recording has confirmed it |
| `pole_item` | int or null | fishing: the main hand item (`G` slot 16) at the cast, or the item a lure was applied to (see `crafts`) when no `G` of the slot came before. Null when the session has said neither; a removed `build` group nulls it |
| `in_combat` | bool or null | an `F 1` is open at the cast |
| `map`, `x`, `y` | int or null | the last `P` at or before the cast, as `path_points` reads it. Null without a `movement` part or before a first `P` |

A lure is keyed by the item it was applied to, so a weapon enchant on another item is never a fishing row's lure. The
ingest adds `u` and `v` from `map` and `x`, `y` the way it does for `path_points`.

### `gather_items` (professions: `SL`; combat: `C`, `CH`; loot: `LO`, `O`; movement: `P`)

One row per item a gather gave. `at` is the loot window's. The items come from the `LO` item list; coin (`m`) and
currency (`c`) entries are not rows. Lines before 2026-11-20 have no list: the `O` lines within 2 s after the window are
the items. One row also for each item that came out of a caught container opened later (`LO i <itemID>`, where that item
was caught in this session): `kind` is `fish`, `source_id` is null and `container_item_id` is the container. A window of
`LO i` that names no item (most of them) cannot be tied to a catch and writes no rows.

| Key | Type | |
| --- | --- | --- |
| `kind` | string | `fish`, `herb`, `ore` or `skin` |
| `source_id` | int or null | the `LO` id of the window, as on `gathers`. Null on a container's items |
| `item_id` | int | |
| `quantity` | int | |
| `skill` | int or null | the rank in the kind's skill line when the attempt began; for a container's items, when it was opened |
| `map` | int or null | the last `P` map at the attempt, or at the opening. Null without a `movement` part |
| `container_item_id` | int or null | the caught container the item came from |

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

### `auction_prices` (economy: `AQ`)

One row per `AQ` line: a price the auction house showed the player. `kind` (string or null) is `c` a commodity
search (lowest unit price, `quantity` everything on sale), `i` an item search (lowest buyout among the first 50
results) or `b` one browse row. `item_id`, `price` (copper), `quantity`, `listings`, `item_level` and `suffix` are
int or null each; `item_level` and `suffix` are the item key's and null for a commodity. `realm_id` (int or null) is
the realm id of the session's `RU` line, null when the session has not read one by the time of the line. Sellers and
bidders are never in the line.

### `camp_stays` (combat: `A`, `C`, `D`; xp: `AFK`; professions: `CR`; movement: `P`)

One row per stay by a campfire: the span the character holds the aura Campfire Nearby (1283391), which the client
gives only while a fire is in range. `at` is the start of the stay, and `level` and `zone_id` are those at that time.
The spell ids and their kinds are one table in `part/src/camp.rs` (`App\Support\CampSpell` on the PHP side). An `A 0`
for an aura that is not open is ignored. After a loading screen the client lists every aura again: an `A 1` for each,
then an `A 0` with the held seconds of the instance it replaced. So an `A 1` for a camp aura that is already open carries
on the same stay or row, and the `A 0` that follows it within 2 s ends nothing. A stay still open at the session's end closes at the session's end. The `CP` camp-visit line is not read: it also counts the hour-long Camp Benefits buff, so
it overstates the time at a camp (Medalink/scarlet#2).

| Key | Type | |
| --- | --- | --- |
| `seconds` | int | length of the stay: the `seconds` of the `A 0` line, rounded; else the time to the session's end |
| `carried` | bool | the aura was on at the session start: its `A 1` has the initial `s`, or came in the first 10 s of the session, when the first scan lists what is already on. The row continues a stay of the session before |
| `ended` | string | `w` the aura went while the session ran, `s` the session ended near the fire |
| `afk_seconds` | int or null | time between `AFK 1` and `AFK 0` inside the stay. Null without an `xp` part |
| `sat_seconds` | int | time inside the stay that a sit timer aura (Welcoming Campfire, 1229739 or 1289723) was held |
| `benefits` | bool | an `A 1` of Camp Benefits (1229741) fell inside the stay. One for a buff still held counts only while a sit aura is on, which is a sit that finished; without it, it is the scan after a loading screen. A loading screen in the middle of a sit reads as a gain too. A buff refreshed this way stays one `camp_benefits` row, so stays with `benefits` can outnumber the rows gained One in the first 10 s of the session does not count: that is the first scan listing a buff already on |
| `tent_seconds` | int | time inside the stay that the tent rest aura (1229487) was held |
| `campfire_spell_id` | int or null | the campfire the character placed (1307227 Basic, 1307252 Journeyman, 1307237 Expert): the last `C` of one from 60 s before the stay to its end, and not before the end of the stay before it, so one cast counts for one stay. Null when the fire was someone else's or already there |
| `placed_tent`, `placed_chair` | bool | the same for the Camp Tent (1307230) and Camp Chair (1307229) casts |
| `crafts` | int or null | `crafts` rows inside the stay: a lure or weapon enchant is not one. Null without a `professions` part |
| `died_after` | bool | a `D` from 2 s before the stay's end (the `D` line can come before the `A 0`) to 300 s after it. False for a stay the session ended |
| `map`, `x`, `y` | int or null | the last `P` at or before the start of the stay, as `path_points` reads it. Null without a `movement` part or before a first `P` |

The ingest adds `u` and `v` from `map` and `x`, `y` the way it does for `path_points`.

### `camp_benefits` (combat: `A`, `D`; xp: `X`)

One row per Camp Benefits aura (1229741) or benefit buff held, whether or not a stay of the same session holds it: the
nine buffs of the objects placed at a camp are 1229519 Camp Chair, 1230124 First Aid Kit, 1230172 Sharpening Wheel,
1230164 Lodestone, 1230587 Mana Well, 1229513 Incense Candle, 1229718 Faction Banner, 1230098 Fish Bowl and 1230653
Enchanted Lute. `at` is when it was gained.

| Key | Type | |
| --- | --- | --- |
| `spell_id` | int | |
| `seconds` | int | held: the `seconds` of the `A 0` line, rounded; else the time from the `A 1` to the end of the aura or of the session. An `A 1` while the aura is on (a refresh, or the scan after a loading screen) carries on the same row, and the `A 0` that follows it within 2 s belongs to the instance it replaced and ends nothing |
| `carried` | bool | already on at the session start: the `A 1` has the initial `s`, or came in the first 10 s of the session. A row that is not carried is a buff gained in the session |
| `lost_to_death` | bool | a `D` line within 2 s of the aura ending, before or after. False when the session ended it |
| `duration` | int or null | seconds the buff was given for: the longest duration of its `A 1` lines. Camp Benefits is given for 3600 or 7200 |
| `xp` | int or null | experience gained while the aura was held: the sum of the `X` lines from the gain to the end. Only on a Camp Benefits row (1229741); null on the nine buff rows, and null without an `xp` part |
| `deaths` | int or null | `D` lines from the gain to the end, and up to 2 s after it when a death took the aura. Only on a Camp Benefits row; a row with `lost_to_death` has at least one |

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

### `recipes` (professions: `RQ`)

`session`, `recipe_spell_id`, `at` (ms of the line), `output_item_id`, `min_quantity`, `max_quantity`, `reagents`
(string or null: `itemID:count` joined by commas), `optional_slots`, `type` (int or null, the client's recipe type).
One row per recipe of the session, the last line read. A recipe is the same for every uploader: the app keeps the
newest row of each id.

### `spells` (names group: `SI`, `SD`)

`session`, `spell_id`, `at` (ms of the newest line), `icon_id`, `cast_ms`, `min_range`, `max_range` (ints or null),
`costs` (string or null: `powerType:cost` joined by commas), `passive` (bool or null), `description` (string or null,
300 characters at most). `SI` fills the first seven keys and `SD` the last; a session with only one of them has nulls
for the other, and the app keeps the known value. One row per spell of the session. `SI` and `SD` do not write a
`names` row.

### `names` (names group)

`session`, `kind` (`npc`, `spell`, `item`, `quest`, `currency`, `skill`, `statistic`, `achievement`, `faction`,
`gossip`, `stat`, `flight`, `talenttab`, `object`), `id` (int), `name` (string). One row per distinct (kind, id) of the
session. A name becomes the shown one for an id when most uploads agree on it (DESIGN.md). `object` names come from the
`ON` line and are voted like `npc` names: one vote for each session that names the object, none for a decode again.

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
`u`, `v` without `movement`. `camp_benefits.xp` is null without `xp`.
`gathers` and `gather_items` are written only with a `professions` part. `gathers.looted`, `source_id` and `again` are
null without `loot` and `in_combat` without `combat`, and `map`, `x`, `y`, `u`, `v` without `movement`
(`gather_items.map` too); a removed group nulls the same columns, and a removed `build` nulls `pole_item`.
Every `gather_items` row is read from loot lines, so a removed `loot` deletes those rows (`FactTable::alsoNeeds`).
`loot_opens.read` and `loot_drops.read` are null without a `combat` part, since the casts that name a gathering window
are `combat` lines; `loot_opens.map`, `x`, `y`, `u`, `v` are null without `movement`; `kills.loot_rule` and
`kills.group_size` are null without `loot`. A removed group nulls the same columns.
`peer_opens` and `peer_drops` are written with a `loot` part and deleted with it; `peer_opens.group_at` is null without
a `social` part and a removed `social` nulls it. `corpse_key` of `kills` is read from the combat part and of `loot_opens`
and `loot_drops` from the loot part, so a removed group deletes it with its rows and no group nulls it.
`group_spans` is written only with a `social` part, and `kills.group_at` and `loot_opens.group_at` are null without
one; a removed `social` deletes the spans and nulls `group_at`, and nulls `class_file` of `group_loot` and
`loot_rolls`, which are written with a `loot` part and deleted with it. `group_spans.skinners` is null without a
`professions` part and a removed `professions` nulls it.
`spell_casts`, `meter_totals`, `meter_spells` and `class_tallies` are written only with a `combat` part: without one the
session has no `C`, `DM`, `DS` or `K` lines, and a removed `combat` deletes the rows it holds. `state_times` reads the
lines of three groups, and each kind is written with its group: `a`, `b`, `x`, `r`, `t` and `f` with `combat`, `m` and
`k` with `movement`, `p`, `h`, `w` and `o` with `build`. A removed group deletes the rows of its own kinds
(`StateKind`, `FactTable::kindsOf`) and a removed `combat` nulls `fight_ms` on the rows that stay, as does a session with
no `combat` part. `item_flows` is read from `I` and `DI` lines, so it is written with a `loot` part and
deleted with it; its `spell_id` is null without a `combat` part and a removed `combat` nulls it, so a removed group
reads as one never sent.

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
