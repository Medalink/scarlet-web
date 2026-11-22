<?php

namespace App\Actions\Rollups;

use App\Http\Middleware\PublicApi;
use App\Support\HeatGrid;
use App\Support\RollupRead;
use App\Support\WorldLayer;
use Illuminate\Support\Facades\Cache;

class WorldHeat
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * What a layer counted in each cell of one level of the world grid (docs/DESIGN.md, Maps), the busiest cells first. A
     * cell that fewer than `config('scarlet.min_characters')` characters contributed to is left out. `$npcId` narrows the
     * creatures layer to one creature. `$level` is a level of `HeatGrid`; the default is the 768 by 512 grid.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return list<array<string, mixed>> cell_i, cell_j, value, characters
     */
    public function handle(WorldLayer $layer, int|array|null $rulesetId = null, ?int $npcId = null, int $limit = 30000, int $level = HeatGrid::LEGACY_LEVEL): array
    {
        $level = min($level, $layer->levels() - 1);
        $key = 'world-heat:'.md5(json_encode([$layer->value, $rulesetId, $npcId, $limit, $level], JSON_THROW_ON_ERROR));

        /** @var list<array<string, mixed>> */
        return Cache::remember($key, PublicApi::MAX_AGE, function () use ($layer, $rulesetId, $npcId, $limit, $level): array {
            $query = $this->read->from($layer->rollup(), $rulesetId)
                ->selectRaw($layer->cellSql($level))
                ->when($npcId !== null, fn ($q) => $q->where('npc_id', $npcId))
                ->groupByRaw($layer->cellGroup($level))
                ->orderByDesc('value')
                ->orderBy('cell_i')
                ->orderBy('cell_j')
                ->limit($limit);

            return $this->read->rows($this->read->shown($query));
        });
    }
}
