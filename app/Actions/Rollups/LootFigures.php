<?php

namespace App\Actions\Rollups;

use App\Support\CountedLoot;
use App\Support\ItemQuality;
use App\Support\LootCounted;
use App\Support\LootListing;
use App\Support\LootRead;
use App\Support\LootRule;
use App\Support\LootSourceKind;
use App\Support\RollupRead;
use Illuminate\Database\Query\Builder;

class LootFigures
{
    public function __construct(private readonly RollupRead $read, private readonly CountedLoot $counted) {}

    /**
     * What the loot index counts, over every ruleset and every loot rule but personal loot, narrowed to `$zone` when one is
     * given: creature windows read for loot (corpses looted) and for skinning, the creatures, objects and containers that
     * have a window, the distinct items they gave, the items of uncommon quality or better among them, the characters, the
     * first and last day. Windows read for fishing and the bobber are in none of them; `fishing` counts them apart.
     * Every count is 0 and `first_at` is null when no window is shown. The free-for-all windows of a source count as its
     * arm counts them (`FreeForAllArms`), as its creature page does.
     *
     * @return array{corpses: int, skinned: int, sources: int, items: int, uncommon: int, characters: int, first_at: string|null, last_at: string|null, fishing: int}
     */
    public function handle(?int $zone): array
    {
        $windows = $this->read->rows($this->read->shown(LootRule::within(LootListing::ofSources($this->read->table(LootCounted::Windows->value, null)), 'loot_rule', null)
            ->when($zone !== null, fn (Builder $query): Builder => $query->where('zone_id', $zone))
            ->selectRaw('sum(windows) filter (where source_kind = ? and read = ?) as corpses, sum(windows) filter (where source_kind = ? and read = ?) as skinned', [LootSourceKind::Creature->value, LootRead::Loot->value, LootSourceKind::Creature->value, LootRead::Skin->value])
            ->selectRaw('count(distinct (source_kind, source_id)) as sources, distinct_count(rollup(characters)) as characters, min(bucket) as first_at, max(bucket) as last_at')))[0] ?? null;
        $items = $this->read->rows($this->read->shown(LootRule::within(LootListing::ofSources($this->read->table(LootCounted::Items->value.' as d', null), 'd.read', 'd.source_kind', 'd.source_id'), 'd.loot_rule', null)
            ->leftJoin('items as i', 'i.item_id', '=', 'd.item_id')
            ->when($zone !== null, fn (Builder $query): Builder => $query->where('d.zone_id', $zone))
            ->selectRaw('count(distinct d.item_id) as items, count(distinct d.item_id) filter (where i.quality >= ?) as uncommon', [ItemQuality::Uncommon->value])))[0] ?? null;

        $shift = $windows === null ? 0 : array_sum(array_filter($this->counted->windowShift(null, 'zone', LootSourceKind::Creature), fn (string $key): bool => $zone === null || $key === (string) $zone, ARRAY_FILTER_USE_KEY));

        return [
            'corpses' => (int) ($windows['corpses'] ?? 0) + $shift, 'skinned' => (int) ($windows['skinned'] ?? 0), 'sources' => (int) ($windows['sources'] ?? 0),
            'items' => (int) ($items['items'] ?? 0), 'uncommon' => (int) ($items['uncommon'] ?? 0),
            'characters' => (int) round((float) ($windows['characters'] ?? 0)),
            'first_at' => $windows['first_at'] ?? null, 'last_at' => $windows['last_at'] ?? null,
            'fishing' => $this->fishing(),
        ];
    }

    private function fishing(): int
    {
        $row = $this->read->rows($this->read->shown(LootRule::within(LootRead::Fish->within($this->read->table(LootCounted::Windows->value, null)), 'loot_rule', null)
            ->selectRaw('sum(windows) as windows')))[0] ?? null;

        return (int) ($row['windows'] ?? 0);
    }
}
