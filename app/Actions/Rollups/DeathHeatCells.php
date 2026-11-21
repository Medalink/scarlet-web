<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;

class DeathHeatCells
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Where characters died on one zone map (uiMap id), in the same 100 by 100 cells as PathHeatCells.
     *
     *
     * @param  int|list<int>|null  $rulesetId
     * @return list<array<string, mixed>> cell_x, cell_y, deaths, characters
     */
    public function handle(int $map, int|array|null $rulesetId = null): array
    {
        $query = $this->read->from(Rollup::DeathHeatDailyRuleset, $rulesetId)
            ->selectRaw('cell_x, cell_y, sum(deaths) as deaths, distinct_count(rollup(characters)) as characters')
            ->where('map', $map)
            ->groupBy('cell_x', 'cell_y')
            ->orderBy('cell_x')
            ->orderBy('cell_y');

        return $this->read->rows($this->read->shown($query));
    }
}
