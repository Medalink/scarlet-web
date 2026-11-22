<?php

namespace App\View\Pages;

use App\Support\LevelBracket;
use App\Support\PageScope;
use App\Support\Server;
use App\View\Format;
use App\View\TalentTree;

/** The talent pages from the answers of App\Actions\Public\TalentSpecs and TalentSpec. */
final class TalentSpecPage
{
    /** Pick rates at or under each of these (of 1) fall in heat 1 to 3; above the last is heat 4. */
    private const array HEAT = [0.25, 0.5, 0.75];

    private const int BLOCK_ROWS = 8;

    /**
     * @param  list<array{class: string, class_slug: string, tab_id: int, name: string, slug: string, characters: int}>  $specs
     * @return array<string, mixed>
     */
    public static function index(array $specs): array
    {
        $total = array_sum(array_column($specs, 'characters'));

        return [
            'title' => 'Talents',
            'sub' => $specs === [] ? 'No talent builds recorded yet.' : Format::count($total, 'character').' with a talent build, in '.Format::count(count($specs), 'spec').'.',
            'columns' => [['label' => 'Spec'], ['label' => 'Class'], ['label' => 'Characters', 'numeric' => true]],
            'rows' => array_map(fn (array $spec): array => ['cells' => [
                ['text' => $spec['name'], 'href' => '/talents/'.$spec['class_slug'].'/'.$spec['slug'], 'dot' => true, 'characterClass' => strtoupper($spec['class'])], Format::className($spec['class']), Format::number($spec['characters']),
            ]], $specs),
        ];
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

        return [
            'title' => $a['spec'].' '.Format::className((string) $a['class']),
            'classKey' => strtoupper((string) $a['class']),
            'className' => Format::className((string) $a['class']),
            'sub' => $n === 0 ? 'No character has a build read at '.mb_strtolower($bracket->label()).'.' : 'n = '.Format::count($n, 'character').', each counted by its latest build at '.mb_strtolower($bracket->label()).'.',
            'n' => $n,
            'brackets' => self::control($bracket, $path, $scope),
            'tree' => $a['layout'] === null || $n === 0 ? null : self::tree($a['layout'], $nodes, $n, $names),
            'nodes' => self::nodeTable($nodes, $n),
            'order' => self::order($a['order']),
            'builds' => self::builds($a['brackets']),
            'outcomes' => self::outcomes($a['outcomes']),
        ];
    }

    /**
     * @param  list<array{build: string, characters: int, levels: int, median_s: float|null, deaths: int, runs: int, bosses: int, run_deaths: int}>  $outcomes
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    private static function outcomes(array $outcomes): array
    {
        return [
            'columns' => [['label' => 'Build'], ['label' => 'Characters', 'numeric' => true], ['label' => 'Timed level-ups', 'numeric' => true], ['label' => 'Median time to level', 'numeric' => true], ['label' => 'Deaths', 'numeric' => true], ['label' => 'Dungeon runs', 'numeric' => true], ['label' => 'Bosses a run', 'numeric' => true], ['label' => 'Deaths a run', 'numeric' => true]],
            'rows' => array_map(fn (array $row): array => ['cells' => [
                $row['build'], Format::number($row['characters']), Format::number($row['levels']), $row['median_s'] === null ? '' : Format::duration($row['median_s']), Format::number($row['deaths']),
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
     * @param  array<string, mixed>  $layout
     * @param  list<array{node: int, spell: int|null, taken: int, rate: float, rank: float, name: string|null}>  $nodes
     * @param  array{spell: array<int, string>, tab: array<int, string>}  $names
     * @return array<string, mixed>
     */
    private static function tree(array $layout, array $nodes, int $n, array $names): array
    {
        $taken = [];
        foreach ($nodes as $node) {
            $taken[$node['node']] = $node;
        }
        $marks = [];
        /** @var array<int, array{id: int, entries: list<array{entry: int, spell: int|null}>, tab: int|null}> $layoutNodes */
        $layoutNodes = $layout['nodes'];
        foreach ($layoutNodes as $id => $node) {
            $name = implode(' or ', array_map(fn (array $entry): string => ($entry['spell'] === null ? null : ($names['spell'][$entry['spell']] ?? null)) ?? 'Talent '.$entry['entry'], $node['entries'])) ?: 'Node '.$id;
            $rate = $taken[$id]['rate'] ?? 0.0;
            $marks[$id] = [
                'title' => $name.': '.Format::percent($rate).' of '.Format::count($n, 'character').' ('.Format::number($taken[$id]['taken'] ?? 0).')',
                'text' => $rate > 0 ? (string) (int) round($rate * 100) : '',
                'class' => self::heat($rate),
                'on' => $rate > 0,
            ];
        }
        $tabs = [];
        foreach ($layoutNodes as $node) {
            if ($node['tab'] !== null && isset($names['tab'][$node['tab']])) {
                $tabs[$node['tab']] = $names['tab'][$node['tab']];
            }
        }

        /** @var array{nodes: array<int, array{id: int, x: int, y: int, tab: int|null, edges: list<array{to: int, type: string}>}>} $layout */
        return TalentTree::geometry($layout, $marks, $tabs);
    }

    private static function heat(float $rate): string
    {
        if ($rate <= 0) {
            return 'off';
        }
        foreach (self::HEAT as $level => $limit) {
            if ($rate <= $limit) {
                return 'h'.($level + 1);
            }
        }

        return 'h4';
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
                self::spell($node['name'] ?? 'Node '.$node['node'], $node['spell']), Format::number($node['taken']).' of '.Format::number($n), Format::percent($node['rate']), Format::number($node['rank'], 1),
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
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    private static function builds(array $brackets): array
    {
        return [
            'columns' => [['label' => 'Levels'], ['label' => 'Characters', 'numeric' => true], ['label' => 'Different builds', 'numeric' => true], ['label' => 'Most common build'], ['label' => 'Share', 'numeric' => true]],
            'rows' => array_map(fn (array $row): array => ['cells' => [
                $row['bracket']->label(), Format::number($row['characters']), $row['characters'] === 0 ? '' : Format::number($row['builds']), $row['top'] ?? '', $row['top'] === null ? '' : Format::percent($row['share']),
            ]], $brackets),
        ];
    }
}
