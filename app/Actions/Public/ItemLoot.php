<?php

namespace App\Actions\Public;

use App\Actions\Rollups\ItemZones;
use App\Actions\Rollups\SourceDrops;
use App\Support\LootRead;
use App\Support\LootSourceKind;

class ItemLoot
{
    public function __construct(
        private readonly ItemZones $zones,
        private readonly ItemPoints $points,
        private readonly SourceDrops $drops,
        private readonly MapZones $mapZones,
        private readonly PriceLootTable $price,
    ) {}

    /**
     * The loot modules of an item page beside its drop table: the zones creatures held it in, where it was looted and,
     * for an item that is a container, what it holds (its drop table over the windows read as a bag, null when none was).
     * Every ruleset together, and personal loot out.
     *
     * @return array{zones: list<array<string, mixed>>, points: list<array{0: float, 1: float}>|null, map_zones: list<array<string, mixed>>, holds: array<string, mixed>|null}
     */
    public function handle(int $id): array
    {
        $holds = $this->drops->handle(LootSourceKind::Bag->value, $id, null, LootRead::Bag);

        return [
            'zones' => $this->zones->handle($id),
            'points' => $this->points->handle($id),
            'map_zones' => $this->mapZones->handle(),
            'holds' => $holds['windows'] === 0 ? null : $this->price->handle($holds),
        ];
    }
}
