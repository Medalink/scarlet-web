<?php

namespace App\Actions\Rollups;

use App\Support\Apportion;
use App\Support\FreeForAllCounts;
use App\Support\LootCounted;
use App\Support\LootRead;
use App\Support\LootRule;
use App\Support\LootSourceKind;
use App\Support\LootSourceRef;
use App\Support\RollupRead;
use Illuminate\Support\Facades\DB;

class FreeForAllDeltas
{
    public function __construct(private readonly RollupRead $read, private readonly FreeForAllArms $arms) {}

    /**
     * What to add to the counted views for the sources whose free-for-all arm counts one reader (`FreeForAllArms`), as rows
     * of the views' own shape, so a table that groups them by zone, group size, source or item shows the arm's windows and
     * drops (docs/LOOT.md, Units of counting, rule 6). A source's arm has one total, and the views split a total by zone and
     * group size, so the arm's windows (and, for an item, its drops and quantity) are shared out over the rows of the
     * source's free-for-all windows in proportion to what each row holds, the larger remainders taking what is left, so the
     * zones and sizes of a source add up to the arm. Each row is the difference to the view's row, and is dated the last
     * day of the rows it adjusts. `$itemIds` null adjusts every item, a list those items, an empty list none.
     *
     * @param  list<LootSourceRef>|null  $sources
     * @param  list<int>|null  $itemIds
     * @return array{windows: list<array{string, string, int, int|null, int|null, int}>, items: list<array{string, string, int, int|null, int|null, int, int, int}>}
     */
    public function handle(?array $sources = null, ?array $itemIds = null): array
    {
        $counts = $this->arms->handle($sources, $itemIds);
        if ($counts === []) {
            return ['windows' => [], 'items' => []];
        }
        $refs = array_map(fn (string $key): LootSourceRef => new LootSourceRef(LootSourceKind::from(substr($key, 0, 1)), (int) substr($key, 1)), array_keys($counts));

        /** @var array{windows: list<array{string, string, int, int|null, int|null, int}>, items: list<array{string, string, int, int|null, int|null, int, int, int}>} */
        return $this->read->kept('free-for-all-deltas', [$sources === null ? null : array_keys($counts), $itemIds], function () use ($counts, $refs, $itemIds): array {
            $kept = $this->keptWindows($counts, $refs);

            return ['windows' => $this->windows($kept), 'items' => $itemIds === [] ? [] : $this->items($counts, $refs, $itemIds, $kept)];
        });
    }

    /**
     * The arm's windows of each source shared over its zone and size rows, with the view's windows of each row.
     *
     * @param  array<string, FreeForAllCounts>  $counts
     * @param  list<LootSourceRef>  $refs
     * @return array<string, array<string, array{row: array<string, mixed>, windows: int, kept: int}>> by source key, then zone and size
     */
    private function keptWindows(array $counts, array $refs): array
    {
        $rows = $this->read->rows(LootSourceRef::anyOf(LootRead::Loot->within(DB::table(LootCounted::Windows->value))->where('loot_rule', LootRule::FreeForAll->value)
            ->selectRaw('source_kind, source_id, zone_id, group_size, sum(windows) as windows, max(bucket) as bucket')
            ->groupBy('source_kind', 'source_id', 'zone_id', 'group_size'), $refs));
        $by = [];
        foreach ($rows as $row) {
            $by[$row['source_kind'].$row['source_id']][] = $row;
        }

        $answer = [];
        foreach ($by as $key => $own) {
            $kept = Apportion::split(array_map(fn (array $row): int => (int) $row['windows'], $own), $counts[$key]->arm->windows);
            foreach ($own as $position => $row) {
                $answer[$key][$this->place($row)] = ['row' => $row, 'windows' => (int) $row['windows'], 'kept' => $kept[$position]];
            }
        }

        return $answer;
    }

