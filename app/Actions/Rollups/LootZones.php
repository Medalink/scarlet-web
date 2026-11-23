<?php

namespace App\Actions\Rollups;

use App\Support\CountedLoot;
use App\Support\LootCounted;
use App\Support\LootListing;
use App\Support\LootRule;
use App\Support\RollupRead;

class LootZones
{
    public function __construct(private readonly RollupRead $read, private readonly CountedLoot $counted) {}

    /**
     * The zones that have loot windows (first reads of every source read for loot, skinning, herbs, ore or a container,
     * every loot rule but personal loot), the busiest first. Zones under `scarlet.min_characters` characters are left out.
     * The free-for-all windows of a source count as its arm counts them (`FreeForAllArms`).
     *
     * @return list<array{id: int, name: string|null, windows: int}>
     */
    public function handle(int $limit = 60): array
    {
        $rows = $this->read->rows($this->read->shown(LootRule::within(LootListing::ofSources($this->read->table(LootCounted::Windows->value, null)), 'loot_rule', null)
            ->selectRaw('zone_id, (select z.name from zones z where z.id = zone_id) as zone, sum(windows) as windows')
            ->whereNotNull('zone_id')->groupBy('zone_id')));
        $shift = $this->counted->windowShift(null, 'zone');
        $zones = array_map(fn (array $row): array => ['id' => (int) $row['zone_id'], 'name' => $row['zone'], 'windows' => (int) $row['windows'] + ($shift[$row['zone_id']] ?? 0)], $rows);
        usort($zones, fn (array $a, array $b): int => [$b['windows'], $a['id']] <=> [$a['windows'], $b['id']]);

        return array_slice($zones, 0, $limit);
    }
}
