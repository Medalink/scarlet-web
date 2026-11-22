<?php

namespace App\Actions\Rollups;

use App\Support\CharacterScope;
use App\Support\Rollup;
use App\Support\RollupRead;

class CampHeat
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * The zone map with the most visits to a campfire, and its 100 by 100 cells (`cell_x`, `cell_y` from 0 to 100) with
     * the visits that began in each. A cell fewer than the scope's threshold of characters stayed in is left
     * out. Null when no map has a cell to show.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     * @return array{map: int, name: string|null, cells: list<array{cell_x: int, cell_y: int, visits: int}>}|null
     */
    public function handle(int|array|CharacterScope|null $scope = null): ?array
    {
        $top = $this->read->from(Rollup::CampHeatDailyRuleset, $scope)
            ->selectRaw('map, (select z.name from zones z where z.id = map) as name')
            ->groupBy('map')
            ->orderByRaw('sum(visits) desc')
            ->orderBy('map')
            ->limit(1);
        $map = $this->read->rows($this->read->shown($top, $scope))[0] ?? null;
        if ($map === null) {
            return null;
        }

        $cells = $this->read->from(Rollup::CampHeatDailyRuleset, $scope)
            ->selectRaw('cell_x, cell_y, sum(visits) as visits')
            ->where('map', $map['map'])
            ->groupBy('cell_x', 'cell_y')
            ->orderBy('cell_x')
            ->orderBy('cell_y');

        return [
            'map' => (int) $map['map'],
            'name' => $map['name'] === null ? null : (string) $map['name'],
            'cells' => array_map(
                fn (array $row): array => ['cell_x' => (int) $row['cell_x'], 'cell_y' => (int) $row['cell_y'], 'visits' => (int) $row['visits']],
                $this->read->rows($this->read->shown($cells, $scope)),
            ),
        ];
    }
}
