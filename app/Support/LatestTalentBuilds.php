<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** The talent builds the talent pages count: the latest build of each character that has a spec. */
final class LatestTalentBuilds
{
    /** One row for each character: `character_id`, `spec_tab_id`, `counted` and `tab_points` of its newest snapshot with a spec. */
    public static function query(PageScope $scope): Builder
    {
        return DB::table('talent_snapshots as s')
            ->selectRaw('distinct on (s.character_id) s.character_id, s.spec_tab_id, s.counted, s.tab_points')
            ->whereNotNull('s.spec_tab_id')
            ->when($scope->ids !== null, fn (Builder $query) => $query->whereIn('s.ruleset_id', (array) $scope->ids))
            ->orderBy('s.character_id')->orderByDesc('s.at');
    }
}
