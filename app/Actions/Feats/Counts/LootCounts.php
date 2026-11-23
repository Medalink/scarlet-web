<?php

namespace App\Actions\Feats\Counts;

use App\Actions\Public\Character\ItemsFound;
use App\Actions\Public\Character\Records;
use App\Models\Character;
use App\Support\Feat;
use App\Support\GameObjects;
use App\Support\ItemQuality;
use App\Support\LootSourceKind;
use App\Support\LootVia;
use Illuminate\Support\Facades\DB;

/** The loot feats: corpses and containers opened, items received, fishing and gathering. */
class LootCounts
{
    public function __construct(
        private readonly Records $records,
        private readonly ItemsFound $itemsFound,
    ) {}

    /**
     * One count per loot feat, keyed by the enum value; null when the feat does not apply to the character.
     *
     * @return array<string, int|float|null>
     */
    public function handle(Character $character): array
    {
        $id = (int) $character->id;
        $opens = DB::table('loot_opens')->where('character_id', $id)->where('counted', true)
            ->selectRaw('count(*) filter (where source_kind = ?) as looter', [LootSourceKind::Creature->value])
            ->selectRaw('count(*) filter (where source_kind = ?) as unboxer', [LootSourceKind::Bag->value])
            ->selectRaw('count(distinct zone_id) filter (where source_kind = ? and source_id = ?) as wading', [LootSourceKind::Object->value, GameObjects::FISHING_BOBBER])
            ->selectRaw('count(distinct zone_id) filter (where source_kind = ? and source_id is distinct from ?) as forager', [LootSourceKind::Object->value, GameObjects::FISHING_BOBBER])->first();
        $drops = DB::table('loot_drops')->where('character_id', $id)->where('counted', true)
            ->selectRaw('count(distinct item_id) as curator')
            ->selectRaw('count(distinct item_id) filter (where via = ? and source_kind = ? and source_id = ?) as fishmonger', [LootVia::Window->value, LootSourceKind::Object->value, GameObjects::FISHING_BOBBER])->first();
        $records = $this->records->handle($id);
        $qualities = $this->itemsFound->handle($id)['qualities'];

        return [
            Feat::Looter->value => (int) ($opens->looter ?? 0),
            Feat::Scavenger->value => (int) ($qualities[ItemQuality::Poor->value] ?? 0),
            Feat::Lucky->value => (int) ($qualities[ItemQuality::Uncommon->value] ?? 0),
            Feat::Collector->value => (int) ($qualities[ItemQuality::Rare->value] ?? 0),
            Feat::Jackpot->value => (int) ($qualities[ItemQuality::Epic->value] ?? 0),
            Feat::Curator->value => (int) ($drops->curator ?? 0),
            Feat::Unboxer->value => (int) ($opens->unboxer ?? 0),
            Feat::Angler->value => (int) $records['fish'],
            Feat::Fishmonger->value => (int) ($drops->fishmonger ?? 0),
            Feat::Wading->value => (int) ($opens->wading ?? 0),
            Feat::Gatherer->value => (int) $records['nodes'],
            Feat::Forager->value => (int) ($opens->forager ?? 0),
        ];
    }
}
