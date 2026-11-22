# Loot explorer

The reference for the loot explorer on scarletweb.io: deep metrics on loot, drop rates, where things drop, and how
loot rules, group size and group composition relate to them. [DESIGN.md](DESIGN.md) lists the pages,
[ROWS.md](ROWS.md) the rows, [API.md](API.md) the endpoints. This file holds what the explorer counts, how it
states a number, and the order it is built in. Change it first when the design changes.

The numbers quoted below were read from the production database on 2026-11-22 (5 characters, loot from 5 to 22
Nov). They show why a rule exists; they are not figures the site prints.

## What the data showed

Four findings shape the design more than any layout choice.

1. **Skinning windows are counted as corpse loot.** A skin read carries `again 1` only when the skinner's client
   already knew the corpse. A corpse that had no loot, or one the partner looted whose `LW` message had not
   arrived (it waits for the end of combat), is a first read when it is skinned. On Mature Paletusk (npc 255851)
   79 of one character's 104 first-read windows have a `gather_items` row of kind `skin` within 3 s. After the
   split there are 46 loot windows and 78 skin windows. So Heavy Leather reads as a 49.6% drop over 125 windows
   and Chunk of Boar Meat as 17.6%, where the corpse loot rate is about 47% (22 of 46). `gather_items.kind` is
   `skin`, `ore`, `fish` or `herb`.
2. **A plain rule comparison finds an effect that cannot exist.** Ivory Boar Tusk on Mature Paletusk: free for
   all 14 of 42 (33.3%), group loot 6 of 71 (8.5%). The 95% ranges do not overlap. The drop table is the same
   under both rules. Under group loot 53 of the 71 windows gave leather or hide, under free for all 19 of 42, so
   the two denominators differ in kind. A rule difference is a counting fault until shown otherwise.
3. **The group lines are not where one would expect.** `GP`, `GM` and `GL` are in `social`. `LT`, `LD`, `RW` and
   `RL` are in `loot` and reach the server today, undecoded. `LW` was in the `none` row of `groups.tsv` (the addon
   wrote it and nothing uploaded it) until it moved to `loot` with its sender as a pseudonym; the decoder does not
   read it yet. Objects were unnamed until the `ON` name line (group `names`), kind `object`.
4. **Five characters fill three drop tables.** Three creatures have 30 or more windows, 37 have 10 or more.
   Rule comparison rests on one source. No source has two group sizes with 30 windows each. Pages must read well
   with most modules saying what is missing.

Free-for-all corpses are read twice: 157 of 421 free-for-all first-read windows have another uploader's
first-read window on the same source within 300 s (37%). That is why the windows with no corpse key of a
free-for-all arm that two characters contributed to count one reader (Units of counting, rule 6).

## Views

Six views. Four extend pages that exist. Each new page gets a comp in `docs/comps/` first, and
[DESIGN.md](DESIGN.md) "Pages" gets its row before any code.

| View | Path | Status |
| --- | --- | --- |
| Loot index | `/loot` | new |
| Creature drops | `/creatures/{id}` | extends the Drops module |
| Object | `/objects/{id}` | new, on the creature template |
| Item sources | `/items/{id}` | extends Where it drops |
| Loot rules and group size | `/loot/groups` | new |
| Looted in a zone | `/zones/{id}` | one new module |

### Loot index, `/loot`

Answers: what has been recorded, how much of it, and where to go for an item or a zone.

- Head: `h1` Loot. Facts line: corpses looted and skinned, characters and the recorded date range.
- Figure strip, six: Corpses looted, Skinned, Sources, Items, Uncommon or better, Characters. Corpses looted and
  Skinned are creature windows read for loot and for skinning; Sources are the creatures, objects and containers
  that have a window; Items and Uncommon or better count distinct items (quality 2 or better) of loot, skinning,
  herb, ore and container windows. Fishing is in none of them. A zone filter narrows the strip.
- Filters as query parameters of one form, no script needed: `view` (Creatures, Objects, Containers, Items), a zone
  pick, a level range (`min`, `max`), a quality pick, a field that searches creatures, objects and items only, and
  `sort`. Level is the creature's level for a creature (`creature_facts_daily`) and the looter's level for an object
  or a container (`loot_opens.level`), since the aggregates carry no level; a source matches a range when its own
  range overlaps it. The quality pick keeps the sources that dropped an item of that quality or better, and the
  items of that quality or better in the Items view. The field matches a name, an id, or an item the source
  dropped.
- Module, full width, **Sources**: Source, Zone (where most of its windows were), Level, Corpses looted (Opened for
  an object or a container), Items seen, Most common item with its rate and range, Characters. Default order is
  corpses looted. `?sort=` takes `windows`, `items`, `level`. 25 rows a page, `?cursor=` as the leaderboards do.
- Module, full width, **Items** (`?view=items`): Item with its icon and quality colour, Dropped, Sources, Best
  source with rate and range, Zone it dropped most in.
- Module, half, **Zones**: a rank of zones by loot windows, each linking to `/loot?zone={id}`.
- Module, half, **Loot rule and group size**: windows by rule and size as rank bars, linking to `/loot/groups`.
- Fishing is one line in the Sources module that links to `/professions/fishing`. Its bobber windows stay out of
  every table here.

