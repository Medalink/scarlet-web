<?php

namespace App\View\Pages;

use App\Support\Board;
use App\Support\Cursor;
use App\Support\PageScope;
use App\Support\Server;
use App\View\Format;
use Illuminate\Support\Carbon;

/** The data of docs/comps/leaderboards.html from the answer of App\Actions\Public\Leaderboard. */
final class LeaderboardPage
{
    /**
     * @param  array{scope: PageScope, board: Board, rows: list<array<string, mixed>>, total: int, classes: list<string>}  $r
     * @param  list<array{id: int, slug: string, label: string}>  $rulesets
     * @return array<string, mixed>
     */
    public static function data(array $r, array $rulesets, ?string $class, int $offset, int $page): array
    {
        $board = $r['board'];
        $scope = $r['scope'];
        $slug = $scope->ruleset?->slug();
        $link = fn (array $change = []): string => self::link($board, ['ruleset' => $slug, 'server' => $scope->server === Server::All ? null : $scope->server->value, 'class' => $class, ...$change]);

        [$shown, $next] = Cursor::page($r['rows'], $offset, $page);
        $rows = array_map(fn (array $row): array => [
            'class' => $row['mine'] ? 'me' : null,
            'cells' => [
                (string) $row['rank'],
                ['text' => (string) $row['name'], 'href' => '/c/'.$row['name'], 'dot' => true],
                Format::character(null, $row['race'], $row['class']),
                $board->format($row['value']),
                ['text' => (string) ($row['ruleset_label'] ?? ''), 'tag' => $row['hardcore'] ? 'HC' : null],
                Format::number($row['deaths']),
                $row['at'] === null ? '' : Carbon::parse((string) $row['at'])->utc()->format('j M'),
            ],
        ], $shown);

        return [
            'board' => $board,
            'title' => $board->title(),
            'sub' => $board->description(),
            'boards' => array_map(fn (Board $item): array => [$item->title(), self::link($item, ['ruleset' => $slug, 'class' => $class])], Board::cases()),
            'boardSelected' => $board->title(),
            'rulesets' => [['label' => 'All', 'href' => $link(['ruleset' => null]), 'on' => $slug === null], ...array_map(fn (array $item): array => ['label' => $item['label'], 'href' => $link(['ruleset' => $item['slug'], 'server' => null]), 'on' => $slug === $item['slug']], $rulesets)],
            'servers' => $slug === null ? array_map(fn (Server $server): array => ['label' => $server->label(), 'href' => $link(['server' => $server === Server::All ? null : $server->value]), 'on' => $server === $scope->server], Server::cases()) : null,
            'classes' => [['Every class', $link(['class' => null])], ...array_map(fn (string $name): array => [Format::className($name), $link(['class' => $name])], $r['classes'])],
            'classSelected' => $class === null ? 'Every class' : Format::className($class),
            'columns' => [
                ['label' => '', 'class' => 'rk'], ['label' => 'Character'], ['label' => 'Class'], ['label' => $board->column(), 'numeric' => true],
                ['label' => 'Ruleset'], ['label' => 'Deaths', 'numeric' => true], ['label' => $board->dateColumn(), 'numeric' => true],
            ],
            'rows' => $rows,
            'total' => $r['total'],
            'count' => self::count($board, $r['total']),
            'next' => $next === null ? null : $link(['cursor' => $next]),
            'step' => $page,
        ];
    }

    /** @param  array<string, string|null>  $query */
    public static function link(Board $board, array $query = []): string
    {
        $query = array_filter($query, fn (?string $value): bool => $value !== null && $value !== '');

        return '/leaderboards/'.$board->value.($query === [] ? '' : '?'.http_build_query($query));
    }

    private static function count(Board $board, int $total): string
    {
        $who = $total === 1 ? '1 public character' : Format::number($total).' public characters';
        $level = $board->level();

        return $level === null ? $who.' on this board.' : $who.($total === 1 ? ' has' : ' have').' reached level '.$level.'.';
    }
}
