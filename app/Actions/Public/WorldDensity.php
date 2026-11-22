<?php

namespace App\Actions\Public;

use App\Actions\Rollups\WorldHeat;
use App\Support\HeatGrid;
use App\Support\Server;
use App\Support\WorldLayer;

class WorldDensity
{
    /** Cells read at most from one level. */
    private const int READ = 150000;

    public function __construct(private readonly ResolveScope $resolveScope, private readonly WorldHeat $heat) {}

    /**
     * One whole level of a layer for the map viewer and `/public/map/density`: `cells` is a flat list of pairs, the
     * index of the cell (`j * columns + i`) minus the previous cell's, then its counter, in index order. Null when the
     * ruleset is not known.
     *
     * @return array{level: int, columns: int, rows: int, cells: list<int|float>}|null
     */
    public function handle(?string $ruleset, WorldLayer $layer, int $level, ?int $npcId = null): ?array
    {
        $scope = $this->resolveScope->handle($ruleset, Server::All);
        if ($scope === null) {
            return null;
        }
        $level = min($level, $layer->levels() - 1);
        $columns = HeatGrid::columns($level);
        $indexed = [];
        foreach ($this->heat->handle($layer, $scope->ids, $npcId, self::READ, $level) as $row) {
            $indexed[(int) $row['cell_j'] * $columns + (int) $row['cell_i']] = $row['value'] + 0;
        }
        ksort($indexed);
        $cells = [];
        $previous = 0;
        foreach ($indexed as $index => $value) {
            $cells[] = $index - $previous;
            $cells[] = $value;
            $previous = $index;
        }

        return ['level' => $level, 'columns' => $columns, 'rows' => HeatGrid::rows($level), 'cells' => $cells];
    }
}