Entry points: a Loot item in the site nav after Map, the page in `App\Support\SitePages` so search finds "loot"
and "drop rates", and a link from every Drops and Where it drops module. Every source row links to
`/creatures/{id}` or `/objects/{id}`, every item to `/items/{id}`, every zone to `/zones/{id}`. A container
(source kind `i`) links to its item page, which gains a What it holds module.

### Creature drops, `/creatures/{id}`

Answers: what this creature drops, how sure the number is, and whether rule or group size moved it.

- The strip keeps its figures. Corpses looted counts loot windows only, not skins.
- **Drops** (w7): Item, Drop rate with its range band, Range, Corpses (`k of n`), Dropped. The bar is the rate and a
  band on it runs from the low to the high end of the range. The rule segment gains **All rules** as the default,
  because the table is one table, and keeps a view per rule. A rule with fewer than 10 windows is disabled. Rows
  rank by the low end of the range. `personal` windows are never pooled into All rules.
- **Skinning** (w5, beside Deaths to it): its own table over the creature's skin windows (`read = 'skin'`, first
  reads, whatever the loot rule): Item, Share of skins, Range, Skins (`k of n`). It ranks and draws by the same
  ladder as Drops. Left out when the creature has no skin windows. The API answer gains `skinning`.
- **Drop rate by loot rule** (w12, phase 4): one row per item with 10 or more drops, one mark per rule on a shared
  0 to 100% axis: a dot for the rate and a line for the range, with `k of n` beside it. Arms of 10 to 29 windows
  are drawn hollow, arms under 10 are not drawn. A row whose ranges do not overlap gets a mark and the tags that
  explain it. Empty state: `Only group loot recorded.`
- **Drop rate by group size** (w12, phase 4): the same chart, split by size inside one rule. Empty state names the
  sizes seen: `Group loot recorded at size 2 only.`
- **Corpses looted of kills** (phase 2, grouped play in phase 7): a figure in the strip labelled `Corpses looted of
  kills`: `76.9%` with `40 of 52` beside it, a dash in place of the share under 10 kills, and no figure when the
  creature has no such kills. It counts a kill with a corpse key at any group size, once however many members got
  credit, and a solo kill with no key that the character made itself (credit 1 or 2). Drops gains an **Of kills** column (`24 of 52`), the windows that
  listed the item over the same kills. Both follow the rule control: under one loot rule only the kills with a key
  of that rule are counted, and the kills with no key (solo play) only under All rules and Solo. A kill nobody
  looted counts against it, so the page says `of kills`, never `drop rate`.
- Drops has no explanation of a drop rate: the columns and the `k of n` say it. Under the table one line carries the
  tags, `Of kills counts a kill nobody looted against the item.` when the column is shown, and, when windows were
  left out because their session has no `read`, `Left out: N corpses from sessions that do not record what a corpse
  was read for.` Drops counts windows read for loot only (`read = 'loot'`); windows read for skinning are in
  Skinning, which has no note. No module of the loot pages carries more than one short line of explanation.

### Object, `/objects/{id}`

Answers: what a chest, herb, vein or other object gives.

The creature template without kills and deaths. `h1` is the object's name, or `Object 649094` until the addon
writes object names. Facts line: where it was opened, at which looter levels, what it gives. Figures: opened, items,
characters, zones. Modules: What it gives (the Drops table over the read the object has most windows of, with the
loot rule control for a chest), Drop rate by loot rule (phase 4), Where it was opened (the positions of the newest
600 windows). The bobber of a fishing line, object 35591, is not an object page: it goes to `/professions/fishing`.
Search finds an object by id and, through its items, by what it gives.

### Item sources, `/items/{id}`

Answers: where this item comes from, which source is best, and who ends up with it in a group.

- Strip: Dropped, Sources, Best drop rate (the top-ranked source's rate, shown only when that source has 30
  windows, else a dash), Median looter level, First recorded. Won by need joins in phase 5.
- **Where it drops** (w8): one row per source and how it was read, not per source and rule: Source, Zone, How
  (Loot, Skinning, Herbalism, Mining, Fishing, Container, from `read`), Drop rate, Range, Corpses (`k of n`), Per
  drop. A creature that drops an item both looted and skinned has two rows, each over the windows read that way.
  Sources with 10 or more windows rank by the low end of the range, so a 2 of 2 cannot lead. Below a hairline,
  sources under 10 windows show `k of n` and no rate. `?rule=` filters the table. Dropped and Sources count windows
  with a known `read`, and a source once however it was read; a container links to its item page. Zone joins in
  phase 3.
- **By zone** (w4): Zone, Dropped, Per 100 corpses looted in the zone.
- **Where it was looted** (phase 2): map cells of the windows that held it.
- **Rolls in groups** (phase 5, already in the comp) and **Who received it** by class (phase 6).
- The facts line gains the item's count of receipts with no loot window (quest rewards, pushes, crafts).

### Loot rules and group size, `/loot/groups`

Answers the owner's question directly, as three separate questions, because they have different answers.

- **What a corpse held, by loot rule**: Rule, Items per corpse, Corpses, Sources. Only sources seen under both
  rules count, weighted per source. The naive figure over all sources is not shown anywhere.
- **What the looter kept**: receipts per window item by rule and size.
- **Your share of the group's loot** (phase 5): by rule and size against 1 over the size.
- **Rolls** (phase 5): need, greed and pass by item quality and rule; items at or above the threshold per 100
  corpses.
- **Kills and corpses an hour by group size** (phase 2).
- **Group composition** (phase 6): need share by class and armour type, skins per corpse with a skinner in the
  group, kills an hour with and without a tank and a healer.
