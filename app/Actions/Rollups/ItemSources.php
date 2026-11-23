<?php

namespace App\Actions\Rollups;

use App\Support\CountedLoot;
use App\Support\ItemBind;
use App\Support\LootCounted;
use App\Support\LootRead;
use App\Support\LootRule;
use App\Support\LootSourceKind;
use App\Support\RateEstimate;
use App\Support\RateFlag;
use App\Support\RollupRead;
use Illuminate\Support\Facades\DB;

class ItemSources
{
    public function __construct(private readonly RollupRead $read, private readonly CountedLoot $counted, private readonly FreeForAllArms $arms) {}

    /**
     * Where an item drops from, by source, loot rule and `read`, with the drop rate (windows that list the item over
     * the windows of the source with that read and rule, first reads only) and the same as an `estimate` (`k` drops of
     * `n` windows with the 95% range). Windows with no `read` are in no row. A free-for-all row counts the windows of the
     * source's arm (`FreeForAllArms`) and carries its tags, as the creature page does.
     *
     * @return list<array<string, mixed>> source_kind, source_id, source, read, loot_rule, drops, opens, rate, characters, estimate
     */
    public function handle(int $itemId, int $limit = 25): array
    {
        $sources = $this->counted->sourcesOf([$itemId]);
        $opens = LootRead::known(DB::table(LootCounted::Windows->value.' as w'))
            ->selectRaw('source_kind, source_id, read, loot_rule, sum(windows) as opens, distinct_count(rollup(characters)) as window_characters')
            ->groupBy('source_kind', 'source_id', 'read', 'loot_rule');

        $query = LootSourceKind::withName(LootRead::known($this->counted->items('d', $sources, [$itemId]), 'd.read')
            ->joinSub($opens, 'o', fn ($join) => $join
                ->on('o.source_kind', '=', 'd.source_kind')
                ->on('o.source_id', '=', 'd.source_id')
                ->on('o.read', '=', 'd.read')
                ->on('o.loot_rule', '=', 'd.loot_rule'))
            ->select('d.source_kind', 'd.source_id', 'd.read'))
            ->selectRaw('d.loot_rule, sum(d.windows) as drops, o.opens, o.window_characters')
            ->selectRaw('distinct_count(rollup(d.characters)) as characters')
            ->where('d.item_id', $itemId)
            ->groupBy('d.source_kind', 'd.source_id', 'd.read', 'd.loot_rule', 'o.opens', 'o.window_characters')
            ->havingRaw('distinct_count(rollup(d.characters)) >= ?', [(int) config('scarlet.min_characters')])->havingRaw('sum(d.windows) > 0')
            ->orderByRaw('sum(d.windows) desc')
            ->orderBy('d.source_id')
            ->orderBy('d.read')
            ->limit($limit);

        $quest = (int) DB::table('items')->where('item_id', $itemId)->value('bind_type') === ItemBind::Quest->value;

        $free = $this->arms->handle($sources, []);
        $shift = $this->counted->windowShift($sources);

        return array_map(function (array $row) use ($quest, $free, $shift): array {
            $key = $row['source_kind'].$row['source_id'];
            $arm = $row['loot_rule'] === LootRule::FreeForAll->value && $row['read'] === LootRead::Loot->value ? ($free[$key] ?? null) : null;
            $opens = (int) $row['opens'] + ($arm === null ? 0 : ($shift[$key] ?? 0));
            $estimate = RateEstimate::of((int) $row['drops'], $opens, $arm === null
                ? RateFlag::tags((float) $row['window_characters'], $quest)
                : [...$arm->arm->flags, ...($quest ? [RateFlag::QuestItem] : [])]);
            unset($row['window_characters']);

            return [...$row, 'opens' => $opens, 'rate' => $opens === 0 ? 0.0 : (int) $row['drops'] / $opens, 'estimate' => $estimate];
        }, $this->read->rows($query));
    }
}
