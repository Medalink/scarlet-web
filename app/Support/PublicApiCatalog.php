<?php

namespace App\Support;

use App\Http\Controllers\Api\V1\Public\CampsController;
use App\Http\Controllers\Api\V1\Public\CharacterController;
use App\Http\Controllers\Api\V1\Public\LeaderboardController;
use App\Http\Controllers\Api\V1\Public\LootController;
use App\Http\Controllers\Api\V1\Public\MapController;
use App\Http\Controllers\Api\V1\Public\ProfessionController;
use App\Http\Controllers\Api\V1\Public\RecordController;
use App\Http\Controllers\Api\V1\Public\SearchController;
use App\Http\Controllers\Api\V1\Public\StatsController;

/**
 * Every endpoint of the public API, once. routes/api.php registers the routes from this list, the /developers page
 * renders it, and docs/API.md describes the same endpoints.
 */
final class PublicApiCatalog
{
    /** The prefix of every public route's name; the rest is the endpoint's `name`. */
    public const string ROUTE_PREFIX = 'public.';

    /** The endpoints whose answers name characters. */
    public const array NAMES_CHARACTERS = ['leaderboard', 'camps', 'profession', 'search', 'character', 'character-series'];

    /** The shared parameters, named once and referred to by the endpoints. */
    public const array PARAMS = [
        'ruleset' => ['ruleset', 'query', 'string', 'A ruleset slug from `/rulesets`. Leave out for all rulesets.'],
        'server' => ['server', 'query', 'string', '`all`, `pve` or `pvp`: the rulesets of one kind of server. Ignored when `ruleset` is given.'],
        'class' => ['class', 'query', 'string', 'A class file name such as `WARRIOR`.'],
        'cursor' => ['cursor', 'query', 'string', 'The `next_cursor` of the previous page.'],
        'limit' => ['limit', 'query', 'integer', 'Rows per page, 1 to 100. Default 25.'],
        'q' => ['q', 'query', 'string', 'The text to search for, 2 to 80 characters.'],
    ];

