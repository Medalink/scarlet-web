<?php

namespace App\Actions\Public;

use App\Support\LatestTalentBuilds;
use App\Support\NameKind;
use App\Support\PageScope;
use App\Support\TalentRanks;
use Illuminate\Support\Facades\DB;

class TalentSplits
{
    /**
     * The most common split of points over the talent trees of each spec, from the latest counted build of every
     * character the spec list counts, with the names of the trees. A split is the points spent in each tree, and the
     * most common one is the split most characters of the spec hold; among equals the one that sorts first.
     *
     * @return array{splits: array<string, array<int, int>>, tabs: array<int, string>} the splits by `class slug:spec tab id`, as the points in each tab by tab id
     */
    public function handle(PageScope $scope): array
    {
        $rows = DB::query()->fromSub(LatestTalentBuilds::query($scope), 'l')->join('characters as c', 'c.id', '=', 'l.character_id')
            ->where('l.counted', true)->whereNotNull('c.class')
            ->groupBy('c.class', 'l.spec_tab_id', 'l.tab_points')->selectRaw('c.class, l.spec_tab_id, l.tab_points, count(*) as characters')->get();

        $held = [];
        $tabs = [];
        foreach ($rows as $row) {
            $points = [];
            foreach (TalentRanks::tabs((string) $row->tab_points) as $tab) {
                $points[$tab['id']] = $tab['spent'];
                $tabs[$tab['id']] = true;
            }
            ksort($points);
            $held[strtolower((string) $row->class).':'.(int) $row->spec_tab_id][json_encode($points, JSON_THROW_ON_ERROR)][] = (int) $row->characters;
        }

        $splits = [];
        foreach ($held as $spec => $byPoints) {
            $counts = array_map(array_sum(...), $byPoints);
            uksort($counts, fn (string $a, string $b): int => [$counts[$b], $a] <=> [$counts[$a], $b]);
            $splits[$spec] = json_decode((string) array_key_first($counts), true, flags: JSON_THROW_ON_ERROR);
        }
        $names = [];
        foreach (DB::table('names')->where('kind', NameKind::TalentTab->value)->whereIn('id', array_keys($tabs))->orderBy('votes')->get(['id', 'name']) as $name) {
            $names[(int) $name->id] = (string) $name->name;
        }

        return ['splits' => $splits, 'tabs' => $names];
    }
}
