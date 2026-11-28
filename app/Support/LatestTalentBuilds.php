<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** The talent builds /talents counts, which are the ones the spec page counts for its All bracket: the latest counted build of each character in each spec it has one of. */
final class LatestTalentBuilds
{
    /** One row for each character and spec: `character_id`, `spec_tab_id` and `tab_points` of its newest counted snapshot of that spec read at a level of the All bracket. */
    public static function query(PageScope $scope): Builder
    {
        return DB::table('talent_snapshots as s')
            ->selectRaw('distinct on (s.character_id, s.spec_tab_id) s.character_id, s.spec_tab_id, s.tab_points')
            ->whereNotNull('s.spec_tab_id')->where('s.counted', true)->whereBetween('s.level', LevelBracket::All->range())
            ->when($scope->ids !== null, fn (Builder $query) => $query->whereIn('s.ruleset_id', (array) $scope->ids))
            ->orderBy('s.character_id')->orderBy('s.spec_tab_id')->orderByDesc('s.at');
    }
}
