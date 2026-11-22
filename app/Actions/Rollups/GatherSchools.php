<?php

namespace App\Actions\Rollups;

use App\Support\CharacterScope;
use App\Support\GatherKind;
use App\Support\Rollup;
use App\Support\RollupRead;

class GatherSchools
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * The looted attempts whose loot window was not the bobber's: a catch from a school.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     */
    public function handle(GatherKind $kind, int|array|CharacterScope|null $scope = null): int
    {
        $query = $this->read->from(Rollup::GathersDailySourceRuleset, $scope)
            ->where('kind', $kind->value)
            ->where('source_id', '<>', GatherKind::BOBBER)
            ->selectRaw('coalesce(sum(looted), 0) as looted');

        return (int) $this->read->value($query, 'looted');
    }
}
