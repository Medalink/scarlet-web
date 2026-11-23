<?php

namespace App\Support;

use App\Actions\Rollups\FreeForAllDeltas;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The counted views (`LootCounted`) as the tables count a corpse: with the free-for-all windows of every source whose arm
 * counts one reader replaced by the arm's (docs/LOOT.md, Units of counting, rule 6). A reader that shows a window count or
 * a drop rate over every loot rule asks here for its rows instead of the view, and groups, sums and orders them as it
 * always did. When no source in scope has such an arm the rows are the view's, in the same SQL.
 */
final class CountedLoot
{
    /** @var array<string, array{windows: list<array<int, mixed>>, items: list<array<int, mixed>>}> */
    private array $deltas = [];

    public function __construct(private readonly FreeForAllDeltas $free, private readonly RollupRead $read) {}

    /**
     * The sources whose free-for-all windows listed any of the items, so a table of those items asks for the arms of these
     * sources and no others.
     *
     * @param  list<int>  $itemIds
     * @return list<LootSourceRef>
     */
    public function sourcesOf(array $itemIds): array
    {
        $rows = $this->read->rows(LootRead::Loot->within(DB::table(LootCounted::Items->value))->where('loot_rule', LootRule::FreeForAll->value)
            ->whereIn('item_id', $itemIds)->select('source_kind', 'source_id')->distinct());

        return array_map(fn (array $row): LootSourceRef => new LootSourceRef(LootSourceKind::from((string) $row['source_kind']), (int) $row['source_id']), $rows);
    }

    /**
     * The creature, object or container sources of `$kind` whose free-for-all windows fall in any of the zones, so a table of
     * those zones asks for the arms of these sources and no others.
     *
     * @param  list<int>  $zones
     * @return list<LootSourceRef>
     */
    public function sourcesIn(array $zones, LootSourceKind $kind): array
    {
        $rows = $this->read->rows(LootRead::Loot->within(DB::table(LootCounted::Windows->value))->where('loot_rule', LootRule::FreeForAll->value)
            ->where('source_kind', $kind->value)->whereIn('zone_id', $zones)->select('source_id')->distinct());

        return array_map(fn (array $row): LootSourceRef => new LootSourceRef($kind, (int) $row['source_id']), $rows);
    }

    /**
     * How many windows the arms add to the windows of the free-for-all loot rows in the view, negative when an arm counts
     * fewer, summed by `$by`: `source` (`LootSourceRef::key()`), `zone`, group `size` (an empty key for none), `total`, or
     * `source_zone` (a source's key, then `|` and the zone).
     * A source whose arm counts every window adds nothing. For a table that sums the view's windows itself and adds this to
     * what it shows, which keeps the view's plan for the scan; `$kind` narrows it to one kind of source.
     *
     * @param  list<LootSourceRef>|null  $sources
     * @return array<string, int>
     */
    public function windowShift(?array $sources, string $by = 'source', ?LootSourceKind $kind = null): array
    {
        $shift = [];
        foreach ($this->rows($sources, [])['windows'] as $row) {
            if ($kind !== null && $row[1] !== $kind->value) {
                continue;
            }
            $key = match ($by) {
                'zone' => (string) $row[3],
                'size' => (string) $row[4],
                'total' => '',
                'source_zone' => $row[1].$row[2].'|'.$row[3],
                default => $row[1].$row[2],
            };
            $shift[$key] = ($shift[$key] ?? 0) + $row[5];
        }

        return $shift;
    }

    /**
     * What the arms add to each item's drops (negative when an arm counts fewer) over every source, as the rows `item_id` and
     * `delta` of a query a table joins on its item and adds to the sum of the view's windows, which keeps the view's plan for
     * the scan. `$zone` narrows it to one zone. Null when no item changes.
     */
    public function dropShift(?int $zone = null): ?Builder
    {
        $shift = [];
        foreach ($this->rows(null, null)['items'] as $row) {
            if ($zone === null || $row[3] === $zone) {
                $shift[$row[5]] = ($shift[$row[5]] ?? 0) + $row[6];
            }
        }
        $shift = array_filter($shift, fn (int $delta): bool => $delta !== 0);
        if ($shift === []) {
            return null;
        }

        return DB::query()->fromRaw('unnest(?::int[], ?::bigint[]) as u(item_id, delta)', [$this->literal(array_keys($shift)), $this->literal(array_values($shift))])->select('u.item_id', 'u.delta');
    }

