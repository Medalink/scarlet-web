<?php

namespace App\View\Pages;

use App\Support\LevelBracket;
use App\Support\PageScope;
use App\Support\PlayerClass;
use App\Support\Server;
use App\Support\TalentTree;
use App\View\Format;

/** The talent pages from the answers of App\Actions\Public\TalentSpecs and TalentSpec. */
final class TalentSpecPage
{
    private const int BLOCK_ROWS = 8;

    /**
     * @param  list<array{class: string, class_slug: string, tab_id: int, name: string, slug: string, characters: int}>  $specs
     * @return array<string, mixed>
     */
    public static function index(array $specs): array
    {
        $total = array_sum(array_column($specs, 'characters'));
        $top = max([1, ...array_column($specs, 'characters')]);

        return [
            'title' => 'Talents',
            'sub' => $specs === [] ? 'No talent builds recorded yet.' : Format::count($total, 'character').' with a talent build, in '.Format::count(count($specs), 'spec').'.',
            'columns' => [['label' => 'Spec'], ['label' => 'Class'], ['label' => 'Characters', 'numeric' => true]],
            'rows' => array_map(fn (array $spec): array => ['cells' => [
                ['text' => $spec['name'].' →', 'href' => '/talents/'.$spec['class_slug'].'/'.$spec['slug'], 'dot' => true, 'characterClass' => strtoupper($spec['class'])], Format::className($spec['class']), ['text' => Format::number($spec['characters']), ...self::bar($spec['characters'], $top)],
            ]], $specs),
        ];
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
        /** @var list<array{node: int, spell: int|null, taken: int, rate: float, rank: float, name: string|null}> $nodes */
        $nodes = $a['nodes'];
        /** @var array{spell: array<int, string>, tab: array<int, string>} $names */
        $names = $a['names'];
        $names['tab'] = self::inGameOrder((string) $a['class'], $names['tab']);

        return [
            'title' => $a['spec'].' '.Format::className((string) $a['class']),
            'classKey' => strtoupper((string) $a['class']),
            'className' => Format::className((string) $a['class']),
            'sub' => $n === 0
                ? ($bracket === LevelBracket::All ? 'No character has a talent build recorded yet.' : 'No character has a build recorded at '.mb_strtolower($bracket->label()).'.')
                : Format::count($n, 'character').', each counted by its latest build'.($bracket === LevelBracket::All ? '.' : ' at '.mb_strtolower($bracket->label()).'.'),
            'n' => $n,
            'brackets' => self::control($bracket, $path, $scope),
            'columns' => $a['layout'] === null || $n === 0 ? [] : self::columns($a['layout'], $nodes, $n, $names),
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
     * One column for each talent tree: the talents characters took, with how many took each, top of the tree first.
     *
     * @param  array<string, mixed>  $layout
     * @param  list<array{node: int, spell: int|null, taken: int, rate: float, rank: float, name: string|null}>  $nodes
     * @param  array{spell: array<int, string>, tab: array<int, string>}  $names
     * @return list<array{name: string, rows: list<array{name: string, href: string|null, rate: string, width: float, title: string}>}>
     */
    private static function columns(array $layout, array $nodes, int $n, array $names): array
    {
        $taken = [];
        foreach ($nodes as $node) {
            $taken[$node['node']] = $node;
        }
        /** @var array<int, array{id: int, y: int, tab: int|null, entries: list<array{entry: int, spell: int|null}>}> $layoutNodes */
        $layoutNodes = $layout['nodes'];
        uasort($layoutNodes, fn (array $a, array $b): int => $b['y'] <=> $a['y']);
        $columns = [];
        foreach ($layoutNodes as $id => $node) {
            $pick = $taken[$id] ?? null;
            if ($pick === null || $pick['rate'] <= 0 || $node['tab'] === null || ! isset($names['tab'][$node['tab']])) {
                continue;
            }
            $spell = $pick['spell'] ?? ($node['entries'][0]['spell'] ?? null);
            $columns[$node['tab']]['name'] = $names['tab'][$node['tab']];
            $columns[$node['tab']]['rows'][] = [
                'name' => $pick['name'] ?? 'Talent '.$id,
                'href' => $spell === null ? null : '/spells/'.$spell,
                'rate' => Format::percent($pick['rate']),
                'width' => round($pick['rate'] * 100, 1),
                'title' => Format::number($pick['taken']).' of '.Format::count($n, 'character').', average rank '.Format::number($pick['rank'], 1),
            ];
        }
        $position = array_flip(array_keys($names['tab']));
        uksort($columns, fn (int $a, int $b): int => $position[$a] <=> $position[$b]);

        return array_values($columns);
    }

    /**
     * The tabs of a class in the order the game lists its trees, which the columns and every build split follow.
     * A tab that matches none of the class's trees goes last.
     *
     * @param  array<int, string>  $tabs
     * @return array<int, string>
     */
    private static function inGameOrder(string $class, array $tabs): array
    {
        $class = PlayerClass::tryFrom(strtoupper($class));
        $place = array_flip(array_map(fn (TalentTree $tree): string => $tree->value, TalentTree::cases()));
        $rank = fn (string $name): int => $class === null || ($tree = TalentTree::of($class, $name)) === null ? PHP_INT_MAX : $place[$tree->value];
        uksort($tabs, fn (int $a, int $b): int => [$rank($tabs[$a]), $a] <=> [$rank($tabs[$b]), $b]);

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
     * @param  list<array{node: int, spell: int|null, taken: int, rate: float, rank: float, name: string|null}>  $nodes
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