- `?source={kind}{id}` narrows every module to one source and is where a creature page's rule chart links.

### Looted in a zone, `/zones/{id}`

One module, **Looted here**: the five sources with the most corpses looted and the five items dropped most, with a
link to `/loot?zone={id}`.

## Units of counting

Three units exist in the data and they are not interchangeable.

| Unit | Row | What it is | Double counts when |
| --- | --- | --- | --- |
| Window | `loot_opens`, `first_read` true | one character's first read of one corpse or object | two uploaders read one corpse and neither knew of the other's read |
| Member kill | `kills` | one character's record that a creature died to its group | summed across the members of a group: a duo writes two rows for one corpse |
| Receipt | `loot_drops`, `via = 'receipt'` | an item the uploader received | never, it is the uploader's own |

A **corpse** has no GUID on the server: the addon uses it and never uploads it. Since decoder version 12 it has a
corpse key, a hash of the GUID (rule 7).

Rules the design follows:

1. A drop table counts windows, and calls them corpses, because a window is the only place the whole table of a
   corpse is seen.
2. One character never double counts a corpse. The addon remembers each GUID for 5 minutes and writes `again 1`
   on a second read; those rows are excluded (`first_read is not false`, in both loot aggregates).
3. Across characters under rules with one looter per corpse (solo, group loot, round robin, need before greed,
   master looter), a corpse has one first read.
4. Under free for all every member may open the corpse. Two uploaders in one group both run Scarlet, so each
   receives the other's read as an `LW` message and writes `again 1` on its own later read. What is left is a
   race: the message waits for the end of combat (up to 5 minutes) and can be dropped, and either player can turn
   sharing off.
5. The server cannot rebuild corpse identity from time and contents. A duo kills the same creature type every few
   seconds, so "same source within 5 minutes" merges different corpses, and it drops small windows first, which
   pushes every rate up. The design does not do it.
6. So: sum first reads across characters, except for the windows with no corpse key (rule 7) of a free-for-all arm
   that two or more characters contributed to. Those use **one reader**: rate and range come from the character with
   the most of them in the arm, as `BossDrops` takes the uploader who saw most, and the arm carries the tag `one
   reader`. It loses up to half the sample and cannot double count. 37% of free-for-all windows have another
   uploader's window on the same source inside 300 s, past the 5% that triggers it. The windows with a key count
   whole in the same arm, each corpse once, and an arm whose windows all have a key has neither one reader nor the
   tag.
7. **The corpse key (phase 7, decoder version 12).** `K`, `LO` and `LW` carry a key that is the same on every client
   for one corpse, stored as `corpse_key` on `kills`, `loot_opens`, `loot_drops`, `peer_opens` and `peer_drops`. A
   window with a key counts once per corpse, however many characters read it or were sent it. A window without one (a
   line from before 2026-11-22, or a session not yet decoded again) keeps the first-read rule (rules 3 to 6). The
   unit is `(source_kind, source_id, corpse_key, read)`: the key is 40 bits and FORMAT.md says it is never read
   alone; a loot window and a skin window of one corpse are two rows of two tables; an object that respawns can keep
   its key, so for an object the same key more than 5 minutes after the last read is a second opening. Of the first
   reads of one corpse across `loot_opens` and `peer_opens`, one window is kept: a character's own before one a
   member sent, then the earliest read (`peer_opens.at` less `late_s`), and the items are that window's. A window a
   member sent says nothing of what it was read for, and a member's skinning is shared like its looting: a creature's
   counts as `skin` when every item in it is an item that a `gather_items` row of kind `skin` has (a window with no
   item is `loot`), else as `loot`; an object's takes the read its own windows have most (`loot`, `herb` or `ore`),
   and an object nobody opened for itself counts nowhere. The set of skin items is read when the tables are rebuilt,
   so a corpse a member skinned counts as loot until some character has skinned that item. An unkeyed window a member
   sent counts nowhere, since it cannot be told from the uploader's own. A window a member sent whose sender runs an
   addon from before the key is the sender's own unkeyed window under another name: the unkeyed aggregates count that
   one, so a kept window a member sent is left out of the three window tables when a window with no key, from any
   character, was read within 5 s of it with the same items (it still joins its kill). A kill is not a window:
   `kills` joins the windows by key, and a keyed kill with no window is a corpse nobody looted or that held nothing,
   so kills never go into the count of looted corpses. Every kill with a key counts, whoever got the credit, and a
   kill read for skinning only is not looted. Where the counts live is under Developer and agent experience.
8. Kills with no corpse key are never summed across characters as corpses. Their figures are solo only, or per member
   (member kills over member hours), where two uploaders in one group add to both sides of the ratio. A kill with a
   key is one corpse whoever got credit, so corpses looted of kills covers grouped play too (rule 7).
9. A partial window is not a sample of the table. A second reader sees what is left. Quest items are per player,
   so a window lists them only for a reader on the quest. Both are reasons the unit is the first read.

## Metrics

Base sets, as SQL filters:

```
window       loot_opens where counted and has_items and first_read is not false and source_id is not null
window item  loot_drops where counted and via = 'window' and first_read is not false and source_id is not null
receipt      loot_drops where counted and via = 'receipt'
member kill  kills where counted and not player
solo kill    member kill where credit in (1, 2) and group_size in (0, 1) and at >= 2026-11-20 21:00 UTC
```

