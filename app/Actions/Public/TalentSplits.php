<?php

namespace App\Actions\Public;

use App\Support\LatestTalentBuilds;
use App\Support\NameKind;
use App\Support\PageScope;
use App\Support\TalentRanks;
use App\Support\TalentTabOrder;
use Illuminate\Support\Facades\DB;

class TalentSplits
{
    public function __construct(private readonly TalentLayout $layouts) {}

    /**
     * The most common split of points over the talent trees of each spec, from the latest counted build of every
     * character the spec list counts for the spec, with the names of the trees. A split is the points spent in each tree, and the
     * most common one is the split most characters of the spec hold; among equals the one that sorts first. `order` is
     * the order of the spec's trees, the one its page draws them in (App\Support\TalentTabOrder over the layout of the
     * tree most characters of the spec hold).
     *
     * @return array{splits: array<string, array<int, int>>, tabs: array<int, string>, order: array<string, list<int>>} the splits and the tree orders by `class slug:spec tab id`, the splits as the points in each tab by tab id
     */
    public function handle(PageScope $scope): array
    {
        $rows = DB::query()->fromSub(LatestTalentBuilds::query($scope), 'l')->join('characters as c', 'c.id', '=', 'l.character_id')
            ->whereNotNull('c.class')
            ->groupBy('c.class', 'l.spec_tab_id', 'l.tab_points', 'l.tree_id')->selectRaw('c.class, l.spec_tab_id, l.tab_points, l.tree_id, count(*) as characters')
            ->orderBy('c.class')->orderBy('l.spec_tab_id')->orderBy('l.tab_points')->orderBy('l.tree_id')->get();

        $held = [];
        $game = [];
        $trees = [];
        $tabs = [];
        foreach ($rows as $row) {
            $key = strtolower((string) $row->class).':'.(int) $row->spec_tab_id;
            $points = [];
            foreach (TalentRanks::tabs((string) $row->tab_points) as $tab) {
                $points[$tab['id']] = $tab['spent'];
                $tabs[$tab['id']] = true;
                $game[$key][] = $tab['id'];
            }
            ksort($points);
            $held[$key][json_encode($points, JSON_THROW_ON_ERROR)][] = (int) $row->characters;
            $trees[$key][(int) $row->tree_id] = ($trees[$key][(int) $row->tree_id] ?? 0) + (int) $row->characters;
        }

        $splits = [];
        $order = [];
        foreach ($held as $spec => $byPoints) {
            $counts = array_map(array_sum(...), $byPoints);
            uksort($counts, fn (string $a, string $b): int => [$counts[$b], $a] <=> [$counts[$a], $b]);
            $splits[$spec] = json_decode((string) array_key_first($counts), true, flags: JSON_THROW_ON_ERROR);
            $tree = $trees[$spec];
            uksort($tree, fn (int $a, int $b): int => [$tree[$b], $a] <=> [$tree[$a], $b]);
            $order[$spec] = TalentTabOrder::of($game[$spec], $this->layouts->handle((int) array_key_first($tree)));
        }
        $names = [];
        foreach (DB::table('names')->where('kind', NameKind::TalentTab->value)->whereIn('id', array_keys($tabs))->orderBy('votes')->get(['id', 'name']) as $name) {
            $names[(int) $name->id] = (string) $name->name;
        }

        return ['splits' => $splits, 'tabs' => $names, 'order' => $order];
    }
}