    /**
     * @param  array<string, array<string, array{row: array<string, mixed>, windows: int, kept: int}>>  $kept
     * @return list<array{string, string, int, int|null, int|null, int}>
     */
    private function windows(array $kept): array
    {
        $deltas = [];
        foreach ($kept as $places) {
            foreach ($places as $place) {
                $row = $place['row'];
                $delta = $place['kept'] - $place['windows'];
                if ($delta !== 0) {
                    $deltas[] = [(string) $row['bucket'], (string) $row['source_kind'], (int) $row['source_id'], $this->nullable($row['zone_id']), $this->nullable($row['group_size']), $delta];
                }
            }
        }

        return $deltas;
    }

    /** @param  array<string, mixed>  $row */
    private function place(array $row): string
    {
        return ($row['zone_id'] ?? '').'|'.($row['group_size'] ?? '');
    }

    /**
     * @param  array<string, FreeForAllCounts>  $counts
     * @param  list<LootSourceRef>  $refs
     * @param  list<int>|null  $itemIds
     * @param  array<string, array<string, array{row: array<string, mixed>, windows: int, kept: int}>>  $kept
     * @return list<array{string, string, int, int|null, int|null, int, int, int}>
     */
    private function items(array $counts, array $refs, ?array $itemIds, array $kept): array
    {
        $query = LootSourceRef::anyOf(LootRead::Loot->within(DB::table(LootCounted::Items->value))->where('loot_rule', LootRule::FreeForAll->value)
            ->selectRaw('source_kind, source_id, item_id, zone_id, group_size, sum(windows) as drops, sum(quantity) as quantity, max(bucket) as bucket')
            ->groupBy('source_kind', 'source_id', 'item_id', 'zone_id', 'group_size'), $refs);
        $rows = $this->read->rows($itemIds === null ? $query : $query->whereIn('item_id', $itemIds));
        $by = [];
        foreach ($rows as $row) {
            $by[$row['source_kind'].$row['source_id']][(int) $row['item_id']][] = $row;
        }

        $deltas = [];
        foreach ($by as $key => $items) {
            foreach ($items as $item => $listed) {
                $own = $this->everyPlace($listed, $kept[$key] ?? []);
                $counted = $counts[$key]->items[$item] ?? ['drops' => 0, 'quantity' => 0];
                $drops = array_map(fn (array $row): int => (int) $row['drops'], $own);
                $quantities = array_map(fn (array $row): int => (int) $row['quantity'], $own);
                $caps = array_map(fn (array $row): int => $kept[$key][$this->place($row)]['kept'] ?? 0, $own);
                $weights = array_map(fn (array $row): int => intdiv((int) $row['drops'] * 1000 * ($kept[$key][$this->place($row)]['kept'] ?? 0), max(1, $kept[$key][$this->place($row)]['windows'] ?? 1)), $own);
                $keptDrops = Apportion::capped(array_sum($weights) > 0 ? $weights : $drops, $caps, $counted['drops']);
                $keptQuantity = Apportion::split(array_sum($keptDrops) > 0 ? $keptDrops : $quantities, $counted['quantity']);
                foreach ($own as $position => $row) {
                    $drop = $keptDrops[$position] - $drops[$position];
                    $quantity = $keptQuantity[$position] - $quantities[$position];
                    if ($drop !== 0 || $quantity !== 0) {
                        $deltas[] = [(string) $row['bucket'], (string) $row['source_kind'], (int) $row['source_id'], $this->nullable($row['zone_id']), $this->nullable($row['group_size']), $item, $drop, $quantity];
                    }
                }
            }
        }

        return $deltas;
    }

    /**
     * The view's rows of one item of a source, with a row of no drops for each zone and size the source has windows in and
     * the item has none, so an arm's drops can go to a row only as far as its windows.
     *
     * @param  list<array<string, mixed>>  $listed
     * @param  array<string, array{row: array<string, mixed>, windows: int, kept: int}>  $places
     * @return list<array<string, mixed>>
     */
    private function everyPlace(array $listed, array $places): array
    {
        $by = [];
        foreach ($listed as $row) {
            $by[$this->place($row)] = $row;
        }
        foreach ($places as $place => $window) {
            $by[$place] ??= [...$window['row'], 'drops' => 0, 'quantity' => 0];
        }

        return array_values($by);
    }

    private function nullable(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