A window also carries `read`. A drop table reads `read = 'loot'`, a skinning table `read = 'skin'`, and fishing,
herbs and ore are `fish`, `herb`, `ore`. Before phase 2 every creature window was counted as loot, skinning
included.

**A window with no `read`** is from a session not decoded at version 10 yet, or from one with no `combat` part: the
casts that name a gathering window are `combat` lines, so such a session never gets a `read`. Such a window may be
a loot window or a skin window, so it counts in no table. the aggregates keep it in
their null `read` bucket and the readers leave it out through `App\Support\LootRead`; nothing counts it as loot
while the re-decode runs, so a page shows only decoded windows until the re-decode has finished, and there is no
switch on `sessions.decoder_version`. The rows that hold the null bucket stay in the aggregates, so the readers can
say how many windows were left out.

| Metric | Numerator | Denominator | Unit | From |
| --- | --- | --- | --- | --- |
| Drop rate | windows of the source that list the item | windows of the source | window | phase 1 |
| Per drop | sum of `quantity` over those window items | windows that list the item | window | phase 1 |
| Per 100 corpses | 100 x sum of `quantity` | windows of the source | window | phase 1 |
| Items per corpse | window items of the source or arm | windows of the source or arm | window | phase 1 |
| Not seen, upper bound | high end of the range at k = 0 | windows of the source | window | phase 1 |
| Share of skins | skin windows that list the item | skin windows of the source | window | phase 2 |
| Coin only | windows with `item_rows = 0` | windows of the source | window | phase 2 |
| Zone rate | 100 x window items of the item on creatures in the zone | creature loot windows in the zone | window | phase 3 |
| Drop rate by rule | as Drop rate, inside one `loot_rule` | windows of the source under that rule | window | phase 4 |
| Drop rate by size | as Drop rate, inside one `loot_rule` and `group_size` | windows of that arm | window | phase 4 |
| Rule difference | rate under rule A minus rate under rule B, one source | n of each arm | window | phase 4 |
| Kept by the looter | sum of `quantity` of the receipts whose source is the window (last `LO` within 5 s) | sum of `quantity` of the window items of the same windows | quantity | phase 4 |
| Corpses looted of kills | corpses of the creature with a loot window, and solo loot windows with no key | kill corpses with a key, and solo member kills with no key | kill | phase 2, phase 7 |
| Drop rate a kill | windows that list the item, of those corpses | the same kills | kill | phase 2, phase 7 |
| Kills an hour by size | member kills while grouped at the size | member seconds grouped at the size / 3600 | member kill | phase 2 |
| Share of group loot | own receipts while grouped | own receipts + `group_loot` rows, same spans | receipt | phase 5 |
| Need share | rolls of type need | rolls of type need, greed or pass | roll | phase 5 |
| Rolled per 100 corpses | 100 x window items at or above `threshold` | windows in a group | window | phase 5 |
| Need share by class | need rolls by a class | rolls by that class on items of that armour type | roll | phase 6 |
| Skins per corpse | skin windows | loot windows, same group spans, by skinner present or not | window | phase 6 |

Notes on the definitions:

- **Windows that list the item**, not rows. Two stacks of one item in one window are two `loot_drops` rows today,
  so a rate can pass 100%. Phase 2 has the decoder merge them into one row with the summed quantity.
- **Solo** means `group_size` is 0 or 1. Solo figures count only sessions whose `groups` hold both `combat` and
  `loot` and that fall after item lists began (2026-11-20 15:00), so kills from before are not in the denominator.
  Opens-to-kills ratios over all time mix periods and no page shows one. The two conditions are met by the rows
  themselves: `kills.group_size` is null without a `loot` part and `read` is null without a `combat` part, so a
  solo kill and a window with a `read` both come from a session that holds both. For kills with no key,
  `kills_solo_attributed_daily` holds the solo kills and the page reads the windows from `loot_windows_unkeyed_daily`
  and `loot_items_unkeyed_daily` at a group size of 0 or 1. For kills with a key `loot_kills_keyed_daily` and
  `loot_kill_items_keyed_daily` hold the kill corpses of every group size, each with whether and what its corpse
  gave.
  Two picks, both the narrower reading: a solo kill is one the character's own play made, `credit` 1 (its last hit) or
  2 (a pet or a damage-over-time spell it had on the creature's threat list: a warlock's kills are mostly these, and
  every one of them has a window; `credit` 0 is a party member's and a missing credit is a `?` the client kept
  secret), and the cutoff is 15:00 in the addon author's time, UTC-6, so 21:00 UTC; the
  lines name no time zone, and the later instant drops a few hours of kills rather than count kills that had no
  window. Counts a window listed more often than there are kills stay as counts (`25 of 20`), with no rate.
- **A kill with no window** is either an empty corpse or one nobody looted. The two are not told apart until the
  corpse key exists, so Drop rate a kill is a lower bound and the page says `of kills`, never `drop rate`, for it.
- **Kept by the looter** needs no dedupe: both sides are one uploader's own rows. Both sides are summed quantities,
  since a window lists a stack of one item as one row and the looter's client writes an `O` line for each stack it picks
  up, so counting rows would put 2 receipts over 1 window item and pass 100%.
- **Share of group loot** needs no dedupe either: it is one uploader's view of its own group, and pooling
  uploaders gives the mean share per member.
