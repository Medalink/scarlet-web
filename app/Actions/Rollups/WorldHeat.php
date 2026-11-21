<?php

namespace App\Actions\Rollups;

use App\Support\RollupRead;
use App\Support\WorldLayer;

class WorldHeat
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * What a layer counted in each cell of the world grid (docs/DESIGN.md, Maps), the busiest cells first. A cell
     * that fewer than `config('scarlet.min_characters')` characters contributed to is left out. `$npcId` narrows
     * the creatures layer to one creature.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return list<array<string, mixed>> cell_i, cell_j, value, characters
     */
    public function handle(WorldLayer $layer, int|array|null $rulesetId = null, ?int $npcId = null, int $limit = 30000): array
    {
        $query = $this->read->from($layer->rollup(), $rulesetId)
            ->selectRaw($layer->cellSql())
            ->when($npcId !== null, fn ($q) => $q->where('npc_id', $npcId))
            ->groupBy('cell_i', 'cell_j')
            ->orderByDesc('value')
            ->orderBy('cell_i')
            ->orderBy('cell_j')
            ->limit($limit);

        return $this->read->rows($this->read->shown($query));
    }
}