    /**
     * The rows of `loot_windows_counted`, aliased `$alias`, adjusted for `$sources` (every source when null).
     *
     * @param  list<LootSourceRef>|null  $sources
     */
    public function windows(string $alias = 'w', ?array $sources = null): Builder
    {
        $rows = $this->rows($sources, [])['windows'];
        if ($rows === []) {
            return DB::table(LootCounted::Windows->value.' as '.$alias);
        }
        $adjust = DB::query()->fromRaw('unnest(?::timestamptz[], ?::text[], ?::int[], ?::int[], ?::smallint[], ?::bigint[]) as x(bucket, source_kind, source_id, zone_id, group_size, windows)', [
            $this->literal(array_column($rows, 0)), $this->literal(array_column($rows, 1)), $this->literal(array_column($rows, 2)), $this->literal(array_column($rows, 3)),
            $this->literal(array_column($rows, 4)), $this->literal(array_column($rows, 5)),
        ])->selectRaw('x.bucket, x.source_kind, x.source_id, ?::varchar as read, ?::varchar as loot_rule, x.group_size, x.zone_id, x.windows, null::bigint as item_rows, null::hyperloglog as characters, false as keyed', [LootRead::Loot->value, LootRule::FreeForAll->value]);

        return DB::query()->fromSub(
            DB::table(LootCounted::Windows->value)->select('bucket', 'source_kind', 'source_id', 'read', 'loot_rule', 'group_size', 'zone_id', 'windows', 'item_rows', 'characters', 'keyed')->unionAll($adjust),
            $alias,
        );
    }

    /**
     * The rows of `loot_items_counted`, aliased `$alias`, adjusted for `$sources` (every source when null) and for `$itemIds`
     * (every item when null). A row of a source or item outside them is the view's.
     *
     * @param  list<LootSourceRef>|null  $sources
     * @param  list<int>|null  $itemIds
     */
    public function items(string $alias = 'd', ?array $sources = null, ?array $itemIds = null): Builder
    {
        $rows = $this->rows($sources, $itemIds)['items'];
        if ($rows === []) {
            return DB::table(LootCounted::Items->value.' as '.$alias);
        }
        $adjust = DB::query()->fromRaw('unnest(?::timestamptz[], ?::text[], ?::int[], ?::int[], ?::smallint[], ?::int[], ?::bigint[], ?::bigint[]) as x(bucket, source_kind, source_id, zone_id, group_size, item_id, windows, quantity)', [
            $this->literal(array_column($rows, 0)), $this->literal(array_column($rows, 1)), $this->literal(array_column($rows, 2)), $this->literal(array_column($rows, 3)),
            $this->literal(array_column($rows, 4)), $this->literal(array_column($rows, 5)), $this->literal(array_column($rows, 6)), $this->literal(array_column($rows, 7)),
        ])->selectRaw('x.bucket, x.source_kind, x.source_id, ?::varchar as read, ?::varchar as loot_rule, x.group_size, x.zone_id, x.item_id, x.windows, x.quantity, null::hyperloglog as characters, false as keyed', [LootRead::Loot->value, LootRule::FreeForAll->value]);

        return DB::query()->fromSub(
            DB::table(LootCounted::Items->value)->select('bucket', 'source_kind', 'source_id', 'read', 'loot_rule', 'group_size', 'zone_id', 'item_id', 'windows', 'quantity', 'characters', 'keyed')->unionAll($adjust),
            $alias,
        );
    }

    /**
     * @param  list<LootSourceRef>|null  $sources
     * @param  list<int>|null  $itemIds
     * @return array{windows: list<array<int, mixed>>, items: list<array<int, mixed>>}
     */
    private function rows(?array $sources, ?array $itemIds): array
    {
        if ($sources === []) {
            return ['windows' => [], 'items' => []];
        }
        $key = json_encode([$sources === null ? null : array_map(fn (LootSourceRef $source): string => $source->key(), $sources), $itemIds], JSON_THROW_ON_ERROR);

        return $this->deltas[$key] ??= $this->free->handle($sources, $itemIds);
    }

    /**
     * A PostgreSQL array literal of the values, a missing one as NULL.
     *
     * @param  array<int, mixed>  $values
     */
    private function literal(array $values): string
    {
        return '{'.implode(',', array_map(fn (mixed $value): string => $value === null ? 'NULL' : '"'.addcslashes((string) $value, '"\\').'"', $values)).'}';
    }
}
