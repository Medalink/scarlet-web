<?php

namespace App\View\Pages;

use App\Support\Board;
use App\Support\PageScope;
use App\Support\Server;
use App\View\Format;

/** The data of the hall at /leaderboards from the answer of App\Actions\Public\LeaderboardHall. */
final class LeaderboardHallPage
{
    public const string PATH = '/leaderboards';

    /**
     * @param  array{scope: PageScope, boards: list<array{board: Board, lead: array<string, mixed>|null, total: int}>}  $r
     * @param  list<array{id: int, slug: string, label: string, kind: string|null}>  $rulesets
     * @return array<string, mixed>
     */
    public static function data(array $r, array $rulesets): array
    {
        $scope = $r['scope'];
        $filters = Filters::onPage($scope, $rulesets, self::PATH);
        $query = self::query($scope);

        $entries = array_map(fn (array $item): array => self::entry($item['board'], $item['lead'], $item['total'], $query), $r['boards']);
        $groups = [];
        foreach ($r['boards'] as $i => $item) {
            $groups[$item['board']->group()][] = $entries[$i];
        }

        return [
            'title' => count($r['boards']).' boards',
            'groups' => $groups,
            'sub' => count($r['boards']).' boards of public characters. Each names who leads it now.',
            'filters' => $filters,
            'boards' => array_map(fn (array $item): array => self::entry($item['board'], $item['lead'], $item['total'], $query), $r['boards']),
        ];
    }

    /**
     * The path of the hall, with the ruleset or server of the scope.
     *
     * @return array<string, string|null>
     */
    public static function query(PageScope $scope): array
    {
        return ['ruleset' => $scope->ruleset?->slug(), 'server' => $scope->server === Server::All ? null : $scope->server->value];
    }

    /**
     * @param  array<string, string|null>  $query
     */
    public static function link(array $query = []): string
    {
        $query = array_filter($query, fn (?string $value): bool => $value !== null && $value !== '');

        return self::PATH.($query === [] ? '' : '?'.http_build_query($query));
    }

    /**
     * @param  array<string, mixed>|null  $lead
     * @param  array<string, string|null>  $query
     * @return array{icon: string, title: string, href: string, name: string|null, page: string|null, class: string|null, none: string, value: string|null, count: string}
     */
    private static function entry(Board $board, ?array $lead, int $total, array $query): array
    {
        $level = $board->level();

        return [
            'icon' => $board->icon(),
            'title' => $board->title(),
            'href' => LeaderboardPage::link($board, $query),
            'name' => $lead === null ? null : Format::characterName((string) $lead['name']),
            'page' => $lead === null ? null : '/c/'.rawurlencode((string) $lead['name']),
            'class' => $lead === null ? null : (string) $lead['class'],
            'none' => $level === null ? 'No public character yet' : 'Nobody has a recorded level-up to '.$level.' yet',
            'value' => $lead === null ? null : $board->format($lead['value']),
            'count' => ($board === Board::MostDistance ? 'world widths, ' : '').Format::count($total, 'character'),
        ];
    }
}
