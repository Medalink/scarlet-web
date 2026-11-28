<?php

namespace App\View\Design;

/** The data of docs/comps/developers.html: the endpoints of the API. */
final class DevelopersPage
{
    private const PREFIX = '/api/v1/public';

    /**
     * @return array<string, mixed>
     */
    public static function data(): array
    {
        $endpoints = array_map(fn (array $e): array => [
            'method' => $e[0], 'path' => $e[1], 'id' => trim((string) preg_replace('/[^a-z]+/', '-', $e[1]), '-'),
            'short' => str_replace(self::PREFIX, '', $e[1]), 'summary' => $e[2], 'params' => $e[3],
        ], [
            ['GET', '/api/v1/public/stats', 'Totals and the series of the global dashboard.', [['ruleset', 'A ruleset id. Leave out for all rulesets'], ['class', 'A class file, such as WARRIOR']]],
            ['GET', '/api/v1/public/rulesets', 'Every ruleset with its totals.', []],
            ['GET', '/api/v1/public/rulesets/{id}/levels', 'Played time to reach each level: median, quartiles, fastest tenth, characters.', []],
            ['GET', '/api/v1/public/leaderboards/{board}', 'A leaderboard of public characters.', [['ruleset', 'A ruleset id'], ['cursor', 'From next_cursor of the last answer']]],
            ['GET', '/api/v1/public/search', 'Public characters, creatures, items, zones and quests by name.', [['q', 'At least 2 characters'], ['kind', 'character, creature, item, zone or quest']]],
            ['GET', '/api/v1/public/characters/{name}', 'A character whose owner made it public. 404 otherwise.', []],
            ['GET', '/api/v1/public/creatures/{id}', 'Kills, deaths and drop rates by loot rule.', [['ruleset', 'A ruleset id']]],
            ['GET', '/api/v1/public/items/{id}', 'Item facts and where it drops.', []],
            ['GET', '/api/v1/public/zones/{id}', 'Time, deaths, kills and quests in a zone.', []],
            ['GET', '/api/v1/public/maps/{id}/heat', 'Path and death counts per map cell.', [['layer', 'paths or deaths']]],
        ]);

        return [
            'endpoints' => $endpoints,
            'nav' => [
                ['title' => 'Basics', 'links' => [['href' => '#use', 'label' => 'Using the API'], ['href' => '#limits', 'label' => 'Limits and keys'], ['href' => '#pages', 'label' => 'Pagination']]],
                ['title' => 'Endpoints', 'links' => array_map(fn (array $e): array => ['href' => '#'.$e['id'], 'label' => $e['short']], $endpoints)],
            ],
        ];
    }
}