- **Quest items** (`items.bind_type = 4`) keep their rate and carry the tag `quest item`: the denominator includes
  readers who were not on the quest.

## Statistical treatment

**Range.** Every rate is `k of n` with a 95% Wilson score range, computed in one place
(`App\Support\RateEstimate`). Wilson behaves at k = 0 and at small n, needs no library, and never leaves 0 to
100%. At k = 0 the high end is the answer to "how rare could it be": 0 of 125 gives under 3.0%, 0 of 39 under
9.0%.

**Minimum sample.** One ladder, the same on every page and in the API.

| Windows | `sample` | Shown |
| --- | --- | --- |
| under 10 | `counts` | `k of n` only. No rate, no bar, not ranked |
| 10 to 29 | `hollow` | rate, range and bar, drawn hollow. Ranked. Never a headline figure |
| 30 and over | `solid` | drawn solid. Can be Best drop rate, can carry a comparison mark |

Ranking is by the low end of the range. That orders 5 of 13 (low end 17.7%) above 8 of 28 (15.3%) and both far
above any 1 of 1, which is the behaviour wanted from thin data.

**Comparisons stay inside one source.** A rule or size comparison is drawn only between arms of the same source
and the same `read`. The naive figure over all sources is never computed for a page: on 2026-11-22 it said free
for all holds 1.05 items per corpse against 0.85 under group loot, which is the creature mix (free for all was
played on level 60 creatures in Winterspring and the Plaguelands, group loot on Riverglades humanoids).

**The difference.** For two arms the page gives the difference in points with a Newcombe range (built from the two
Wilson ranges). It prints three states and no p-value: `no difference found` (the range spans zero), a mark (the
range excludes zero, both arms have 30 windows, and it still excludes zero at 0.05 divided by the number of items
compared in the source), or nothing (an arm is under 10).

**What a mark means.** The domain says the rule and the size do not change the table. So a mark is not a finding
about the game. It lists the tags of both arms and it goes to a developer list (`/loot/groups?marks=1`, and the
API), because the likeliest cause is counting. Ivory Boar Tusk is the proof: +24.9 points, range 9.8 to 40.7,
caused by skin windows.

**Which arms are compared.** A chart compares the two arms with the most windows that are drawn (10 or more).
`personal` is drawn and never compared. The windows with no corpse key of a free-for-all arm of two or more characters
are counted by its one reader before any ladder step or comparison, so its `n` is that character's windows with no
key plus the corpses with a key. The items compared are the rows of
the chart; a mark needs 0.05 divided by that number to keep the range off zero, with the Newcombe range rebuilt
from Wilson ranges at the matching quantile. `different characters` is set on a mark whose two arms share no
character.

**Pooled across sources.** On `/loot/groups`, items per corpse by rule is a per-source difference averaged with
Mantel-Haenszel weights (n1 x n2 / (n1 + n2)) over sources with 10 or more windows in both arms. The module prints
how many sources and windows it rests on, and a 95% range of the pooled difference from the variance of the items
in each window (`loot_windows_character_daily` keeps the sum and the sum of squares of item rows). The two rules
compared are `?a=` and `?b=`, else the two rules with the most windows on sources seen under both.

**Tags.** Short, fixed, enumerated in one PHP enum (`App\Support\RateFlag`) and in the API as `flags`. A tag sits
beside the n it qualifies. A page draws a tag only when it changes how to read the number; the API carries every
tag of the estimate. The enum holds the tags of the phases built so far.

| Tag | API value | Set when | From |
| --- | --- | --- | --- |
| `1 character` | `one_character` | every window of the row or arm is from one character | phase 1 |
| `quest item` | `quest_item` | the item binds as a quest item | phase 1 |
| `one reader` | `one_reader` | a free-for-all arm that holds windows with no corpse key from two or more characters | phase 4, narrowed in phase 7 |
| `different characters` | `different_characters` | the two arms of a comparison share no character | phase 4 |
| `rule unknown` | `rule_unknown` | windows from before the first `LM` line of the session | phase 4 |
| `other creatures` | `other_creatures` | a group-size arm exists only on sources no other size has, so no comparison is drawn | phase 4 |

**What five characters can show.** Windows needed for a range within a third of the rate either way:

| True rate | Windows |
| --- | --- |
| 50% | 35 |
| 20% | 140 |
| 5% | 660 |
| 1% | 3,400 |
| 0.2% | 17,000 |

Telling 20% from 30% between two rules takes about 290 windows in each arm (80% power). So the explorer can state
common drops on a few creatures to within about 15 points, bound what was never seen, and say nothing about rare
drops, group size or composition until the data grows. It says so by showing `k of n`, hollow bars and empty
states, not by hiding the pages. Drop tables do not depend on who looted, so one busy character is a valid sample
of a table; the character count matters for privacy and for catching one client's recording fault, which is why
the tag exists.

**Privacy threshold.** `scarlet.min_characters` hides a bucket under N characters. It stays 1 on loot pages: a
drop table names nobody, and at 5 the explorer would be empty. The character count is on every row of the index
and in the API.

## Group rules and composition

### What is real in the game

| Lever | Changes | Does not change |
| --- | --- | --- |
| Loot rule | who may open the corpse, who receives each item, whether an item is rolled | what the corpse holds |
| Threshold | which qualities are rolled or assigned | what the corpse holds |
| Group size | each member's share (1 over the size under group loot and round robin), kills an hour | what the corpse holds |
| Classes and roles | who needs what, whether anyone skins, kills an hour, deaths | what the corpse holds |
| Level spread | experience and pace | loot |
| Quest state of the reader | whether the window lists the quest item | everything else |

