<?php

namespace App\View\Pages;

use App\Support\LevelBracket;
use App\Support\PageScope;
use App\Support\Server;
use App\Support\SpellIcons;
use App\Support\TalentTabOrder;
use App\View\Format;
use App\View\TalentFrame;

/** The talent pages from the answers of App\Actions\Public\TalentSpecs and TalentSpec. */
final class TalentSpecPage
{
    private const int BLOCK_ROWS = 8;

    /**
     * @param  list<array{class: string, class_slug: string, tab_id: int, name: string, slug: string, characters: int}>  $specs
     * @param  array{splits: array<string, array<int, int>>, tabs: array<int, string>, order: array<string, list<int>>}|null  $splits  the most common split of each spec and the order of its trees, which puts a column of splits first
     * @return array<string, mixed>
     */
    public static function index(array $specs, ?array $splits = null): array
    {
        $total = array_sum(array_column($specs, 'characters'));
        $top = max([1, ...array_column($specs, 'characters')]);
        $lead = $splits === null ? [] : [['label' => 'Most common split']];

        return [
            'title' => 'Talents',
            'sub' => $specs === [] ? 'No talent builds recorded yet.' : Format::count($total, 'character').' with a talent build, in '.Format::count(count($specs), 'spec').'.',
            'columns' => [...$lead, ['label' => 'Spec'], ['label' => 'Class'], ['label' => 'Characters', 'numeric' => true]],
            'rows' => array_map(fn (array $spec): array => ['cells' => [
                ...($splits === null ? [] : [self::specSplit($spec, $splits)]),
                ['text' => $spec['name'].' →', 'href' => '/talents/'.$spec['class_slug'].'/'.$spec['slug'], 'dot' => true, 'characterClass' => strtoupper($spec['class'])], Format::className($spec['class']), ['text' => Format::number($spec['characters']), ...self::bar($spec['characters'], $top)],
            ]], $specs),
        ];
    }

    /**
     * The points of a spec's most common build in each tree, in the order of its frames: "31 / 20 / 0".
     *
     * @param  array{class_slug: string, class: string, tab_id: int}  $spec
     * @param  array{splits: array<string, array<int, int>>, tabs: array<int, string>, order: array<string, list<int>>}  $splits
     */
    private static function specSplit(array $spec, array $splits): string
    {
        $key = $spec['class_slug'].':'.$spec['tab_id'];
        $points = $splits['splits'][$key] ?? [];
        $order = array_values(array_unique([...($splits['order'][$key] ?? []), ...array_keys($points)]));

        return implode(' / ', array_map(fn (int $id): string => (string) ($points[$id] ?? 0), array_values(array_filter($order, fn (int $id): bool => isset($points[$id])))));
    }

    /** @return array{bar?: string} */
    private static function bar(int $value, int $top): array
    {
        return $value > 0 ? ['bar' => max(0.15, $value / $top * 5.2).'rem'] : [];
    }

    /**
     * The Talents block of /stats: the most played specs, each with its characters.
     *
     * @param  list<array{class: string, class_slug: string, tab_id: int, name: string, slug: string, characters: int}>  $specs
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, total: int}
     */
    public static function block(array $specs): array
    {
        $index = self::index(array_slice($specs, 0, self::BLOCK_ROWS));

        /** @var list<array<string, mixed>> $columns */
        $columns = $index['columns'];
        /** @var list<array<string, mixed>> $rows */
        $rows = $index['rows'];

        return ['columns' => $columns, 'rows' => $rows, 'total' => array_sum(array_column($specs, 'characters'))];
    }

