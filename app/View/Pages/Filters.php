<?php

namespace App\View\Pages;

use App\Models\Ruleset;
use App\Support\PageScope;
use App\Support\Server;

/** The ruleset and server filters of a dashboard page, as the seg and pick components take them. */
final class Filters
{
    /** More rulesets than this are offered in a drop-down instead of a segmented control. */
    private const int SEG_LIMIT = 3;

    /**
     * The same filters for a page whose path stays and whose ruleset and server are query parameters.
     *
     * @param  list<array{id: int, slug: string, label: string}>  $rulesets
     * @param  array<string, string>  $keep  other query parameters to carry over
     * @return array{rulesets: list<array<string, mixed>>, rulesetsPick: list<array{0: string, 1: string}>|null, rulesetSelected: string|null, servers: list<array<string, mixed>>|null}
     */
    public static function onPage(PageScope $scope, array $rulesets, string $path, array $keep = []): array
    {
        $rulesets = self::named($scope, $rulesets);
        $link = fn (array $query): string => $path.(($query = array_filter([...$keep, ...$query])) === [] ? '' : '?'.http_build_query($query));
        $items = [['label' => 'All', 'href' => $link([]), 'on' => $scope->ruleset === null && $scope->server === Server::All]];
        foreach ($rulesets as $ruleset) {
            $items[] = ['label' => $ruleset['label'], 'href' => $link(['ruleset' => $ruleset['slug']]), 'on' => $scope->ruleset?->id === $ruleset['id']];
        }
        $many = count($rulesets) > self::SEG_LIMIT;

        return [
            'rulesets' => $many ? [] : $items,
            'rulesetsPick' => $many ? array_map(fn (array $item): array => [$item['label'], $item['href']], $items) : null,
            'rulesetSelected' => $many ? ($scope->ruleset?->label() ?? 'All') : null,
            'servers' => $scope->ruleset === null ? array_map(fn (Server $server): array => [
                'label' => $server->label(),
                'href' => $link($server === Server::All ? [] : ['server' => $server->value]),
                'on' => $server === $scope->server,
            ], Server::cases()) : null,
        ];
    }

    /**
     * @param  list<array{id: int, slug: string, label: string}>  $rulesets
     * @param  string  $statsPath  the page for every ruleset, with the server as a query
     * @return array{rulesets: list<array<string, mixed>>, rulesetsPick: list<array{0: string, 1: string}>|null, rulesetSelected: string|null, servers: list<array<string, mixed>>|null}
     */
    public static function for(PageScope $scope, array $rulesets, string $statsPath = '/stats', string $rulesetPath = '/rulesets'): array
    {
        $rulesets = self::named($scope, $rulesets);
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

    /**
     * The rulesets a filter offers: the one with no recorded rules is left out when a named one exists, unless it is
     * the one on show. /rulesets still lists it.
     *
     * @param  list<array{id: int, slug: string, label: string}>  $rulesets
     * @return list<array{id: int, slug: string, label: string}>
     */
    private static function named(PageScope $scope, array $rulesets): array
    {
        $named = array_values(array_filter($rulesets, fn (array $ruleset): bool => $ruleset['slug'] !== Ruleset::UNKNOWN_SLUG));
        if ($named === []) {
            return $rulesets;
        }

        return array_values(array_filter($rulesets, fn (array $ruleset): bool => $ruleset['slug'] !== Ruleset::UNKNOWN_SLUG || $scope->ruleset?->id === $ruleset['id']));
    }
}
