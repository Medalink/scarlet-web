<?php

namespace App\Support;

use App\Http\Controllers\Api\V1\Public\MapController;
use App\Http\Controllers\Api\V1\Public\StatsController;

/**
 * Every endpoint of the public API, once. routes/api.php registers the routes from this list, the /developers page
 * renders it, and docs/API.md describes the same endpoints.
 */
final class PublicApiCatalog
{
    /** The shared parameters, named once and referred to by the endpoints. */
    public const array PARAMS = [
        'ruleset' => ['ruleset', 'query', 'string', 'A ruleset slug from `/rulesets`. Leave out for all rulesets.'],
        'server' => ['server', 'query', 'string', '`all`, `pve` or `pvp`: the rulesets of one kind of server. Ignored when `ruleset` is given.'],
        'cursor' => ['cursor', 'query', 'string', 'The `next_cursor` of the previous page.'],
        'limit' => ['limit', 'query', 'integer', 'Rows per page, 1 to 100. Default 25.'],
        'q' => ['q', 'query', 'string', 'The text to search for, 2 to 80 characters.'],
    ];

    /**
     * @return list<array{name: string, path: string, group: string, action: array{0: class-string, 1: string}, summary: string, params: list<string|array{0: string, 1: string, 2: string, 3: string}>, returns: string, example: string, where: array<string, string>}>
     */
    public static function endpoints(): array
    {
        return [
            [
                'name' => 'stats', 'group' => 'Statistics', 'path' => '/stats', 'action' => [StatsController::class, 'stats'],
                'summary' => 'Headline numbers: characters, experience, time played, kills, deaths, quests and money, with when the data starts and was last updated.',
                'params' => ['ruleset', 'server'],
                'returns' => '`data.totals`, `data.coverage`, `data.rulesets`. A total is `null` while fewer than `meta.min_characters` characters contributed to it.',
                'example' => '/stats', 'where' => [],
            ],
            [
                'name' => 'rulesets', 'group' => 'Statistics', 'path' => '/rulesets', 'action' => [StatsController::class, 'rulesets'],
                'summary' => 'Every ruleset with the numbers of the comparison table and its levelling curve.',
                'params' => [],
                'returns' => '`data`: a list of rulesets with `slug`, `label`, `hardcore`, `war_mode`, `characters`, `seconds`, `deaths`, `xp`, `quest_xp`, `reached`, `median_to_target` and `levelling` (level, median seconds, characters).',
                'example' => '/rulesets', 'where' => [],
            ],
            [
                'name' => 'ruleset', 'group' => 'Statistics', 'path' => '/rulesets/{slug}', 'action' => [StatsController::class, 'ruleset'],
                'summary' => 'The headline numbers of one ruleset.',
                'params' => [['slug', 'path', 'string', 'A ruleset slug from `/rulesets`.']],
                'returns' => 'The same as `/stats`, for this ruleset.',
                'example' => '/rulesets/p1-std-pve', 'where' => ['slug' => '[a-z0-9-]+'],
            ],
            [
                'name' => 'series', 'group' => 'Statistics', 'path' => '/rulesets/{slug}/series/{series}', 'action' => [StatsController::class, 'series'],
                'summary' => 'One series of a ruleset: `levels`, `xp`, `deaths`, `dungeons`, `professions`, `gold` or `activity`. Use `all` as the slug for every ruleset.',
                'params' => [['slug', 'path', 'string', 'A ruleset slug, or `all`.'], ['series', 'path', 'string', 'The series name.']],
                'returns' => '`data`: the rows of the series. Buckets with fewer than `meta.min_characters` characters are left out.',
                'example' => '/rulesets/all/series/levels', 'where' => ['slug' => '[a-z0-9-]+', 'series' => '[a-z]+'],
            ],
            [
                'name' => 'map-heat', 'group' => 'Map', 'path' => '/map/heat', 'action' => [MapController::class, 'heat'],
                'summary' => 'Heat on the world grid: the cells where characters walked, died or met creatures, each with a level from 1 to 4.',
                'params' => ['ruleset', ['layer', 'query', 'string', '`paths`, `deaths` or `creatures`. Default `paths`.'], ['creature', 'query', 'integer', 'A creature id: narrows `creatures` to one creature.']],
                'returns' => '`data.cells`: `i`, `j`, `level`, `value`, `characters`. `meta.grid` gives the grid size.',
                'example' => '/map/heat?layer=deaths', 'where' => [],
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