`personal` is the one rule the table above may not hold for: each member would read a table of their own. Its
windows are never pooled with another rule, on any page, including All rules.

So "how do rules and composition affect drop rates" has an honest answer in three parts: the corpse holds the same;
who takes it home changes; how many corpses an hour changes. The pages are built as those three parts, and the
first part is shown as a check of the claim against the data, not as an assumption.

### What the current facts answer

`loot_opens` and `loot_drops` carry `loot_rule`, `threshold`, `group_size`, `zone_id`, `level`, `character_id`.

- Drop rate, items per corpse and quantity by rule and by size, inside one source.
- Kept by the looter by rule and size.
- Rolled per 100 corpses by threshold, since quality is on `items`.
- Where: zone and level of every window.
- Not answerable: who else received, rolls, classes, roles, level spread, kills by size (`kills` has no size).

### What needs a new decoded fact

All of these are a decoder version and an automatic re-decode from stored parts, except `LW`.

**Columns added to existing rows (decoder version 10):**

| Table | Column | Type | Meaning |
| --- | --- | --- | --- |
| `loot_opens`, `loot_drops` | `read` | string | `loot`, `skin`, `herb`, `ore`, `fish`, `bag` (kind `i`), `other`. A gathering kind when a `gather_items` row of that kind belongs to the window |
| `loot_opens` | `item_rows` | int | item entries in the window; 0 is coin or currency only |
| `loot_opens` | `map`, `x`, `y` | int or null | the last `P` at or before the window, as `gathers` reads it; ingest adds `u`, `v` |
| `loot_opens`, `kills` | `group_at` | int or null | `at` of the `group_spans` row in force; null solo or without a `social` part |
| `kills` | `loot_rule`, `group_size` | string, int or null | the rule in force, as on `loot_opens` |
| `loot_drops` | (behaviour) | | one window row per item: stacks of one item in one window are merged, quantity summed |

**`group_spans`** (social: `GP`, `GM`, `GL m`, `GL s`). One row per stretch in which nothing below changed. No
names. Removing `social` deletes the rows and nulls `group_at`.

| Key | Type | |
| --- | --- | --- |
| `end` | int | ms |
| `size` | int | `GP` size |
| `raid` | bool | `GL s` |
| `members_known` | int | members with a `GL m` line, the uploader included |
| `classes` | string | class files of the known members, sorted, comma-joined: `HUNTER,PRIEST` |
| `tanks`, `healers`, `damage`, `no_role` | int | by `GL m` role |
| `level_min`, `level_max` | int or null | over known members |
| `guild_mates`, `friends` | int | `GL m` flags |
| `skinners` | int or null | uploader only today: 1 when the uploader has the Skinning line; other members' professions are not recorded |

**`group_loot`** (loot: `LT`). What other members received.

| Key | Type | |
| --- | --- | --- |
| `kind` | string | `l` looted, `p` pushed |
| `who` | string | pseudonym |
| `class_file` | string or null | from `GL m` through the session's `pn` lines; null without `social` |
| `item_id`, `quantity` | int | |
| `loot_rule`, `threshold`, `group_size` | | as `loot_opens` |
| `source_kind`, `source_id` | string, int or null | the `peer_opens` row of the same `who` and item within 60 s (decoder version 12); null when that member sent no window |

**`loot_rolls`** (loot: `LD`, `RW`, `RL`). One row per player per roll; rolls on one item within 60 s are one roll,
and a roll that arrives from both chat and history is one row.

| Key | Type | |
| --- | --- | --- |
| `roll_no` | int | order of the roll in the session |
| `item_id` | int | |
| `who` | string | own name or pseudonym |
| `mine` | bool | |
| `class_file` | string or null | as `group_loot` |
| `roll_type` | string or null | `pass`, `need`, `greed`, `disenchant`, `transmog` |
| `roll` | int or null | the number |
| `outcome` | string or null | `won`, `lost`, `passed`, `all_passed` |
| `decide_ms` | int or null | own rolls: `RW o` extra |
| `loot_rule`, `threshold`, `group_size` | | |

**`peer_opens`, `peer_drops`** (`LW`, decoder version 12). The same keys as `loot_opens` and `loot_drops` without a
position, plus `who` (pseudonym) and `late_s`. Kept in their own tables so no existing query can count them. `LW` is
in `loot` in `groups.tsv`, with a `LW 1 sender` row in `player-fields.tsv`. `read` is `fish` for the fishing bobber and
null otherwise, since the window does not say what its sender read it for. What they add: the windows of group
members who run Scarlet and do not upload, the source of a member's receipt, and who opened each corpse (how evenly
group loot rotates). When the peer also uploads, the row repeats that peer's own `LO`; pseudonyms are per install, so
the two cannot be joined by name, and only the corpse key removes the repeat. docs/ROWS.md has the keys.

**Corpse key** (addon, decoder version 12). A field appended to `K`, `LO` and `LW`: 10 hex digits of a hash of the
creature or object GUID, the same on every client. It makes a corpse countable once across uploaders, joins a kill to
its window (empty corpses become visible), joins the loot window to the skin window of one corpse, and removes `LW`
repeats. It stores a hash of a creature GUID, which FORMAT.md before this change says is never stored. The decoder
writes it as `corpse_key` on `kills`, `loot_opens`, `loot_drops`, `peer_opens` and `peer_drops`; null on a line from
before the key.

