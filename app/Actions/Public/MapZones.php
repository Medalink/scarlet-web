<?php

namespace App\Actions\Public;

use App\Actions\Rollups\BusiestZones;
use App\Models\MapRect;
use App\Support\CharacterScope;

class MapZones
{
    public function __construct(private readonly BusiestZones $busiest) {}

    /**
     * The zone rectangles of the world with what was recorded in each, for the map's readout and its zone names.
     * A zone with no recorded play has no `seconds`.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     * @return list<array{id: int, name: string, u0: float, v0: float, u1: float, v1: float, seconds: float|null, deaths: int|null, characters: int|null}>
     */
    public function handle(int|array|CharacterScope|null $scope = null): array
    {
        $stats = [];
        foreach ($this->busiest->handle($scope, 500) as $row) {
            $stats[(int) $row['zone_id']] = $row;
        }

        return array_values(MapRect::query()->orderBy('ui_map_id')->get()->map(fn (MapRect $rect): array => [
            'id' => $rect->ui_map_id,
            'name' => $rect->name,
            'u0' => $rect->u0,
            'v0' => $rect->v0,
            'u1' => $rect->u1,
            'v1' => $rect->v1,
            'seconds' => isset($stats[$rect->ui_map_id]) ? (float) $stats[$rect->ui_map_id]['seconds'] : null,
            'deaths' => isset($stats[$rect->ui_map_id]) ? (int) $stats[$rect->ui_map_id]['deaths'] : null,
            'characters' => isset($stats[$rect->ui_map_id]) ? (int) $stats[$rect->ui_map_id]['characters'] : null,
        ])->all());
    }
}
