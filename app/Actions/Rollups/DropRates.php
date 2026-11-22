<?php

namespace App\Actions\Rollups;

use App\Support\ItemBind;
use App\Support\LootArm;
use App\Support\LootCounted;
use App\Support\LootRead;
use App\Support\LootRule;
use App\Support\RateEstimate;
use App\Support\RateFlag;
use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Support\Facades\DB;

class DropRates
{
    private const array NONE = ['keyed' => 0, 'unkeyed' => 0, 'reader' => 0, 'keyed_quantity' => 0, 'unkeyed_quantity' => 0];

    public function __construct(private readonly RollupRead $read) {}

    /**
     * What a source drops under each loot rule: drops over the windows of the source read for loot (first reads only),
     * and the same as an `estimate` (`k` drops of `n` windows with the 95% range). `$sourceKind` is the decoder's source
     * kind: `c` creature, `g` game object, `i` item. A free-for-all rule follows the arm of the source's rule chart
     * (`LootArm`): where the arm counts one reader, `opens` is the arm's windows, `drops` the windows of that set that
     * listed the item, `quantity` the keyed windows' quantity plus the reader's windows' share of the quantity of the
     * windows with no key (the aggregate keeps no quantity for one character), and the estimate carries `one_reader`.
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
        $arm = $this->oneReaderArm($sourceKind, $sourceId, $rows);
        $split = $arm === null ? [] : $this->split($sourceKind, $sourceId, $arm);

        $answer = [];
        foreach ($rows as $row) {
            $quest = (int) $row['bind_type'] === ItemBind::Quest->value;
            if ($arm !== null && $row['loot_rule'] === LootRule::FreeForAll->value) {
                $row = $this->armed($row, $arm, $split[(int) $row['item_id']] ?? null);
                if ($row === null) {
                    continue;
                }
                $answer[] = [...$row, 'estimate' => RateEstimate::of((int) $row['drops'], (int) $row['opens'], [...$arm->flags, ...($quest ? [RateFlag::QuestItem] : [])])];

                continue;
            }
            $estimate = RateEstimate::of((int) $row['drops'], (int) $row['opens'], RateFlag::tags((float) $row['window_characters'], $quest));
            unset($row['window_characters'], $row['bind_type']);
            $answer[] = [...$row, 'estimate' => $estimate];
        }
        if ($arm !== null) {
            usort($answer, fn (array $a, array $b): int => [$a['loot_rule'], -(int) $a['drops'], $a['item_id']] <=> [$b['loot_rule'], -(int) $b['drops'], $b['item_id']]);
        }

        return $answer;
    }

    /**
     * The free-for-all arm of the source when it counts one reader, else null: a source with no free-for-all windows, or
     * whose arm counts every window, has rows no different from the aggregate's.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function oneReaderArm(string $sourceKind, int $sourceId, array $rows): ?LootArm
    {
        if (! in_array(LootRule::FreeForAll->value, array_column($rows, 'loot_rule'), true)) {
            return null;
        }
        $windows = $this->read->rows(LootRead::Loot->within(DB::table(LootCounted::WindowsCharacter->value))
            ->selectRaw('character_id, keyed::int as keyed, sum(windows) as windows')
            ->where('source_kind', $sourceKind)->where('source_id', $sourceId)->where('loot_rule', LootRule::FreeForAll->value)
            ->groupBy('character_id', 'keyed'));
        $unkeyed = $keyed = [];
        foreach ($windows as $row) {
            if ((int) $row['keyed'] !== 1) {
                $unkeyed[(int) $row['character_id']] = (int) $row['windows'];

                continue;
            }
            $keyed[(int) $row['character_id']] = (int) $row['windows'];
        }
        $arm = LootArm::of(LootRule::FreeForAll->value, LootRule::words(LootRule::FreeForAll->value), $unkeyed, $keyed, true, false, false, (int) config('scarlet.min_characters'));

        return $arm !== null && in_array(RateFlag::OneReader, $arm->flags, true) ? $arm : null;
    }

    /**
     * What the free-for-all windows listed, by item: the windows with a key, the windows with no key, and the one
     * reader's windows with no key, with the quantity of the first two.
     *
     * @return array<int, array{keyed: int, unkeyed: int, reader: int, keyed_quantity: int, unkeyed_quantity: int}>
     */
    private function split(string $sourceKind, int $sourceId, LootArm $arm): array
    {
        $rows = $this->read->rows(LootRead::Loot->within(DB::table(LootCounted::Items->value))
            ->selectRaw('item_id, keyed::int as keyed, sum(windows) as drops, sum(quantity) as quantity')
            ->where('source_kind', $sourceKind)->where('source_id', $sourceId)->where('loot_rule', LootRule::FreeForAll->value)
            ->groupBy('item_id', 'keyed'));
        $split = [];
        foreach ($rows as $row) {
            $id = (int) $row['item_id'];
            $listed = $split[$id] ?? self::NONE;
            if ((int) $row['keyed'] !== 1) {
                $split[$id] = [...$listed, 'unkeyed' => (int) $row['drops'], 'unkeyed_quantity' => (int) $row['quantity']];

                continue;
            }
            $split[$id] = [...$listed, 'keyed' => (int) $row['drops'], 'keyed_quantity' => (int) $row['quantity']];
        }
        if ($arm->reader === null) {
            return $split;
        }
        $readers = $this->read->rows(LootRead::Loot->within(DB::table(Rollup::LootFreeForAllItemsUnkeyedDaily->value))
            ->selectRaw('item_id, sum(windows) as windows')
            ->where('source_kind', $sourceKind)->where('source_id', $sourceId)->where('character_id', $arm->reader)
            ->groupBy('item_id'));
        foreach ($readers as $row) {
            $id = (int) $row['item_id'];
            $split[$id] = [...($split[$id] ?? self::NONE), 'reader' => (int) $row['windows']];
        }

        return $split;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{keyed: int, unkeyed: int, reader: int, keyed_quantity: int, unkeyed_quantity: int}|null  $listed
     * @return array<string, mixed>|null the row over the arm's windows, null when none of them listed the item
     */
    private function armed(array $row, LootArm $arm, ?array $listed): ?array
    {
        $listed ??= self::NONE;
        $drops = $arm->drops($listed['keyed'], $listed['unkeyed'], $listed['reader']);
        if ($drops === 0) {
            return null;
        }
        $unkeyedQuantity = 0;
        if ($arm->unkeyedCounted && $listed['unkeyed'] > 0) {
            $unkeyedQuantity = $arm->reader === null ? $listed['unkeyed_quantity'] : (int) round($listed['unkeyed_quantity'] * $listed['reader'] / $listed['unkeyed']);
        }

        return [
            'item_id' => $row['item_id'], 'item' => $row['item'], 'loot_rule' => $row['loot_rule'],
            'drops' => (string) $drops, 'quantity' => (string) (($arm->keyedCounted ? $listed['keyed_quantity'] : 0) + $unkeyedQuantity),
            'opens' => (string) $arm->windows, 'rate' => $drops / max(1, $arm->windows), 'characters' => count($arm->characters),
        ];
    }
}