**Object names** (addon, decoder version 12 needs nothing). An `ON objectID, name` name line, read from the tooltip
when the object is used. It is a `names` row of kind `object`, voted like an npc name. Until a name has votes an
object is labelled by what it gives.

## Developer and agent experience

- **One estimate type.** `App\Support\RateEstimate` holds `k`, `n`, `rate`, `low`, `high`, the sample step and the
  flags. Every Action returns it and every page draws it through the Blade component `x-rate`, so no page formats
  a rate itself.
- **One Action per question**, as the house rules say: `LootSources`, `LootItems`, `SourceDrops`,
  `SourceRuleArms`, `LootRuleEffects`, `ObjectRecord`. `DropRates` and `ItemSources` stay until their callers
  move, then go (three ships).
- **Two aggregates**: `loot_windows_daily` (source, `read`, rule, size, zone; windows, item
  rows, characters) and `loot_items_daily` (the same keys and item; windows with the item, quantity). The old
  `opens_daily` and `drops_daily` are dropped, and so is `loot_windows_daily`, which the counted views of phase 7 replaced. Phase 4 adds three beside them: `loot_windows_character_daily` (windows
  by source, read, rule, size and character, with the sum and the sum of squares of item rows),
  `loot_freeforall_items_daily` (the same by item, free for all only, for the one reader; dropped in phase 7 for its unkeyed twin)
  and `loot_receipts_daily` (receipts that came from a corpse). These count one character's first reads, which is what the pages that pair a
  character's windows with its own receipts read (What the looter kept, Your share of group loot).
- **Corpses counted once (phase 7).** Windows without a key are the same aggregates over `corpse_key is null`:
  `loot_windows_unkeyed_daily`, `loot_items_unkeyed_daily`, `loot_windows_character_unkeyed_daily`,
  `loot_freeforall_items_unkeyed_daily` and `kills_solo_attributed_daily`. Windows with a key cannot be a sum of daily
  buckets, since one corpse is in the days of every character that read it, so `RebuildKeyedLoot` fills five plain
  tables whole (`loot_windows_keyed_daily`, `loot_items_keyed_daily`, `loot_windows_character_keyed_daily`,
  `loot_kills_keyed_daily`, `loot_kill_items_keyed_daily`) in one transaction at the end of every aggregate refresh,
  under the same lock, so every ingest, re-decode and removal reaches them. The views `loot_windows_counted`,
  `loot_items_counted` and `loot_windows_character_counted` put a keyed table under its unkeyed twin with a `keyed`
  column, in the shape of the aggregate they replace, so a read changes one table name. The keyed rows are aggregated
  like the others (a day, a source, a read, a rule, a size, a zone), so a page reads rows in proportion to what was
  recorded, never a row for each window. Rebuilt whole, 260,000 keyed windows, 780,000 window items, 20,000 windows
  from members and 260,000 kills (about 100 times the production rows of 2026-11-22, spread over 300 sources) take
  4.8 s on the development machine and leave 2,101 window rows and 6,302 item rows. Spread over 2,000 sources and 30
  days, with no grouping at all, the same rows take 10 s and leave 153,637 and 460,911; the index of sources and items
  then reads in 19 ms and 79 ms from the counted views, where counting the distinct corpse keys of the same rows at
  page time takes 257 ms and 884 ms and reads every window, and a cold `/loot` takes 1.6 s, `/creatures/{id}` 0.2 s
  and a warm page 40 ms.
- The group tables (`group_spans`, `group_loot`, `loot_rolls`) are read directly, through `RollupRead` and its cache.
- **Enums own the strings**: `LootRead`, `RateFlag`, `LootRule` (the eight methods), so `scarlet.identityLiteral`
  holds.
- **API**: `/public/loot/sources`, `/public/loot/items`, `/public/loot/groups`, `/public/objects/{id}`, and a
  `loot` object added to `/public/creatures/{id}` and `/public/items/{id}`. Fields are only added. Every rate is
  the same object under the key `estimate`, so an agent never parses a percentage or guesses a denominator. Phase 1
  adds it to the `drops` rows of `/public/creatures/{id}` and the `sources` rows of `/public/items/{id}`, and adds
  `by_source` to the item (one row for each source, ranked):

```json
{"item_id": 265358, "name": "Stolen Ore",
 "estimate": {"k": 28, "n": 39, "rate": 0.718, "low": 0.562, "high": 0.835,
          "unit": "window", "sample": "solid", "flags": ["quest_item"]},
 "quantity": 28, "per_drop": 1.0}
```

  `rate` and the range are `null` while `sample` is `counts`.
- **Checks**: one `e2e/loot.mjs` that drives the real pages and asserts every rate on them carries its `k of n`.
  No unit tests.

## Build order

Each phase is a few small ships and leaves the site working. Phases 1 to 4 need no new upload.

