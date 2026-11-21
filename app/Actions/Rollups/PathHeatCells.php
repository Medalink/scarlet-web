<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;

class PathHeatCells
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Where characters walked on one zone map (uiMap id): 100 by 100 cells, `cell_x` and `cell_y` from 0 to 100.
     *
     * @return list<array<string, mixed>> cell_x, cell_y, points, characters
     */
    public function handle(int $map, ?int $rulesetId = null): array
    {
        $query = $this->read->from(Rollup::HeatDailyRuleset, $rulesetId)
            ->selectRaw('cell_x, cell_y, sum(points) as points, distinct_count(rollup(characters)) as characters')
            ->where('map', $map)
            ->groupBy('cell_x', 'cell_y')
            ->orderBy('cell_x')
            ->orderBy('cell_y');

        return $this->read->rows($this->read->shown($query));
    }
}
