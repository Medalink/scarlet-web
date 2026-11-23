<?php

namespace App\Actions\Rollups;

use App\Support\CountedLoot;
use App\Support\LootRead;
use App\Support\LootRule;
use App\Support\LootSourceKind;
use App\Support\RateEstimate;
use App\Support\RollupRead;
use Illuminate\Database\Query\Builder;

class ItemZones
{
    public function __construct(private readonly RollupRead $read, private readonly CountedLoot $counted) {}

    /**
     * The zones where creatures held an item: the corpses that held it (first reads of creature loot windows, every
     * loot rule but personal loot) and, as one rate, how many of the zone's creature corpses looted for the first time
     * held it. The zones with most corpses first, at most `$limit`. A zone under `scarlet.min_characters` characters is
     * left out. Gathering and fishing are not counted. The free-for-all windows of a creature are those of its arm
     * (`FreeForAllArms`), shared out over its zones by what each zone holds (`FreeForAllDeltas`).
     *
     * @return list<array{id: int, name: string|null, dropped: int, estimate: RateEstimate}>
     */
    public function handle(int $itemId, int $limit = 10): array
    {
        $held = $this->read->rows($this->creatures($this->counted->items('d', $this->counted->sourcesOf([$itemId]), [$itemId]))
            ->selectRaw('zone_id, (select z.name from zones z where z.id = zone_id) as zone, sum(windows) as dropped')
            ->where('item_id', $itemId)->whereNotNull('zone_id')->groupBy('zone_id')
            ->havingRaw('distinct_count(rollup(characters)) >= ?', [(int) config('scarlet.min_characters')])
            ->orderByRaw('sum(windows) desc')->orderBy('zone_id')->limit($limit));
        if ($held === []) {
            return [];
        }
        $zones = array_map('intval', array_column($held, 'zone_id'));
        $looted = array_column($this->read->rows($this->creatures($this->counted->windows('w', $this->counted->sourcesIn($zones, LootSourceKind::Creature)))
            ->selectRaw('zone_id, sum(windows) as windows')
            ->whereIn('zone_id', $zones)->groupBy('zone_id')), 'windows', 'zone_id');

        return array_map(fn (array $row): array => [
            'id' => (int) $row['zone_id'], 'name' => $row['zone'], 'dropped' => (int) $row['dropped'],
            'estimate' => RateEstimate::of((int) $row['dropped'], (int) ($looted[$row['zone_id']] ?? 0)),
        ], $held);
    }

    /** The windows over creature loot, every loot rule but personal loot. */
    private function creatures(Builder $query): Builder
    {
        return LootRule::within(LootRead::Loot->within($query->where('source_kind', LootSourceKind::Creature->value)), 'loot_rule', null);
    }
}
