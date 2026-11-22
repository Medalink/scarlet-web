<?php

namespace App\Actions\Public;

use App\Actions\Rollups\LootFigures;
use App\Actions\Rollups\LootItems;
use App\Actions\Rollups\LootRuleSizes;
use App\Actions\Rollups\LootSources;
use App\Actions\Rollups\LootZones;
use App\Support\LootFilter;
use App\Support\LootView;

class LootIndex
{
    /** The rows of one page of the table. */
    public const int PAGE = 25;

    public function __construct(
        private readonly LootFigures $figures,
        private readonly LootSources $sources,
        private readonly LootItems $items,
        private readonly LootZones $zones,
        private readonly LootRuleSizes $rules,
    ) {}

    /**
     * Everything `/loot` shows: the figures (over the filter's zone), one page of its table, the zones that have loot and
     * what was recorded for fishing, which no table counts (docs/LOOT.md, Loot index). Every ruleset together, and
     * personal loot out.
     *
     * @return array<string, mixed>
     */
    public function handle(LootFilter $filter, int $offset): array
    {
        return [
            'filter' => $filter,
            'offset' => $offset,
            'figures' => $this->figures->handle($filter->zone),
            'zones' => $this->zones->handle(),
            'rules' => $this->rules->handle(),
            'table' => $filter->view === LootView::Items ? $this->items->handle($filter, $offset, self::PAGE) : $this->sources->handle($filter, $offset, self::PAGE),
        ];
    }
}
