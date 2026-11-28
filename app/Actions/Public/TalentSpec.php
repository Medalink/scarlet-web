<?php

namespace App\Actions\Public;

use App\Support\LevelBracket;
use App\Support\NameKind;
use App\Support\PageScope;
use App\Support\TalentKind;
use App\Support\TalentRanks;
use Illuminate\Support\Facades\DB;

class TalentSpec
{
    /** Points of the most common order the page lists. */
    public const int ORDER_POINTS = 40;

    public function __construct(private readonly TalentSpecs $specs, private readonly TalentLayout $layouts, private readonly TalentOutcomes $outcomes) {}

    /**
     * What the characters of one class and spec picked. Every character counts once, by its latest counted build read
     * at a level inside the bracket, so `characters` is the n of every share on the page.
     *
     * - `nodes`: for each talent, how many of those characters had a point in it and their average rank.
     * - `brackets`: the same count for each level bracket: characters, different builds, the most common build.
     * - `order`: for each point of the build the talent the most characters took with it, from the picks the addon
     *   saw (the points held when the addon first read a build have no order, so this holds the characters that
     *   levelled with it on).
     * - `layout`: the layout of the tree, or null while no session sent one.
     * - `shown`: the ids of the talent tabs the builds list.
     * - `outcomes`: for the most held builds, the levelling, deaths and dungeon runs that happened while they were held.
     *
     * @return array<string, mixed>|null null when no character plays the spec
     */
    public function handle(string $classSlug, string $specSlug, PageScope $scope, LevelBracket $bracket): ?array
    {
        $spec = null;
        $tabs = [];
        foreach ($this->specs->handle(new PageScope(null, $scope->server, null)) as $row) {
            if ($row['class_slug'] === $classSlug && $row['slug'] === $specSlug) {
                $spec ??= $row;
                $tabs[] = $row['tab_id'];
            }
        }
        if ($spec === null) {
            return null;
        }

        $rows = $this->latest($scope, $classSlug, $tabs, $bracket);
        $tree = $this->commonTree($rows);
        $layout = $tree === null ? null : $this->layouts->handle($tree);
        $nodes = $this->nodes($rows);
        $order = $this->order($scope, $classSlug, $tabs);
        $everyone = $bracket === LevelBracket::All ? $rows : $this->latest($scope, $classSlug, $tabs, LevelBracket::All);
        $names = $this->names($nodes, $order, $layout, $everyone);

        return [
            'class' => $spec['class'],
            'class_slug' => $classSlug,
            'spec' => $spec['name'],
            'spec_slug' => $specSlug,
            'bracket' => $bracket,
            'characters' => count($rows),
            'tree_id' => $tree,
            'layout' => $layout,
            'shown' => $this->shownTabs($rows),
            'nodes' => array_map(fn (array $node): array => [...$node, 'name' => $node['spell'] === null ? null : ($names['spell'][$node['spell']] ?? null)], $nodes),
            'order' => array_map(fn (array $point): array => [...$point, 'name' => $point['spell'] === null ? null : ($names['spell'][$point['spell']] ?? null)], $order),
            'brackets' => $this->brackets($scope, $classSlug, $tabs, $names['tab']),
            'outcomes' => array_map(fn (array $build): array => [...$build, 'build' => $this->tabsText($build['tab_points'], $names['tab'])], $this->outcomes->handle($scope, $classSlug, $tabs, $bracket)),
            'names' => $names,
        ];
    }

    /**
     * The latest counted build of each character in the bracket.
     *
     * @param  list<int>  $tabs
     * @return list<\stdClass>
     */
    private function latest(PageScope $scope, string $classSlug, array $tabs, LevelBracket $bracket): array
    {
        [$from, $to] = $bracket->range();

        return array_values(DB::table('talent_snapshots as s')->join('characters as c', 'c.id', '=', 's.character_id')
            ->whereRaw('lower(c.class) = ?', [$classSlug])->whereIn('s.spec_tab_id', $tabs)->where('s.counted', true)->whereBetween('s.level', [$from, $to])
            ->when($scope->ids !== null, fn ($query) => $query->whereIn('s.ruleset_id', (array) $scope->ids))
            ->selectRaw('distinct on (s.character_id) s.character_id, s.level, s.tree_id, s.build_hash, s.tab_points, s.ranks')
            ->orderBy('s.character_id')->orderByDesc('s.at')->get()->all());
    }

    /**
     * The talent tabs the builds list, which are the trees the game shows for the spec.
     *
     * @param  list<\stdClass>  $rows
     * @return list<int>
     */
    private function shownTabs(array $rows): array
    {
        $tabs = [];
        foreach ($rows as $row) {
            array_push($tabs, ...array_column(TalentRanks::tabs((string) $row->tab_points), 'id'));
        }

        return array_values(array_unique($tabs));
    }

    /** @param  list<\stdClass>  $rows */
    private function commonTree(array $rows): ?int
    {
        $count = [];
        foreach ($rows as $row) {
            $count[(int) $row->tree_id] = ($count[(int) $row->tree_id] ?? 0) + 1;
        }
        arsort($count);

        return $count === [] ? null : array_key_first($count);
    }