    /**
     * @param  array<string, mixed>  $a
     * @return array<string, mixed>
     */
    public static function data(array $a, string $path, PageScope $scope): array
    {
        /** @var LevelBracket $bracket */
        $bracket = $a['bracket'];
        $n = (int) $a['characters'];
        /** @var list<array{node: int, spell: int|null, taken: int, rate: float, rank: float, picks: array<int, int>, name: string|null}> $nodes */
        $nodes = $a['nodes'];
        /** @var array{spell: array<int, string>, tab: array<int, string>} $names */
        $names = $a['names'];
        $names['tab'] = self::inTreeOrder($names['tab'], TalentTabOrder::of($a['shown'], $a['layout']));
        $cells = $a['layout'] === null || $n === 0 ? [] : TalentFrame::cells($a['layout'], $a['shown']);

        return [
            'title' => $a['spec'].' '.Format::className((string) $a['class']),
            'classKey' => strtoupper((string) $a['class']),
            'className' => Format::className((string) $a['class']),
            'sub' => $n === 0
                ? ($bracket === LevelBracket::All ? 'No character has a talent build recorded yet.' : 'No character has a build recorded at '.mb_strtolower($bracket->label()).'.')
                : Format::count($n, 'character').', each counted by its latest build'.($bracket === LevelBracket::All ? '.' : ' at '.mb_strtolower($bracket->label()).'.'),
            'n' => $n,
            'brackets' => self::control($bracket, $path, $scope),
            'trees' => $cells === [] ? [] : self::trees($cells, $nodes, $n, $names),
            'outside' => $cells === [] ? [] : self::outside($cells, $nodes, $n),
            'nodes' => self::nodeTable($nodes, $n),
            'order' => self::order($a['order']),
            'builds' => self::builds($a['brackets'], $names['tab']),
            'outcomes' => self::outcomes($a['outcomes'], $names['tab']),
        ];
    }

    /**
     * @param  list<array{build: string, characters: int, levels: int, median_s: float|null, deaths: int, runs: int, bosses: int, run_deaths: int}>  $outcomes
     * @param  array<int, string>  $tabs
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    private static function outcomes(array $outcomes, array $tabs): array
    {
        return [
            'columns' => [['label' => 'Build'], ['label' => 'Characters', 'numeric' => true], ['label' => 'Timed level-ups', 'numeric' => true], ['label' => 'Median time to level', 'numeric' => true], ['label' => 'Deaths', 'numeric' => true], ['label' => 'Dungeon runs', 'numeric' => true], ['label' => 'Bosses a run', 'numeric' => true], ['label' => 'Deaths a run', 'numeric' => true]],
            'rows' => array_map(fn (array $row): array => ['cells' => [
                self::split($row['build'], $tabs), Format::number($row['characters']), Format::number($row['levels']), $row['median_s'] === null ? '' : Format::duration($row['median_s']), Format::number($row['deaths']),
                Format::number($row['runs']), $row['runs'] === 0 ? '' : Format::number($row['bosses'] / $row['runs'], 1), $row['runs'] === 0 ? '' : Format::number($row['run_deaths'] / $row['runs'], 1),
            ]], $outcomes),
        ];
    }

    /** @return list<array{label: string, href: string, on: bool}> */
    private static function control(LevelBracket $on, string $path, PageScope $scope): array
    {
        return array_map(function (LevelBracket $bracket) use ($on, $path, $scope): array {
            $query = array_filter([
                'ruleset' => $scope->ruleset?->slug(),
                'server' => $scope->server === Server::All ? null : $scope->server->value,
                'level' => $bracket === LevelBracket::All ? null : $bracket->value,
            ]);

            return ['label' => $bracket === LevelBracket::All ? 'All' : str_replace('-', ' to ', $bracket->value), 'href' => $path.($query === [] ? '' : '?'.http_build_query($query)), 'on' => $bracket === $on];
        }, LevelBracket::cases());
    }

