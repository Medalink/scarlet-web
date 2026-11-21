<?php

namespace App\Actions\Public;

use App\Actions\Rollups\WorldHeat;
use App\Support\HeatLevels;
use App\Support\Server;
use App\Support\WorldLayer;

class WorldHeatCells
{
    public function __construct(private readonly ResolveScope $resolveScope, private readonly WorldHeat $heat) {}

    /**
     * The cells of one layer with their level 1 to 4, for the map viewer and `/public/map/heat`. Null when the
     * ruleset is not known.
     *
     * @return list<array{i: int, j: int, level: int, value: int|float, characters: int}>|null
     */
    public function handle(?string $ruleset, WorldLayer $layer, ?int $npcId = null): ?array
    {
        $scope = $this->resolveScope->handle($ruleset, Server::All);
        if ($scope === null) {
            return null;
        }
        $rows = $this->heat->handle($layer, $scope->ids, $npcId);
        $levels = HeatLevels::assign($rows);

        return array_map(fn (array $row, array $cell): array => [
            'i' => $cell[0], 'j' => $cell[1], 'level' => $cell[2],
            'value' => $row['value'] + 0, 'characters' => (int) $row['characters'],
        ], $rows, $levels);
    }
}
