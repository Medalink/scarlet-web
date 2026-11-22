<?php

namespace App\Actions\Rollups;

use App\Support\FreeForAllCounts;
use App\Support\LootArm;
use App\Support\LootCounted;
use App\Support\LootDay;
use App\Support\LootRead;
use App\Support\LootRule;
use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FreeForAllArm
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * The free-for-all arm of one source when it counts one reader (`LootArm::overDays`, docs/LOOT.md, Units of counting,
     * rule 6), with what each item listed in the windows the arm counts; null for a source with no free-for-all windows, or
     * whose arm counts every window, which the aggregates already sum. Every table that shows a free-for-all drop rate asks
     * for this arm, so the creature page and the API count the same windows.
     */
    public function handle(string $sourceKind, int $sourceId): ?FreeForAllCounts
    {
        $arm = LootArm::overDays(
            LootRule::FreeForAll->value, LootRule::words(LootRule::FreeForAll->value), $this->windows($sourceKind, $sourceId), true, false, false, (int) config('scarlet.min_characters'),
        );

        return $arm === null || $arm->reader === null ? null : new FreeForAllCounts($arm, $this->items($sourceKind, $sourceId, $arm, $arm->reader));
    }

    /**
     * @return array<string, array{unkeyed: array<int, int>, keyed: array<int, int>}> by day
     */
    private function windows(string $sourceKind, int $sourceId): array
    {
        $rows = $this->read->rows($this->source(LootRead::Loot->within(DB::table(LootCounted::WindowsCharacter->value)), $sourceKind, $sourceId)
            ->selectRaw('character_id, keyed::int as keyed, '.LootDay::of().' as day, sum(windows) as windows')
            ->groupBy('character_id', 'keyed')->groupByRaw(LootDay::of()));
        $days = [];
        foreach ($rows as $row) {
            $days[(string) $row['day']] ??= ['unkeyed' => [], 'keyed' => []];
            $days[(string) $row['day']][(int) $row['keyed'] === 1 ? 'keyed' : 'unkeyed'][(int) $row['character_id']] = (int) $row['windows'];
        }

        return $days;
    }

    /**
     * The windows that listed each item over the days the arm counts, and their quantity. The aggregate keeps no quantity
     * for one character, so the reader's share of the quantity of the windows with no key is its share of the windows with
     * no key that listed the item that day.
     *
     * @return array<int, array{drops: int, quantity: int}> by item, for the items with a drop
     */
    private function items(string $sourceKind, int $sourceId, LootArm $arm, int $reader): array
    {
        $listed = [];
        foreach ($this->read->rows($this->source(LootRead::Loot->within(DB::table(LootCounted::Items->value)), $sourceKind, $sourceId)
            ->selectRaw('item_id, keyed::int as keyed, '.LootDay::of().' as day, sum(windows) as drops, sum(quantity) as quantity')
            ->groupBy('item_id', 'keyed')->groupByRaw(LootDay::of())) as $row) {
            $listed[(int) $row['item_id']][(string) $row['day']][(int) $row['keyed'] === 1 ? 'keyed' : 'unkeyed'] = ['drops' => (int) $row['drops'], 'quantity' => (int) $row['quantity']];
        }
        $readers = [];
        foreach ($this->read->rows($this->source(LootRead::Loot->within(DB::table(Rollup::LootFreeForAllItemsUnkeyedDaily->value)), $sourceKind, $sourceId, false)
            ->selectRaw('item_id, '.LootDay::of().' as day, sum(windows) as windows')
            ->where('character_id', $reader)->groupBy('item_id')->groupByRaw(LootDay::of())) as $row) {
            $readers[(int) $row['item_id']][(string) $row['day']] = (int) $row['windows'];
        }

        $counted = [];
        foreach ($listed as $item => $days) {
            $byDay = [];
            $quantity = 0.0;
            foreach ($days as $day => $parts) {
                $unkeyed = $parts['unkeyed']['drops'] ?? 0;
                $own = $readers[$item][$day] ?? 0;
                $byDay[$day] = ['keyed' => $parts['keyed']['drops'] ?? 0, 'unkeyed' => $unkeyed, 'reader' => $own];
                $quantity += ($arm->countsKeyedOn($day) ? ($parts['keyed']['quantity'] ?? 0) : 0)
                    + ($arm->countsUnkeyedOn($day, $reader) && $unkeyed > 0 ? ($parts['unkeyed']['quantity'] ?? 0) * $own / $unkeyed : 0);
            }
            $drops = $arm->dropsOver($byDay);
            if ($drops > 0) {
                $counted[$item] = ['drops' => $drops, 'quantity' => (int) round($quantity)];
            }
        }

        return $counted;
    }

    private function source(Builder $query, string $sourceKind, int $sourceId, bool $ruled = true): Builder
    {
        $query->where('source_kind', $sourceKind)->where('source_id', $sourceId);

        return $ruled ? $query->where('loot_rule', LootRule::FreeForAll->value) : $query;
    }
}
