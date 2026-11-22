<?php

namespace App\Support;

use App\Http\Controllers\Api\V1\Public\CharacterController;
use App\Http\Controllers\Api\V1\Public\LeaderboardController;
use App\Http\Controllers\Api\V1\Public\MapController;
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
    public const array NAMES_CHARACTERS = ['leaderboard', 'search', 'character', 'character-series'];

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
                'summary' => 'The boards: fastest to level 20, 40 and 60, highest level without dying, most quests, most kills, most jumps, most dungeon runs and most distance.',
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
                'summary' => 'Characters (only those made public), creatures, items, zones and quests whose name contains the text, best match first.',
                'params' => ['q', ['kind', 'query', 'string', '`characters`, `creatures`, `items`, `zones` or `quests`. Leave out for the first hits of every kind.'], 'cursor', 'limit'],
                'returns' => 'Without `kind`: `data` has one entry per kind with `count` and `hits`. With `kind`: `data` is the list of hits, paged with `meta.next_cursor`. A hit has `kind`, `id`, `name`, `path` and the numbers of its kind.',
                'example' => '/search?q=ash&kind=zones', 'where' => [],
            ],
            [
                'name' => 'character', 'group' => 'Characters', 'path' => '/characters/{name}', 'action' => [CharacterController::class, 'show'],
                'summary' => 'A public character: race, class, level, ruleset, time played, this week, distance walked, feats and standings. A character that is not public answers 404.',
                'params' => [['name', 'path', 'string', 'The character name, `Name-Surname`.']],
                'returns' => '`data`: `name`, `race`, `class`, `faction`, `level`, `played_seconds` (the total the game reports, `null` until Scarlet has read it), `recorded_seconds` (play Scarlet counted), `first_level`, `recorded_since`, `whole_life` (recorded from level 1), `ruleset`, `last_seen_at`, `week`, `distance`, `feats` (a list, highest tier first: `key`, one of `summit`, `slayer`, `questing`, `delver`, `explorer`, `veteran`, `devoted`, `wayfarer`, `jumper`, `swimmer`, `unbroken`, `artisan`, `crafter`, `angler`, `gatherer` or `tracker`, `name`, `value`, `tier` from 0 for none to 5 for legend, with `tier_name` one of `Copper`, `Silver`, `Gold`, `Scarlet` or `Legend`, `threshold` of the tier held and `next`, the threshold of the tier above, `null` at the top; `artisan` counts the sum of the character\'s profession ranks), `standings` (a list, best place first, of the boards the character is on among public characters of every ruleset: `board`, `title`, `place`, `total` and `value`). `meta.economy` says whether the owner shows money.',
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
                'params' => [['id', 'path', 'integer', 'The creature id, as in the address of its page.'], 'ruleset', 'server'],
                'returns' => '`data`: `facts`, `kills`, `deaths`, `deaths_by_level`, `drops` (item, loot rule, drops, opens, rate) and `cells`. Drop rates cover every ruleset.',
                'example' => '/creatures/257926', 'where' => ['id' => '[0-9]+'],
            ],
            [
                'name' => 'item', 'group' => 'Records', 'path' => '/items/{id}', 'action' => [RecordController::class, 'item'],
                'summary' => 'One item: what it is and where it drops, by source and loot rule.',
                'params' => [['id', 'path', 'integer', 'The item id, as in the address of its page.']],
                'returns' => '`data`: `item`, `sources` (source, loot rule, drops, opens, rate), `dropped`, `source_count`, `best_rate`, `first_at`.',
                'example' => '/items/1465', 'where' => ['id' => '[0-9]+'],
            ],
            [
                'name' => 'zone', 'group' => 'Records', 'path' => '/zones/{id}', 'action' => [RecordController::class, 'zone'],
                'summary' => 'One zone: time played, deaths, what killed characters there and the creatures met. A dungeon has a negative id.',
                'params' => [['id', 'path', 'integer', 'The zone id, as in the address of its page.'], 'ruleset', 'server'],
                'returns' => '`data`: `rect` (the zone on the world map), `played`, `deaths`, `killers`, `creatures`.',
                'example' => '/zones/1446', 'where' => ['id' => '-?[0-9]+'],
            ],
            [
                'name' => 'quest', 'group' => 'Records', 'path' => '/quests/{id}', 'action' => [RecordController::class, 'quest'],
                'summary' => 'One quest: accepts, turn-ins, abandons, experience, and the levels and zones where it is turned in.',
                'params' => [['id', 'path', 'integer', 'The quest id, as in the address of its page.'], 'ruleset', 'server'],
                'returns' => '`data`: `totals`, `levels` (level, turn-ins) and `zones`.',
                'example' => '/quests/6521', 'where' => ['id' => '[0-9]+'],
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
}