    /**
     * One frame for each talent tree, in the in-game layout: each node with its icon and the share of characters that
     * took it. A node nobody took is drawn dimmed at 0%.
     *
     * @param  list<array{tab: int, nodes: list<array{id: int, col: int, row: int, spells: list<int|null>, max: int, to: list<int>}>}>  $cells
     * @param  list<array{node: int, spell: int|null, taken: int, rate: float, rank: float, picks: array<int, int>, name: string|null}>  $nodes
     * @param  array{spell: array<int, string>, tab: array<int, string>}  $names
     * @return list<array{name: string, width: int, height: int, nodes: list<array<string, mixed>>, arrows: list<array<string, mixed>>}>
     */
    private static function trees(array $cells, array $nodes, int $n, array $names): array
    {
        $taken = [];
        foreach ($nodes as $node) {
            $taken[$node['node']] = $node;
        }
        $spells = [];
        foreach ($cells as $tree) {
            foreach ($tree['nodes'] as $cell) {
                array_push($spells, ...$cell['spells']);
            }
        }
        $icons = SpellIcons::icons(array_values(array_unique(array_filter($spells, fn (?int $spell): bool => $spell !== null))));

        return array_map(function (array $tree) use ($taken, $icons, $names, $n): array {
            $frame = TalentFrame::frame($tree['nodes']);
            $rates = [];
            $drawn = [];
            foreach ($tree['nodes'] as $cell) {
                $pick = $taken[$cell['id']] ?? null;
                $spell = $pick['spell'] ?? $cell['spells'][0];
                $called = fn (?int $of): string => $of === null ? 'Talent '.$cell['id'] : ($names['spell'][$of] ?? 'Talent '.$cell['id']);
                $name = $called($spell);
                $rate = $pick['rate'] ?? 0.0;
                $rates[$cell['id']] = $rate;
                $choice = count($cell['spells']) > 1;
                $count = $pick === null ? 0 : $pick['taken'];
                $head = $choice
                    ? implode(' · ', array_map(fn (?int $of): string => $called($of).' '.Format::number(($pick['picks'] ?? [])[$of ?? 0] ?? 0), $cell['spells']))
                    : $name.', '.Format::number($count);
                $title = $head.' of '.Format::count($n, 'character').($pick === null ? '' : ', average rank '.Format::number($pick['rank'], 1));
                $drawn[] = [
                    'id' => $cell['id'],
                    'left' => $frame['slots'][$cell['id']]['left'],
                    'top' => $frame['slots'][$cell['id']]['top'],
                    'name' => $name,
                    'initial' => mb_strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]/u', '', $name) ?? '', 0, 1)) ?: '?',
                    'icon' => $spell === null ? null : ($icons[$spell] ?? null),
                    'href' => $spell === null ? null : '/spells/'.$spell,
                    'rate' => $count === 0 ? '0%' : ($rate < 0.005 ? '<1%' : Format::percent($rate)),
                    'share' => round($rate, 3),
                    'taken' => $count > 0,
                    'choice' => $choice,
                    'title' => $title,
                ];
            }

            return [
                'name' => $names['tab'][$tree['tab']] ?? 'Tab '.$tree['tab'],
                'width' => $frame['width'],
                'height' => $frame['height'],
                'nodes' => $drawn,
                'arrows' => array_map(fn (array $arrow): array => [...$arrow, 'lit' => ($rates[$arrow['from']] ?? 0.0) > 0], $frame['arrows']),
            ];
        }, $cells);
    }

    /**
     * The talents characters held that the layout does not place, as bars, most taken first.
     *
     * @param  list<array{tab: int, nodes: list<array{id: int}>}>  $cells
     * @param  list<array{node: int, spell: int|null, taken: int, rate: float, rank: float, picks: array<int, int>, name: string|null}>  $nodes
     * @return list<array{name: string, href: string|null, rate: string, width: float, title: string}>
     */
    private static function outside(array $cells, array $nodes, int $n): array
    {
        $placed = [];
        foreach ($cells as $tree) {
            foreach ($tree['nodes'] as $cell) {
                $placed[$cell['id']] = true;
            }
        }

        return array_values(array_map(fn (array $node): array => [
            'name' => $node['name'] ?? 'Talent '.$node['node'],
            'href' => $node['spell'] === null ? null : '/spells/'.$node['spell'],
            'rate' => Format::percent($node['rate']),
            'width' => round($node['rate'] * 100, 1),
            'title' => Format::number($node['taken']).' of '.Format::count($n, 'character').', average rank '.Format::number($node['rank'], 1),
        ], array_filter($nodes, fn (array $node): bool => $node['rate'] > 0 && ! isset($placed[$node['node']]))));
    }

    /**
     * The tabs in the order of `$order`, which is the order of the frames and of every build split; a tab outside it goes last, by id.
     *
     * @param  array<int, string>  $tabs
     * @param  list<int>  $order
     * @return array<int, string>
     */
    private static function inTreeOrder(array $tabs, array $order): array
    {
        $place = array_flip($order);
        uksort($tabs, fn (int $a, int $b): int => [$place[$a] ?? PHP_INT_MAX, $a] <=> [$place[$b] ?? PHP_INT_MAX, $b]);

        return $tabs;
    }

    /**
     * A build as points in each tree, in the order of `$tabs`: "Beast Mastery 4, Marksmanship 2" becomes "4 / 2 / 0".
     *
     * @param  array<int, string>  $tabs
     */
    private static function split(string $build, array $tabs): string
    {
        $points = [];
        foreach ($tabs as $name) {
            $points[] = preg_match('/(?:^|, )'.preg_quote($name, '/').' (\d+)(?:,|$)/', $build, $match) === 1 ? $match[1] : '0';
        }

        return $tabs === [] || array_sum(array_map('intval', $points)) === 0 ? $build : implode(' / ', $points);
    }

    /**
     * @param  list<array{node: int, spell: int|null, taken: int, rate: float, rank: float, picks: array<int, int>, name: string|null}>  $nodes
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    private static function nodeTable(array $nodes, int $n): array
    {
        return [
            'columns' => [['label' => 'Talent'], ['label' => 'Characters', 'numeric' => true], ['label' => 'Pick rate', 'numeric' => true], ['label' => 'Average rank', 'numeric' => true]],
            'rows' => array_map(fn (array $node): array => ['cells' => [
                self::spell($node['name'] ?? 'Talent '.$node['node'], $node['spell']), Format::number($node['taken']).' of '.Format::number($n), Format::percent($node['rate']), Format::number($node['rank'], 1),
            ]], $nodes),
        ];
    }

    /** @return string|array{text: string, href: string} a spell's name linked to its page when the talent has a spell */
    private static function spell(string $name, ?int $spell): string|array
    {
        return $spell === null ? $name : ['text' => $name, 'href' => '/spells/'.$spell];
    }

    /**
     * @param  list<array{point: int, characters: int, total: int, name: string|null, spell: int|null}>  $order
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    private static function order(array $order): array
    {
        return [
            'columns' => [['label' => 'Point', 'numeric' => true], ['label' => 'Talent'], ['label' => 'Took it', 'numeric' => true], ['label' => 'Characters', 'numeric' => true]],
            'rows' => array_map(fn (array $row): array => ['cells' => [
                (string) $row['point'], self::spell($row['name'] ?? 'Unnamed talent', $row['spell']), Format::percent($row['characters'] / max(1, $row['total'])), Format::number($row['total']),
            ]], $order),
        ];
    }

    /**
     * @param  list<array{bracket: LevelBracket, characters: int, builds: int, top: string|null, share: float}>  $brackets
     * @param  array<int, string>  $tabs
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    private static function builds(array $brackets, array $tabs): array
    {
        $brackets = array_values(array_filter($brackets, fn (array $row): bool => $row['characters'] > 0 || $row['bracket'] === LevelBracket::All));

        return [
            'columns' => [['label' => 'Levels'], ['label' => 'Characters', 'numeric' => true], ['label' => 'Different builds', 'numeric' => true], ['label' => 'Most common build'], ['label' => 'Share', 'numeric' => true]],
            'rows' => array_map(fn (array $row): array => ['cells' => [
                $row['bracket']->label(), Format::number($row['characters']), $row['characters'] === 0 ? '' : Format::number($row['builds']), $row['top'] === null ? '' : self::split($row['top'], $tabs), $row['top'] === null ? '' : Format::percent($row['share']),
            ]], $brackets),
        ];
    }
}
