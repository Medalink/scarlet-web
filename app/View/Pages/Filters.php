<?php

namespace App\View\Pages;

use App\Support\PageScope;
use App\Support\Server;

/** The ruleset and server filters of a dashboard page, as the seg and pick components take them. */
final class Filters
{
    /** More rulesets than this are offered in a drop-down instead of a segmented control. */
    private const int SEG_LIMIT = 3;

    /**
     * @param  list<array{id: int, slug: string, label: string}>  $rulesets
     * @param  string  $statsPath  the page for every ruleset, with the server as a query
     * @return array{rulesets: list<array<string, mixed>>, rulesetsPick: list<array{0: string, 1: string}>|null, rulesetSelected: string|null, servers: list<array<string, mixed>>|null}
     */
    public static function for(PageScope $scope, array $rulesets, string $statsPath = '/stats', string $rulesetPath = '/rulesets'): array
    {
        $items = [['label' => 'All', 'href' => $statsPath, 'on' => $scope->ruleset === null]];
        foreach ($rulesets as $ruleset) {
            $items[] = ['label' => $ruleset['label'], 'href' => $rulesetPath.'/'.$ruleset['slug'], 'on' => $scope->ruleset?->id === $ruleset['id']];
        }
        $many = count($rulesets) > self::SEG_LIMIT;

        $servers = null;
        if ($scope->ruleset === null) {
            $servers = array_map(fn (Server $server): array => [
                'label' => $server->label(),
                'href' => $server === Server::All ? $statsPath : $statsPath.'?server='.$server->value,
                'on' => $server === $scope->server,
            ], Server::cases());
        }

        return [
            'rulesets' => $many ? [] : $items,
            'rulesetsPick' => $many ? array_map(fn (array $item): array => [$item['label'], $item['href']], $items) : null,
            'rulesetSelected' => $many ? ($scope->ruleset?->label() ?? 'All') : null,
            'servers' => $servers,
        ];
    }
}
