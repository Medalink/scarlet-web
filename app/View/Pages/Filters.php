<?php

namespace App\View\Pages;

use App\Models\RulesetKind;
use App\Support\PageScope;
use App\Support\Server;

/** The ruleset and server filters of a dashboard page, as the seg and pick components take them. */
final class Filters
{
    /** More rulesets than this are offered in a drop-down instead of a segmented control. */
    private const int SEG_LIMIT = 3;

    /** What the disabled Hardcore filter says while no hardcore ruleset exists. */
    public const string HARDCORE_NOT_OUT = 'Hardcore (not out yet)';

    /**
     * The same filters for a page whose path stays and whose ruleset and server are query parameters.
     *
     * @param  list<array{id: int, slug: string, label: string, kind: string|null}>  $rulesets
     * @param  array<string, string>  $keep  other query parameters to carry over
     * @return array{rulesets: list<array<string, mixed>>, rulesetsPick: list<array{0: string, 1: string|null, 2?: bool}>|null, rulesetSelected: string|null, servers: list<array<string, mixed>>|null}
     */
    public static function onPage(PageScope $scope, array $rulesets, string $path, array $keep = []): array
    {
        $link = fn (array $query): string => $path.(($query = array_filter([...$keep, ...$query])) === [] ? '' : '?'.http_build_query($query));
        $items = self::items($scope, $rulesets, $link([]), fn (array $ruleset): string => $link(['ruleset' => $ruleset['slug']]));

        return self::answer($scope, $items, $scope->ruleset === null ? array_map(fn (Server $server): array => [
            'label' => $server->label(),
            'href' => $link($server === Server::All ? [] : ['server' => $server->value]),
            'on' => $server === $scope->server,
        ], Server::cases()) : null);
    }

    /**
     * @param  list<array{id: int, slug: string, label: string, kind: string|null}>  $rulesets
     * @param  string  $statsPath  the page for every ruleset, with the server as a query
     * @return array{rulesets: list<array<string, mixed>>, rulesetsPick: list<array{0: string, 1: string|null, 2?: bool}>|null, rulesetSelected: string|null, servers: list<array<string, mixed>>|null}
     */
    public static function for(PageScope $scope, array $rulesets, string $statsPath = '/stats', string $rulesetPath = '/stats'): array
    {
        $items = self::items($scope, $rulesets, $statsPath, fn (array $ruleset): string => $rulesetPath.'/'.$ruleset['slug']);

        return self::answer($scope, $items, $scope->ruleset === null ? array_map(fn (Server $server): array => [
            'label' => $server->label(),
            'href' => $server === Server::All ? $statsPath : $statsPath.'?server='.$server->value,
            'on' => $server === $scope->server,
        ], Server::cases()) : null);
    }

    /**
     * The ruleset filter: All, then each identified ruleset, then Hardcore disabled while no hardcore ruleset exists.
     * A ruleset no checked signal names yet is offered only while it is the one on show; /rulesets lists it.
     *
     * @param  list<array{id: int, slug: string, label: string, kind: string|null}>  $rulesets
     * @param  callable(array{id: int, slug: string, label: string, kind: string|null}): string  $href
     * @return list<array{label: string, href: string, on: bool, disabled?: bool}>
     */
    public static function items(PageScope $scope, array $rulesets, string $allHref, callable $href): array
    {
        $items = [['label' => 'All', 'href' => $allHref, 'on' => $scope->ruleset === null]];
        $hardcore = false;
        foreach ($rulesets as $ruleset) {
            $hardcore = $hardcore || $ruleset['kind'] === RulesetKind::Hardcore->value;
            if ($ruleset['kind'] !== null || $scope->ruleset?->id === $ruleset['id']) {
                $items[] = ['label' => $ruleset['label'], 'href' => $href($ruleset), 'on' => $scope->ruleset?->id === $ruleset['id']];
            }
        }
        if (! $hardcore) {
            $items[] = ['label' => self::HARDCORE_NOT_OUT, 'href' => '', 'on' => false, 'disabled' => true];
        }

        return $items;
    }

    /**
     * @param  list<array{label: string, href: string, on: bool, disabled?: bool}>  $items
     * @param  list<array<string, mixed>>|null  $servers
     * @return array{rulesets: list<array<string, mixed>>, rulesetsPick: list<array{0: string, 1: string|null, 2?: bool}>|null, rulesetSelected: string|null, servers: list<array<string, mixed>>|null}
     */
    private static function answer(PageScope $scope, array $items, ?array $servers): array
    {
        $many = count($items) - 1 > self::SEG_LIMIT;

        return [
            'rulesets' => $many ? [] : $items,
            'rulesetsPick' => $many ? array_map(fn (array $item): array => empty($item['disabled']) ? [$item['label'], $item['href']] : [$item['label'], null, true], $items) : null,
            'rulesetSelected' => $many ? (array_values(array_filter($items, fn (array $item): bool => $item['on']))[0]['label'] ?? 'All') : null,
            'servers' => $servers,
        ];
    }
}
