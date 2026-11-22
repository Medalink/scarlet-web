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
    /** The places of the podium on the first page of a board. */
    private const int PODIUM = 3;

    /**
     * @param  array{scope: PageScope, board: Board, rows: list<array<string, mixed>>, total: int, classes: list<string>}  $r
     * @param  list<array{id: int, slug: string, label: string, kind: string|null}>  $rulesets
     * @return array<string, mixed>
     */
    public static function data(array $r, array $rulesets, ?string $class, int $offset, int $page): array
    {
        $board = $r['board'];
        $scope = $r['scope'];
        $slug = $scope->ruleset?->slug();
        $link = fn (array $change = []): string => self::link($board, ['ruleset' => $slug, 'server' => $scope->server === Server::All ? null : $scope->server->value, 'class' => $class, ...$change]);

        [$shown, $next] = Cursor::page($r['rows'], $offset, $page);
        $day = fn (mixed $at): string => Carbon::parse((string) $at)->utc()->format('j M');
        $since = fn (array $row): string => $row['since'] === null ? '' : ' since '.$day($row['since']);
        $podium = $offset === 0 ? array_slice($shown, 0, self::PODIUM) : [];
        $rows = array_map(fn (array $row): array => [
            'class' => $row['mine'] ? 'me' : null,
            'cells' => [
                (string) $row['rank'],
                ['text' => (string) $row['name'], 'href' => '/c/'.$row['name'], 'dot' => true, 'characterClass' => (string) $row['class']],
                Format::character(null, $row['race'], $row['class']),
                $board->format($row['value']).($board->level() === null ? $since($row) : ''),
                ['text' => (string) ($row['ruleset_label'] ?? ''), 'tag' => $row['hardcore'] ? 'HC' : null],
                Format::number($row['deaths']).$since($row),
                $row['at'] === null ? '' : $day($row['at']),
            ],
        ], array_slice($shown, count($podium)));

        return [
            'board' => $board,
            'title' => $board->title(),
            'sub' => $board->description(),
            'boards' => [
                ['All boards', LeaderboardHallPage::link(LeaderboardHallPage::query($scope))],
                ...array_map(fn (Board $item): array => [$item->title(), self::link($item, ['ruleset' => $slug, 'class' => $class])], Board::cases()),
            ],
            'boardSelected' => $board->title(),
            'rulesets' => Filters::items($scope, $rulesets, $link(['ruleset' => null]), fn (array $item): string => $link(['ruleset' => $item['slug'], 'server' => null])),
            'servers' => $slug === null ? array_map(fn (Server $server): array => ['label' => $server->label(), 'href' => $link(['server' => $server === Server::All ? null : $server->value]), 'on' => $server === $scope->server], Server::cases()) : null,
            'classes' => [['Every class', $link(['class' => null])], ...array_map(fn (string $name): array => [Format::className($name), $link(['class' => $name])], $r['classes'])],
            'classSelected' => $class === null ? 'Every class' : Format::className($class),
            'columns' => [
                ['label' => '', 'class' => 'rk'], ['label' => 'Character'], ['label' => 'Class'], ['label' => $board->column(), 'numeric' => true],
                ['label' => 'Ruleset'], ['label' => 'Deaths', 'numeric' => true], ['label' => $board->dateColumn(), 'numeric' => true],
            ],
            'podium' => array_map(fn (array $row): array => self::place($board, $row, $day), $podium),
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

    /**
     * One place of the podium. The unit under the value is the board's column in lower case with the date the
     * character was first recorded; a fastest board names the day the level was reached instead.
     *
     * @param  array<string, mixed>  $row
     * @param  callable(mixed): string  $day
     * @return array{rank: int, mine: bool, name: string, href: string, class: string, what: string, value: string, unit: string, tag: string|null}
     */
    private static function place(Board $board, array $row, callable $day): array
    {
        $unit = $board->level() === null
            ? strtolower($board->column()).($row['since'] === null ? '' : ' since '.$day($row['since']))
            : ($row['at'] === null ? '' : 'reached '.$day($row['at']));

        return [
            'rank' => (int) $row['rank'],
            'mine' => (bool) $row['mine'],
            'name' => (string) $row['name'],
            'href' => '/c/'.$row['name'],
            'class' => (string) $row['class'],
            'what' => Format::character(null, $row['race'], $row['class']),
            'value' => $board->format($row['value']),
            'unit' => $unit,
            'tag' => $row['hardcore'] ? 'HC' : null,
        ];
    }

    private static function count(Board $board, int $total): string
    {
        $who = Format::count($total, 'public character');
        $level = $board->level();

        return $level === null ? $who.' on this board.' : $who.($total === 1 ? ' has' : ' have').' a recorded level-up to '.$level.'.';
    }
}