| Phase | Ships | Needs |
| --- | --- | --- |
| 1. Ranges on the two pages that exist | `RateEstimate`; `k of n`, range and the sample ladder on the creature Drops and item Where it drops modules; All rules as the creature default; one row per source on the item page; the `skins counted` tag; comps updated | read side only. No migration, no decoder |
| 2. Count the right things | decoder 10 (`read`, merged stacks, `item_rows`, position, rule and size on `kills`); the two new aggregates beside the old; callers move; Skinning table; solo kill figures; old aggregates dropped | decoder bump, re-decode runs by itself |
| 3. Browse | `/loot`, `/objects/{id}`, Looted here on the zone page, By zone and the map on the item page, What it holds for containers, the nav item and `SitePages` row | phase 2 |
| 4. Rule and size inside a source | the two comparison charts, the mark and its developer list, `/loot/groups` with What a corpse held, What the looter kept and Kills an hour by size | phase 2 |
| 5. Who received and rolls | decode `LT`, `LD`, `RW`, `RL` into `group_loot` and `loot_rolls`; Share of group loot, Rolls, Rolls in groups on the item page | decoder bump only; the lines are already uploaded |
| 6. Composition | `group_spans`, `group_at`, `class_file` on rolls and receipts; the composition module; Who received it by class | decoder bump; `social` parts |
| 7. Exact corpses | **write side done** (decoder version 12): corpse key on `K`, `LO`, `LW`; `LW` uploaded; `peer_opens`, `peer_drops`; `group_loot` source; object names. **read side done:** every page counts a corpse with a key once (the counted views), `corpses looted of kills` and `Of kills` cover grouped play, and `one reader` and its tag apply only to the windows with no key of a free-for-all arm; no arm carries the tag once none of its windows lacks a key | addon release and the sharing-group change, both shipped |

Phase 1 is one support class, two Actions, two Blade modules and two comps. It changes what a visitor reads: a row
that said `0.8%` from one drop says `1 of 125, 0.1 to 4.4%`, a source with three windows stops showing rates, and
the item page stops splitting each source into rows per rule. It ships a known fault with a label (`skins counted`)
and phase 2 removes the label. Phase 2 is the one that makes the numbers right, and nothing in phases 3 and 4 is
built on the old aggregates.

## Examples

Read 2026-11-22. Ranges are 95% Wilson.

**Mature Paletusk** (Riverglades, levels 40 to 43, 2 characters). As phase 1 counts it, 125 windows with skinning
mixed in, tag `skins counted`:

| Item | Drop rate | Range | Corpses |
| --- | --- | --- | --- |
| Heavy Leather | 49.6% | 41.0 to 58.2 | 62 of 125 |
| Chunk of Boar Meat | 17.6% | 11.9 to 25.2 | 22 of 125 |
| Ivory Boar Tusk | 17.6% | 11.9 to 25.2 | 22 of 125 |
| Thick Leather | 12.0% | 7.4 to 18.9 | 15 of 125 |
| Large Boar Tusk | 8.0% | 4.4 to 14.1 | 10 of 125 |
| Regal Gloves | 0.8% | 0.1 to 4.4 | 1 of 125 |

After the split in phase 2, Chunk of Boar Meat is 22 of 46 (47.8%, 34.1 to 61.9) in Drops and Heavy Leather is 62
of 78 (79.5%, 69.2 to 87.0) in Skinning. The Ivory Boar Tusk rule row becomes 6 of 18 under group loot against 14
of 23 under free for all, both hollow, `no difference found`. A phase 4 mock shows the marked row with its tags,
because it is the case the design exists for.

**A source with one rule.** A creature recorded under group loot only has an empty rule chart (`Only group loot
recorded.`) and a size chart that names the sizes seen (`Group loot recorded at size 2 (37) and 3 (2).`). A quest
item such as Stolen Ore drops at 51 to 72% from four creatures and sells for nothing; the tag `quest item` comes
from `items.bind_type`.

**An item with many sources.** Mageweave Cloth had 30 sources. 17 had 10 windows or more and ranked by the low
end: 5 of 13 (17.7 to 64.5) led 8 of 28 (15.3 to 47.1), and two sources with 30 windows ranked 12th and 13th at 2
of 35 and 2 of 39. 13 sources showed counts only. Best drop rate was a dash, because the top-ranked source had 13
windows. A creature with 0 of 125 bounds the rate under 3.0%.

**An object.** An object with no name row shows as `Object 649094`: two items, 28 of 48 and 20 of 48, both hollow
by rule, `no difference found`. This is the plain case: three rules, one table.

**Loot rules and group size.** Nine creatures had group loot and free for all. One had 10 windows under both. The
naive figure over all sources (0.85 items per corpse under group loot, 1.05 under free for all) is the one the page
never shows. Group loot at size 5 had 78 windows on 19 creatures, 18 of them seen at no other size: tag `other
creatures`, no chart. Of 1,418 window items with a known quality, 71 were uncommon or better (5.0%).

## Decisions

Taken 2026-11-22 as the design recommended; the owner can switch any of them.

1. `scarlet.min_characters` stays 1 for loot pages, with the character count on every row.
2. `LW` is uploaded, in the `loot` group, with the sender as a pseudonym (phase 7).
3. The addon adds a corpse key (a hash of the GUID) to `K`, `LO` and `LW` (phase 7). It reverses "the GUID is not
   stored" for creatures and objects only, as a hash.
4. Group composition is public as class, role and level counts with no names, from `social` parts only. A
   character with `social` off adds nothing to those modules, and its own class is not used in its place: the class of
   an uploader counts only for a session that has a `group_spans` row (Who received it, by class).
5. A Loot item goes in the site nav after Map.
6. Fishing stays on `/professions/fishing`, out of the explorer's tables, with one link.