    /**
     * @return list<array{name: string, path: string, group: string, action: class-string|array{0: class-string, 1: string}, summary: string, params: list<string|array{0: string, 1: string, 2: string, 3: string}>, returns: string, example: string, where: array<string, string>}>
     */
    public static function endpoints(): array
    {
        return [
            [
                'name' => 'stats', 'group' => 'Statistics', 'path' => '/stats', 'action' => [StatsController::class, 'stats'],
                'summary' => 'Headline numbers: characters, experience, time recorded, kills, deaths, quests and money, with when the data starts and was last updated.',
                'params' => ['ruleset', 'server'],
                'returns' => '`data.totals`, `data.coverage`, `data.rulesets`. A total is `null` while fewer than `meta.min_characters` characters contributed to it.',
                'example' => '/stats', 'where' => [],
            ],
            [
                'name' => 'camps', 'group' => 'Statistics', 'path' => '/camps', 'action' => CampsController::class,
                'summary' => 'How characters use campfires: visits, how long they last, what gets set up, the Camp Benefits players leave with, play while holding them, where and when characters camp, and the characters with the most time by the fire.',
                'params' => ['ruleset', 'server'],
                'returns' => '`data.totals` (`visits`, `characters`, `seconds`, `median_seconds`, `played_seconds` and the counts of visits that earned Camp Benefits, sat, used a tent, logged out or died after, with the AFK and crafting figures), `fires` (visits by the campfire the character placed, and `spell_id` `null` for a fire already there), `camp_benefits`, `benefits` (the nine benefit buffs: `spell_id`, `label`, `effect`, `gains`, `two_hour_gains`, `seconds`, `lost_to_death`, `characters`), `lengths` (visits by `step` of 30 seconds, 40 is 20 minutes and over), `by_level`, `hours` (visit starts by UTC `weekday`, 0 is Sunday, and `hour`), `buffed` (play with Camp Benefits against the same levels across all play), `zones`, `heat` (cells of the zone map with the most visits), `board` (the first rows of `most-time-by-the-fire`) and `rulesets`. A figure is `null` or its row is left out while fewer than `meta.min_characters` characters contributed to it.',
                'example' => '/camps', 'where' => [],
            ],
            [
                'name' => 'profession', 'group' => 'Statistics', 'path' => '/professions/{slug}', 'action' => ProfessionController::class,
                'summary' => 'How characters work with one profession. The four gatherings, `fishing`, `herbalism`, `mining` and `skinning`, answer with attempts and catches, the time they take, what they give, the rank characters reach, where and when they gather, and the characters with the most catches. The eight crafts, `alchemy`, `blacksmithing`, `enchanting`, `engineering`, `leatherworking`, `tailoring`, `cooking` and `first-aid`, answer with crafts, their sittings, recipes, reagents, the rank characters reach, where and when they craft, and the characters with the most crafts. Every other slug answers 404.',
                'params' => [['slug', 'path', 'string', 'A profession slug: `fishing`, `herbalism`, `mining`, `skinning`, `alchemy`, `blacksmithing`, `enchanting`, `engineering`, `leatherworking`, `tailoring`, `cooking` or `first-aid`.'], 'ruleset', 'server'],
                'returns' => 'For a crafting profession: `data.profession` (the slug), `totals` (`crafts`, `made`, `characters`, `scope_characters`, `recipes`, `sittings`, `median_sitting_crafts`, `median_sitting_seconds`, `median_gap`, `skill_ups`, `trivial`, `median_skill`, `at_cap`), `recipes` (the twelve with the most crafts, with `item_id`, `item`, `crafts`, `made`, `skill_ups`, `characters`, `median_skill` and `reagents`), `difficulty` (crafts and `skill_ups` by the recipe\'s difficulty, 0 optimal to 3 trivial), `per_point` (by `band` of 25 ranks), `by_level`, `hours` (crafts by UTC `weekday`, 0 is Sunday, and `hour`), `reagents` (what the crafts used), `zones`, `board` (the first rows of the board of crafts) and `rulesets`. For a gathering: `data.kind` (`fish`, `herb`, `ore` or `skin`), `totals` (`attempts`, `looted`, `characters`, `scope_characters`, `seconds`, `played_seconds`, `median_seconds`, `trips` with their median length and attempts, `in_combat`, `skill_ups`, `median_skill`, `at_cap`, `items`, `container_catches`, and `schools` for fishing or `first_strikes` for mining, `null` for the others), `items` and `contents` (what came out of caught containers), `waits` (looted attempts by `step` of 2 seconds, 15 is 30 seconds and over), `per_point` (by `band` of 25 ranks), `by_level`, `hours` (attempt starts by UTC `weekday`, 0 is Sunday, and `hour`), `lures`, `lured` and `poles` (fishing), `creatures` (skinning), `zones`, `heat` (cells of the zone map with the most attempts, counted as `visits`), `board` (the first rows of the board of looted attempts) and `rulesets`. A figure is `null` or its row is left out while fewer than `meta.min_characters` characters contributed to it.',
                'example' => '/professions/fishing', 'where' => ['slug' => '[a-z-]+'],
            ],
            [
                'name' => 'rulesets', 'group' => 'Statistics', 'path' => '/rulesets', 'action' => [StatsController::class, 'rulesets'],
                'summary' => 'Every ruleset with the numbers of the comparison table and its levelling curve.',
                'params' => [],
                'returns' => '`data`: a list of rulesets with `slug`, `label`, `hardcore`, `war_mode`, `kind` (`normal`, `pvp`, `roleplay`, `roleplay-pvp` or `hardcore`, `null` while Scarlet cannot tell which ruleset a character is on), `recorded` (false for the row of characters whose ruleset is not known yet), `characters`, `seconds`, `deaths`, `xp`, `quest_xp`, `reached` (characters whose level is at least the target, whether or not Scarlet saw them level up), `median_to_target` (only from recorded level-ups) and `levelling` (level, median seconds, characters that recorded the level-up).',
                'example' => '/rulesets', 'where' => [],
            ],
            [
                'name' => 'ruleset', 'group' => 'Statistics', 'path' => '/rulesets/{slug}', 'action' => [StatsController::class, 'ruleset'],
                'summary' => 'The headline numbers of one ruleset.',
                'params' => [['slug', 'path', 'string', 'A ruleset slug from `/rulesets`.']],
                'returns' => 'The same as `/stats`, for this ruleset.',
                'example' => '/rulesets/normal', 'where' => ['slug' => '[a-z0-9-]+'],
            ],
            [
                'name' => 'series', 'group' => 'Statistics', 'path' => '/rulesets/{slug}/series/{series}', 'action' => [StatsController::class, 'series'],
                'summary' => 'One series of a ruleset: `levels`, `cap`, `xp`, `deaths`, `dungeons`, `professions`, `gold` or `activity`. Use `all` as the slug for every ruleset.',
                'params' => [['slug', 'path', 'string', 'A ruleset slug, or `all`.'], ['series', 'path', 'string', 'The series name.']],
                'returns' => '`data`: the rows of the series. Buckets with fewer than `meta.min_characters` characters are left out.',
                'example' => '/rulesets/all/series/levels', 'where' => ['slug' => '[a-z0-9-]+', 'series' => '[a-z]+'],
            ],
            [
                'name' => 'leaderboards', 'group' => 'Leaderboards', 'path' => '/leaderboards', 'action' => [LeaderboardController::class, 'index'],
                'summary' => 'The boards: fastest to level 20, 40 and 60, highest level without dying, most quests turned in, most quests completed, most kills, most jumps, most dungeon runs, most distance, most time by the fire, most fish caught, most herbs gathered, most ore mined, most creatures skinned and most crafts for each crafting profession.',
                'params' => [],
                'returns' => '`data`: a list of boards with `board` (the name used in the path), `title`, `description` and `value` (the heading of the value column).',
                'example' => '/leaderboards', 'where' => [],
            ],
            [
                'name' => 'leaderboard', 'group' => 'Leaderboards', 'path' => '/leaderboards/{board}', 'action' => [LeaderboardController::class, 'show'],
                'summary' => 'One board, best first. Only public characters with counted sessions are listed.',
                'params' => [['board', 'path', 'string', 'A board name from `/leaderboards`.'], 'ruleset', 'server', 'class', 'cursor', 'limit'],
                'returns' => '`data`: rows with `rank`, `name`, `race`, `class`, `value`, `ruleset`, `hardcore`, `deaths`, `since` (the date the character was first recorded, `null` when recorded from level 1) and `at`. `meta.next_cursor`, `meta.total`.',
                'example' => '/leaderboards/most-quests?limit=2', 'where' => ['board' => '[a-z0-9-]+'],
            ],
            [
                'name' => 'search', 'group' => 'Search', 'path' => '/search', 'action' => SearchController::class,
                'summary' => 'Characters (only those made public), creatures, items, objects, zones and quests whose name holds every word of the text, and the site\'s own pages. Best match first: exact, prefix, word, contains, then a close spelling when nothing else matched.',
                'params' => ['q', ['kind', 'query', 'string', '`characters`, `creatures`, `items`, `objects`, `zones` or `quests`. Leave out for the first hits of every kind and the pages.'], 'cursor', 'limit'],
                'returns' => 'Without `kind`: `data` has one entry per kind and a seventh, `pages`, each with `count` and `hits`. With `kind`: `data` is the list of hits, paged with `meta.next_cursor`. A hit has `kind`, `id`, `name`, `path`, `match` (`exact`, `prefix`, `word`, `contains` or `fuzzy`) and the numbers of its kind; a text of digits also finds the creature, item, object, quest or zone with that id as an `exact` hit. A page hit has `title`, `path`, `meta` and `match`.',
                'example' => '/search?q=ash&kind=zones', 'where' => [],
            ],
            [
                'name' => 'character', 'group' => 'Characters', 'path' => '/characters/{name}', 'action' => [CharacterController::class, 'show'],
                'summary' => 'A public character: race, class, level, ruleset, time played, this week, distance walked, feats and standings. A character that is not public answers 404.',
                'params' => [['name', 'path', 'string', 'The character name, `Name-Surname`.']],
                'returns' => '`data`: `name`, `race`, `class`, `faction`, `level`, `played_seconds` (the total the game reports, `null` until Scarlet has read it), `recorded_seconds` (play Scarlet counted), `first_level`, `recorded_since`, `whole_life` (recorded from level 1), `ruleset`, `last_seen_at`, `week`, `distance`, `feats` (every feat that applies to the character: those earned first, rarest first, then those not yet earned, closest first; `pinned` is true for a feat the owner pinned, which keeps its place in the list. `key` is one of '.self::featKeys().'; the gold feats `earner`, `spender`, `tycoon` and `windfall` are listed only when `meta.economy` is true; `name`, `value`, `tier` from 0 for none to 5 for legend, with `tier_name` one of `Copper`, `Silver`, `Gold`, `Scarlet` or `Legend`, `threshold` of the tier held and `next`, the threshold of the tier above, `null` at the top; `artisan` counts the ranks of Cooking, First Aid, Fishing and the character\'s two highest primary professions), `standings` (a list, best place first, of the boards the character is on among public characters of every ruleset: `board`, `title`, `place`, `total` and `value`). `meta.economy` says whether the owner shows money.',
                'example' => '/characters/Tolvir-Greane', 'where' => ['name' => '[^/]+'],
            ],
            [
                'name' => 'character-series', 'group' => 'Characters', 'path' => '/characters/{name}/series/{series}', 'action' => [CharacterController::class, 'series'],
                'summary' => 'One series of a public character: `levelling` (played time before each level, with the ruleset median and middle half), `xp`, `deaths`, `dungeons`, `activity`, `sessions`, and `gold` when the owner shows money.',
                'params' => [['name', 'path', 'string', 'The character name.'], ['series', 'path', 'string', 'The series name.']],
                'returns' => '`data`: the rows of the series.',
                'example' => '/characters/Tolvir-Greane/series/levelling', 'where' => ['name' => '[^/]+', 'series' => '[a-z]+'],
            ],
            [
                'name' => 'creature', 'group' => 'Records', 'path' => '/creatures/{id}', 'action' => [RecordController::class, 'creature'],
                'summary' => 'One creature: its level range and kind, kills, deaths to it, drops under each loot rule and the world cells where it was met.',
                'params' => [['id', 'path', 'integer', 'The creature id, as in the address of its page.'], 'ruleset', 'server', ['rule', 'query', 'string', 'The loot rule `by_size` is read under, such as `group`. Leave out for the rule with the most corpses.']],
                'returns' => '`data`: `facts`, `kills`, `deaths`, `deaths_by_level`, `drops` (item, loot rule, drops, opens, rate, `estimate` (`k` drops of `n` corpses looted for the first time, counting only windows read for loot, not for skinning; `rate`, `low` and `high` as the 95% Wilson range, `unit`, `sample` as `counts` under 10 corpses, `hollow` from 10 and `solid` from 30, and `flags` from `one_character`, `quest_item` and, for a free-for-all rule that counts one reader as the arm of `by_rule` does, `one_reader`; `rate`, `low` and `high` are `null` under 10 corpses)), `solo` (`kills`, the solo kills of the creature, `windows`, the solo windows read for loot, an `estimate` of the two with unit `kill`, and `rows` of item and an `estimate` of the windows that listed it over those kills; `estimate` is `null` while there are no solo kills), `skinning` (`windows`, the skin windows of the creature read for the first time, and `rows` of item, `quantity`, `per_drop` and an `estimate` as for `drops`, over those windows and whatever the loot rule), `by_rule` (the drop table split by loot rule: `split`, `rule` (`null`), `rules` and `sizes` (what was recorded, with `windows`), `arms` (one for each rule, with `key`, `label`, `windows`, `corpses`, `characters`, `flags` and `drawn`, true from 10 corpses; a free-for-all arm with windows with no corpse key that a second character contributed to counts one set (docs/LOOT.md, Units of counting, rule 6) and carries `one_reader`), `compared` (the keys of the two drawn arms with the most corpses, never personal loot) and `rows`, one for each item with 10 drops or more, of `item_id`, `item`, `drops`, `arms` (`key` and `estimate` for each arm) and `difference` between the two compared (`difference`, `low` and `high` as shares, `marked` and `flags`; `null` while an arm is under 10 corpses, and `marked` only when both arms have 30 corpses and the range stays off zero after the number of items is taken into account)), `by_size` (the same by group size inside one loot rule: `rule` is the `rule` parameter, else the rule with the most corpses) and `cells`. Drop rates cover every ruleset.',
                'example' => '/creatures/257926', 'where' => ['id' => RecordId::pattern()],
            ],
            [
                'name' => 'item', 'group' => 'Records', 'path' => '/items/{id}', 'action' => [RecordController::class, 'item'],
                'summary' => 'One item: what it is, where it drops by source and loot rule, where it is gathered, the boss encounters that dropped it, the recipes that make or use it, and its auction prices.',
                'params' => [['id', 'path', 'integer', 'The item id, as in the address of its page.']],
                'returns' => '`data`: `item`, `sources` (source, `read`, loot rule, drops, opens, rate, `estimate` as for the drops of a creature), `by_source` (one row for each source and `read` over every loot rule but personal loot: source, `read` (`loot`, `skin`, `herb`, `ore`, `fish`, `bag` or `other`), `quantity`, `per_drop` and `estimate`, ranked by the low end of the range with sources under 10 corpses last; `rule` and the `rules` the item dropped under), `gathered` (where the item is gathered, the 25 sources with the most gathers: `kind`, `source_kind` (`c` creature, `i` container item, null on a row for the whole profession, such as herbs or ore), `source_id`, `source`, `gathers`, `quantity`, `characters`, `median_skill`, and `rate`, for skins the share of that creature\'s skins that gave the item), `prices` (one entry for each ruleset and realm: `ruleset`, `realm`, and `days` of `day`, `price` in copper and `browse`), `dropped` and `source_count` (drop sources with a known `read`, each counted once however it was read, plus gathering sources), `gathered_count` (gathers over every source), `best_rate`, `first_at`, `encounters` (encounter, item, dropped, characters), `made_by` and `used_in` (recipes: spell, output, reagents), `rolls` (the rolls of the characters that uploaded under the rules that roll, or `null` while too few of them rolled: `rolls`, `all_passed`, the rolls everyone passed, and `need`, `greed` and `pass` as an `estimate` with unit `roll`) and `receivers` (what groups received of it by class, never a name: `class`, `received`, the `share` of all receipts as an `estimate` with unit `receipt`, `rolled` and `won`, the rolls of need or greed the class made and won, and `won_by_roll` as an `estimate` with unit `roll`; each figure is one uploader\'s view of its group, so a group with several uploaders counts once for each).',
                'example' => '/items/1465', 'where' => ['id' => RecordId::pattern()],
            ],
            [
                'name' => 'object', 'group' => 'Records', 'path' => '/objects/{id}', 'action' => [LootController::class, 'object'],
                'summary' => 'One object, such as a chest, an ore vein or an herb: how often it was opened, by how many characters, where and at which levels, what it gives and the places it was opened.',
                'params' => [['id', 'path', 'integer', 'The object id, as in the address of its page.'], ['rule', 'query', 'string', 'A loot rule the drop table is read under, such as `group`. Leave out for every loot rule but personal loot.']],
                'returns' => '`data`: `id`, `name` (`null` until the addon has named it), `read` (`loot`, `herb` or `ore`: the read the drop table covers, the one the object has most windows of), `windows`, `characters`, `items`, `zones` (`id`, `name`, `windows`), `level_min` and `level_max` (the levels of the characters that opened it), `drop_table` (`rule`, `windows`, `characters`, `unread`, `rules` and `rows` of `item_id`, `item`, `quantity`, `per_drop` and `estimate` (`k` windows that held the item of `n` windows read for the first time, `rate`, `low` and `high` as the 95% Wilson range, `unit`, `sample` as `counts` under 10 windows, `hollow` from 10 and `solid` from 30, and `flags`; `rate`, `low` and `high` are `null` under 10 windows)) and `points` (the `[u, v]` world positions the characters stood at for the newest 600 openings, or `null`). The fishing bobber answers 404: its catches are on `/professions/fishing`.',
                'example' => '/objects/1731', 'where' => ['id' => RecordId::pattern()],
            ],
            [
                'name' => 'spell', 'group' => 'Records', 'path' => '/spells/{id}', 'action' => [RecordController::class, 'spell'],
                'summary' => 'One spell: what the client says about it (icon, cast time, range, cost, description) and, for a recipe, what it makes and needs.',
                'params' => [['id', 'path', 'integer', 'The spell id, as in the address of its page.']],
                'returns' => '`data`: `name`, `facts` (`icon_id`, `cast_ms`, `min_range`, `max_range`, `costs` as `powerType:cost` joined by commas, `passive`, `description`) and `recipe` (`output`, `optional_slots`, `reagents`).',
                'example' => '/spells/133', 'where' => ['id' => RecordId::pattern()],
            ],
            [
                'name' => 'encounter', 'group' => 'Records', 'path' => '/encounters/{id}', 'action' => [RecordController::class, 'encounter'],
                'summary' => 'One boss encounter: the items it dropped, each drop counted once however many players of the group uploaded it.',
                'params' => [['id', 'path', 'integer', 'The encounter id, as in the address of its page.']],
                'returns' => '`data.drops`: `item_id`, `item`, `dropped` and `characters`, most dropped first.',
                'example' => '/encounters/1065', 'where' => ['id' => RecordId::pattern()],
            ],
            [
                'name' => 'zone', 'group' => 'Records', 'path' => '/zones/{id}', 'action' => [RecordController::class, 'zone'],
                'summary' => 'One zone: time played, deaths, what killed characters there and the creatures met. A dungeon has a negative id.',
                'params' => [['id', 'path', 'integer', 'The zone id, as in the address of its page.'], 'ruleset', 'server'],
                'returns' => '`data`: `rect` (the zone on the world map), `played`, `deaths`, `killers`, `creatures`.',
                'example' => '/zones/1446', 'where' => ['id' => '-?'.RecordId::pattern()],
            ],
            [
                'name' => 'quest', 'group' => 'Records', 'path' => '/quests/{id}', 'action' => [RecordController::class, 'quest'],
                'summary' => 'One quest: accepts, turn-ins, abandons, experience, and the levels and zones where it is turned in.',
                'params' => [['id', 'path', 'integer', 'The quest id, as in the address of its page.'], 'ruleset', 'server'],
                'returns' => '`data`: `totals`, `levels` (level, turn-ins) and `zones`.',
                'example' => '/quests/6521', 'where' => ['id' => RecordId::pattern()],
            ],
            [
                'name' => 'loot-sources', 'group' => 'Loot', 'path' => '/loot/sources', 'action' => [LootController::class, 'sources'],
                'summary' => 'The creatures, objects or containers that were looted, one row for each: how many times, how many different items, the levels, the zone with most of them and the item that was in most of them. Fishing is not here.',
                'params' => [['view', 'query', 'string', '`creatures`, `objects` or `containers`. Default `creatures`.'], ['zone', 'query', 'integer', 'A zone id from `/zones/{id}`. Leave out for every zone.'], ['min', 'query', 'integer', 'The lowest level: the creature level for `creatures`, the level of the looter for the others.'], ['max', 'query', 'integer', 'The highest level, read the same way.'], ['quality', 'query', 'integer', 'The lowest item quality the source gave, 0 poor to 5 legendary.'], ['q', 'query', 'string', 'Text that is in the name of the source or of an item it gave, or the id of the source. 2 to 80 characters.'], ['sort', 'query', 'string', '`windows` (default), `items` or `level`.'], 'cursor', 'limit'],
                'returns' => '`data`: a list of `source_kind` (`c` creature, `g` object, `i` container item), `source_id`, `name`, `path`, `windows` (the corpses or openings read for the first time, every loot rule but personal loot), `items`, `characters`, `level_min`, `level_max`, `zone` (`id`, `name`) and `top` (`item_id`, `item` and the `estimate` (`k` windows that held the item of `n` windows read for the first time, `rate`, `low` and `high` as the 95% Wilson range, `unit`, `sample` as `counts` under 10 windows, `hollow` from 10 and `solid` from 30, and `flags`; `rate`, `low` and `high` are `null` under 10 windows) of the item that was in most of them). `meta.view` and `meta.next_cursor`.',
                'example' => '/loot/sources', 'where' => [],
            ],
            [
                'name' => 'loot-items', 'group' => 'Loot', 'path' => '/loot/items', 'action' => [LootController::class, 'items'],
                'summary' => 'The items that dropped from creatures, objects and containers, one row for each: how many times, from how many sources, the source with the best rate and the zone the item dropped most in. Fishing is not here.',
                'params' => [['zone', 'query', 'integer', 'A zone id from `/zones/{id}`. Leave out for every zone.'], ['quality', 'query', 'integer', 'The lowest item quality, 0 poor to 5 legendary.'], ['q', 'query', 'string', 'Text that is in the name of the item, or its id. 2 to 80 characters.'], ['sort', 'query', 'string', '`windows` (default) or `items`: items by how often they dropped or from how many sources.'], 'cursor', 'limit'],
                'returns' => '`data`: a list of `item_id`, `name`, `quality`, `path`, `dropped` (the corpses and openings that held the item, once each), `sources`, `best` (`source_kind`, `source_id`, `source`, `path` and the `estimate` (`k` windows that held the item of `n` windows read for the first time, `rate`, `low` and `high` as the 95% Wilson range, `unit`, `sample` as `counts` under 10 windows, `hollow` from 10 and `solid` from 30, and `flags`; `rate`, `low` and `high` are `null` under 10 windows) at the source where the low end of the range is highest) and `zone`. `meta.next_cursor`.',
                'example' => '/loot/items', 'where' => [],
            ],
            [
                'name' => 'loot-groups', 'group' => 'Loot', 'path' => '/loot/groups', 'action' => [LootController::class, 'groups'],
                'summary' => 'What loot rules and group size change in loot: the items a corpse held under two loot rules, what the looter kept, the share of group loot each member received, rolls, kills an hour by group size and group composition. Every ruleset; personal loot is in no comparison.',
                'params' => [['source', 'query', 'string', 'A creature or object as its kind and id, `c255851` or `g649094`. Narrows what a corpse held and what the looter kept to it; the other parts cover every creature.'], ['a', 'query', 'string', 'The first loot rule to compare: `group`, `freeforall`, `roundrobin`, `needbeforegreed`, `masterlooter` or `solo`. Leave out with `b` for the two with the most corpses on creatures seen under both.'], ['b', 'query', 'string', 'The second loot rule to compare, as `a`.'], ['marks', 'query', 'boolean', '`true` adds `marks`.']],
                'returns' => '`data`: `source` (`null`, or `source_kind`, `source_id`, `name`, `path`), `figures` (`corpses`, `rules`, `largest`, `characters`, `first_at`, `last_at`, `several`, the creatures seen under two rules, `rolls` and `groups`), `effects` (`seen`, the rules with `windows` and `sources`, `pair`, the two compared, `rows` of `rule`, `items` per corpse and `corpses`, `sources` and `windows` the difference rests on, `difference` of `difference`, `low` and `high` in items per corpse or `null`, and `more`), `kept` (rows of `rule`, `size`, `corpses`, `items`, `received` and `other`, true for a size seen only on creatures no other size has), `share` (rows of `rule`, `size`, `yours`, `others`, `equal_share` and the `estimate` of yours over both, unit `receipt`), `rolls` (`choices` of `rule`, `quality`, `rolls` and `need`, `greed` and `pass` as an `estimate` with unit `roll`, and `thresholds` of `rule`, `threshold`, `corpses`, `items` and `per_100_corpses`), `kills_by_size` (`size`, `kills`, `hours` and `per_hour`), `composition` (`need` of `class`, `armor`, `rolls` and the need `estimate`, `skins` of `uploader_skins`, `looted`, `skinned` and `per_corpse`, and `roles` of `tank`, `healer`, `kills`, `hours` and `per_hour`) and `marks`. An `estimate` has `k`, `n`, `rate`, `low` and `high` as the 95% Wilson range, `unit`, `sample` as `counts` under 10, `hollow` from 10 and `solid` from 30, and `flags`; `rate`, `low` and `high` are `null` under 10. `per_hour`, `per_corpse` and `per_100_corpses` are `null` under 10 kills or corpses. `marks` is `null` unless asked for, else every marked difference between two loot rules or two group sizes of one rule: `source_kind`, `source_id`, `name`, `path`, `split` (`rule` or `size`), `rule` (the rule a size split reads), `item_id`, `item`, `arms` (the two compared, each with `key`, `label`, `windows`, `corpses`, `characters`, `flags`, `drawn` and the item\'s `estimate`) and `difference` (`difference`, `low` and `high` as shares of the arms, `marked` and `flags`); a mark is a counting fault until shown otherwise, since the game\'s drop table depends on neither.',
                'example' => '/loot/groups', 'where' => [],
            ],
            [
                'name' => 'map-heat', 'group' => 'Map', 'path' => '/map/heat', 'action' => [MapController::class, 'heat'],
                'summary' => 'Heat on the world grid: the cells where characters walked, died or met creatures, each with a level from 1 to 4.',
                'params' => ['ruleset', ['layer', 'query', 'string', '`paths`, `deaths` or `creatures`. Default `paths`.'], ['creature', 'query', 'integer', 'A creature id: narrows `creatures` to one creature.']],
                'returns' => '`data.cells`: `i`, `j`, `level`, `value`, `characters`. `meta.grid` gives the grid size.',
                'example' => '/map/heat?layer=deaths', 'where' => [],
            ],
            [
                'name' => 'map-density', 'group' => 'Map', 'path' => '/map/density', 'action' => [MapController::class, 'density'],
                'summary' => 'One whole level of the heat grid, for drawing a density surface. Level 0 is 192 by 128 cells; each level doubles both.',
                'params' => [
                    'ruleset', ['layer', 'query', 'string', '`paths`, `deaths` or `creatures`. Default `paths`.'],
                    ['level', 'query', 'integer', '0 to 3 for `paths`, 0 to 2 for the other layers. Default 0.'],
                    ['creature', 'query', 'integer', 'A creature id: narrows `creatures` to one creature.'],
                ],
                'returns' => '`data`: `level`, `columns`, `rows` and `cells`, a flat list of pairs: the cell index (`j * columns + i`) minus the previous cell\'s, then the cell\'s counter.',
                'example' => '/map/density?layer=paths&level=2', 'where' => [],
            ],
        ];
    }

    /**
     * An endpoint's parameters with the shared ones resolved.
     *
     * @param  list<string|array{0: string, 1: string, 2: string, 3: string}>  $params
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function params(array $params): array
    {
        return array_map(fn (string|array $param): array => is_string($param) ? self::PARAMS[$param] : $param, $params);
    }

    private static function featKeys(): string
    {
        return implode(', ', array_map(fn (Feat $feat): string => '`'.$feat->value.'`', Feat::cases()));
    }
}