    /**
     * @param  list<\stdClass>  $rows
     * @return list<array{node: int, spell: int|null, taken: int, rate: float, rank: float}> most taken first
     */
    private function nodes(array $rows): array
    {
        /** @var array<int, array{node: int, spell: int|null, taken: int, ranks: int}> $nodes */
        $nodes = [];
        foreach ($rows as $row) {
            foreach (TalentRanks::parse((string) $row->ranks) as $held) {
                $node = $nodes[$held['node']] ?? ['node' => $held['node'], 'spell' => null, 'taken' => 0, 'ranks' => 0];
                $node['spell'] ??= $held['spell'];
                $node['taken']++;
                $node['ranks'] += $held['rank'];
                $nodes[$held['node']] = $node;
            }
        }
        $total = max(1, count($rows));
        $list = array_map(fn (array $node): array => ['node' => $node['node'], 'spell' => $node['spell'], 'taken' => $node['taken'], 'rate' => $node['taken'] / $total, 'rank' => $node['ranks'] / $node['taken']], array_values($nodes));
        usort($list, fn (array $a, array $b): int => [$b['taken'], $a['node']] <=> [$a['taken'], $b['node']]);

        return $list;
    }

    /**
     * @param  list<int>  $tabs
     * @return list<array{point: int, spell: int|null, characters: int, total: int}>
     */
    private function order(PageScope $scope, string $classSlug, array $tabs): array
    {
        $rows = DB::table('talent_changes as t')->join('characters as c', 'c.id', '=', 't.character_id')
            ->whereRaw('lower(c.class) = ?', [$classSlug])->whereIn('c.spec_tab_id', $tabs)->where('t.counted', true)
            ->where('t.kind', TalentKind::Pick->value)->whereNull('t.respec_index')->whereNotNull('t.point_no')
            ->when($scope->ids !== null, fn ($query) => $query->whereIn('t.ruleset_id', (array) $scope->ids))
            ->groupBy('t.point_no', 't.spell_id')->selectRaw('t.point_no, t.spell_id, count(distinct t.character_id) as characters')
            ->orderBy('t.point_no')->orderByDesc('characters')->orderBy('t.spell_id')->get();

        $points = [];
        foreach ($rows as $row) {
            $point = (int) $row->point_no;
            $points[$point] ??= ['point' => $point, 'spell' => $row->spell_id === null ? null : (int) $row->spell_id, 'characters' => (int) $row->characters, 'total' => 0];
            $points[$point]['total'] += (int) $row->characters;
        }

        return array_slice(array_values($points), 0, self::ORDER_POINTS);
    }

    /**
     * Each bracket's characters, builds and most common build.
     *
     * @param  list<int>  $tabs
     * @param  array<int, string>  $tabNames
     * @return list<array{bracket: LevelBracket, characters: int, builds: int, top: string|null, share: float}>
     */
    private function brackets(PageScope $scope, string $classSlug, array $tabs, array $tabNames): array
    {
        $out = [];
        foreach (LevelBracket::cases() as $bracket) {
            $rows = $this->latest($scope, $classSlug, $tabs, $bracket);
            $builds = [];
            foreach ($rows as $row) {
                $builds[(string) $row->build_hash]['count'] = ($builds[(string) $row->build_hash]['count'] ?? 0) + 1;
                $builds[(string) $row->build_hash]['tabs'] ??= (string) $row->tab_points;
            }
            uasort($builds, fn (array $a, array $b): int => $b['count'] <=> $a['count']);
            $top = $builds === [] ? null : $builds[array_key_first($builds)];
            $out[] = [
                'bracket' => $bracket,
                'characters' => count($rows),
                'builds' => count($builds),
                'top' => $top === null ? null : $this->tabsText($top['tabs'], $tabNames),
                'share' => $top === null ? 0.0 : $top['count'] / count($rows),
            ];
        }

        return $out;
    }

    /** @param  array<int, string>  $tabNames */
    private function tabsText(string $tabPoints, array $tabNames): string
    {
        $parts = [];
        foreach (TalentRanks::tabs($tabPoints) as $tab) {
            if ($tab['spent'] > 0) {
                $parts[] = ($tabNames[$tab['id']] ?? 'Tab '.$tab['id']).' '.$tab['spent'];
            }
        }

        return implode(', ', $parts);
    }

    /**
     * @param  list<array{spell: int|null}>  $nodes
     * @param  list<array{spell: int|null}>  $order
     * @param  array{nodes: array<int, array{entries: list<array{spell: int|null}>, tab: int|null}>}|null  $layout
     * @param  list<\stdClass>  $builds
     * @return array{spell: array<int, string>, tab: array<int, string>}
     */
    private function names(array $nodes, array $order, ?array $layout, array $builds): array
    {
        $spells = [...array_column($nodes, 'spell'), ...array_column($order, 'spell')];
        $tabs = [];
        foreach ($builds as $build) {
            array_push($tabs, ...array_column(TalentRanks::tabs((string) $build->tab_points), 'id'));
        }
        foreach ($layout['nodes'] ?? [] as $node) {
            array_push($spells, ...array_column($node['entries'], 'spell'));
            $tabs[] = $node['tab'];
        }

        return ['spell' => $this->named(NameKind::Spell, $spells), 'tab' => $this->named(NameKind::TalentTab, $tabs)];
    }

    /**
     * @param  list<int|null>  $ids
     * @return array<int, string>
     */
    private function named(NameKind $kind, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, fn (mixed $id): bool => $id !== null)));
        $names = [];
        foreach (DB::table('names')->where('kind', $kind->value)->whereIn('id', $ids)->orderBy('votes')->get(['id', 'name']) as $row) {
            $names[(int) $row->id] = (string) $row->name;
        }

        return $names;
    }
}
