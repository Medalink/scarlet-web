<?php

namespace App\Actions\Rollups;

use App\Support\CharacterScope;
use App\Support\GatherKind;
use App\Support\Rollup;
use App\Support\RollupRead;

class GatherWaits
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Looted attempts by how long they took: `step` is the seconds in steps of two, at most 15 (30 seconds and over).
     * Only steps with attempts and enough characters are listed.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     * @return list<array{step: int, gathers: int}>
     */
    public function handle(GatherKind $kind, int|array|CharacterScope|null $scope = null): array
    {
        $query = $this->read->from(Rollup::GathersDailyShapeRuleset, $scope)
            ->where('kind', $kind->value)
            ->whereNotNull('wait_step')
            ->selectRaw('wait_step as step, sum(looted) as gathers')
            ->groupBy('wait_step')
            ->havingRaw('sum(looted) > 0')
            ->orderBy('wait_step');

        return array_map(
            fn (array $row): array => ['step' => (int) $row['step'], 'gathers' => (int) $row['gathers']],
            $this->read->rows($this->read->shown($query, $scope)),
        );
    }
}
