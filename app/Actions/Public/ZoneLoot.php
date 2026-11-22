<?php

namespace App\Actions\Public;

use App\Actions\Rollups\LootItems;
use App\Actions\Rollups\LootSources;
use App\Support\LootFilter;
use App\Support\LootView;

class ZoneLoot
{
    /** The creatures and the items the Looted here module of a zone page lists. */
    public const int TOP = 5;

    public function __construct(
        private readonly LootSources $sources,
        private readonly LootItems $items,
    ) {}

    /**
     * The creatures looted most often in a zone and the items that dropped most often in it, as the loot index reads them
     * with the zone as its filter: every ruleset and every loot rule but personal loot, fishing out, sources under
     * `scarlet.min_characters` characters left out.
     *
     * @return array{sources: list<array<string, mixed>>, items: list<array<string, mixed>>}
     */
    public function handle(int $zone): array
    {
        return [
            'sources' => $this->sources->handle(new LootFilter(LootView::Creatures, $zone), 0, self::TOP)['rows'],
            'items' => $this->items->handle(new LootFilter(LootView::Items, $zone), 0, self::TOP)['rows'],
        ];
    }
}
