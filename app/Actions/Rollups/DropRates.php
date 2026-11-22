<?php

namespace App\Actions\Rollups;

use App\Support\FreeForAllCounts;
use App\Support\ItemBind;
use App\Support\LootCounted;
use App\Support\LootRead;
use App\Support\LootRule;
use App\Support\RateEstimate;
use App\Support\RateFlag;
use App\Support\RollupRead;
use Illuminate\Support\Facades\DB;

class DropRates
{
    public function __construct(private readonly RollupRead $read, private readonly FreeForAllArm $free) {}

    /**
     * What a source drops under each loot rule: drops over the windows of the source read for loot (first reads only),
     * and the same as an `estimate` (`k` drops of `n` windows with the 95% range). `$sourceKind` is the decoder's source
     * kind: `c` creature, `g` game object, `i` item. A free-for-all rule follows the arm of the source's rule chart
     * (`FreeForAllArm`): where the arm counts one reader, `opens` is the arm's windows, `drops` the windows of that set that
     * listed the item, `quantity` the quantity of the windows with a key plus the reader's windows' share of the quantity
     * of the windows with no key (the aggregate keeps no quantity for one character), and the estimate carries `one_reader`.
     * The aggregates carry no ruleset, so a drop rate is the same for every ruleset.
     *
     * @return list<array<string, mixed>> item_id, item, loot_rule, drops, quantity, opens, rate, characters, estimate
     */
    public function handle(string $sourceKind, int $sourceId): array
    {
        $opens = LootRead::Loot->within(DB::table(LootCounted::Windows->value))
            ->selectRaw('loot_rule, sum(windows) as opens, distinct_count(rollup(characters)) as window_characters')
            ->where('source_kind', $sourceKind)
            ->where('source_id', $sourceId)
            ->groupBy('loot_rule');

        $query = LootRead::Loot->within(DB::table(LootCounted::Items->value.' as d'), 'd.read')
            ->joinSub($opens, 'o', 'o.loot_rule', '=', 'd.loot_rule')
            ->leftJoin('items as i', 'i.item_id', '=', 'd.item_id')
            ->selectRaw("d.item_id, (select n.name from names n where n.kind = 'item' and n.id = d.item_id order by n.votes desc, n.name limit 1) as item")
            ->selectRaw('d.loot_rule, sum(d.windows) as drops, sum(d.quantity) as quantity, o.opens, sum(d.windows)::float / o.opens as rate')
            ->selectRaw('distinct_count(rollup(d.characters)) as characters, o.window_characters, i.bind_type')
            ->where('d.source_kind', $sourceKind)
            ->where('d.source_id', $sourceId)
            ->groupBy('d.item_id', 'd.loot_rule', 'o.opens', 'o.window_characters', 'i.bind_type')
            ->havingRaw('distinct_count(rollup(d.characters)) >= ?', [(int) config('scarlet.min_characters')])
            ->orderBy('d.loot_rule')
            ->orderByRaw('sum(d.windows) desc')
            ->orderBy('d.item_id');

        $rows = $this->read->rows($query);
        $free = in_array(LootRule::FreeForAll->value, array_column($rows, 'loot_rule'), true) ? $this->free->handle($sourceKind, $sourceId) : null;

        $answer = [];
        foreach ($rows as $row) {
            $quest = (int) $row['bind_type'] === ItemBind::Quest->value;
            if ($free !== null && $row['loot_rule'] === LootRule::FreeForAll->value) {
                $row = $this->armed($row, $free);
                if ($row === null) {
                    continue;
                }
                $answer[] = [...$row, 'estimate' => RateEstimate::of((int) $row['drops'], (int) $row['opens'], [...$free->arm->flags, ...($quest ? [RateFlag::QuestItem] : [])])];

                continue;
            }
            $estimate = RateEstimate::of((int) $row['drops'], (int) $row['opens'], RateFlag::tags((float) $row['window_characters'], $quest));
            unset($row['window_characters'], $row['bind_type']);
            $answer[] = [...$row, 'estimate' => $estimate];
        }
        if ($free !== null) {
            usort($answer, fn (array $a, array $b): int => [$a['loot_rule'], -(int) $a['drops'], $a['item_id']] <=> [$b['loot_rule'], -(int) $b['drops'], $b['item_id']]);
        }

        return $answer;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null the row over the arm's windows, null when none of them listed the item
     */
    private function armed(array $row, FreeForAllCounts $free): ?array
    {
        $counted = $free->items[(int) $row['item_id']] ?? null;
        if ($counted === null) {
            return null;
        }

        return [
            'item_id' => $row['item_id'], 'item' => $row['item'], 'loot_rule' => $row['loot_rule'],
            'drops' => (string) $counted['drops'], 'quantity' => (string) $counted['quantity'],
            'opens' => (string) $free->arm->windows, 'rate' => $counted['drops'] / max(1, $free->arm->windows), 'characters' => count($free->arm->characters),
        ];
    }
}
