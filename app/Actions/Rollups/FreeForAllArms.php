<?php

namespace App\Actions\Rollups;

use App\Support\FreeForAllCounts;
use App\Support\LootArm;
use App\Support\LootCounted;
use App\Support\LootDay;
use App\Support\LootRead;
use App\Support\LootRule;
use App\Support\LootSourceRef;
use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class FreeForAllArms
{
    private const int BULK = 40;

    public function __construct(private readonly RollupRead $read) {}

    /**
     * The free-for-all arm of many sources at once when it counts one reader (`LootArm::overDays`, docs/LOOT.md, Units of
     * counting, rule 6), with what each item listed in the windows the arm counts, by `LootSourceRef::key()`. A source
     * with no free-for-all windows, or whose arm counts every window, is not in the answer: the aggregates already sum
     * its windows. `$sources` null reads every source. `$itemIds` null reads every item, a list only those items, and an
     * empty list none, for a table that shows windows and no drop rates. Each table that shows a free-for-all window count
     * or rate asks for this, so the creature page, the item page and `/loot` count the same windows. The read is three
     * queries for any number of sources, and the answer is kept as plain arrays for the seconds a read is fresh.
     *
     * @param  list<LootSourceRef>|null  $sources
     * @param  list<int>|null  $itemIds
     * @return array<string, FreeForAllCounts>
     */
    public function handle(?array $sources = null, ?array $itemIds = null): array
    {
        if ($sources === []) {
            return [];
        }
        $kept = $this->read->kept('free-for-all-arms', [$sources === null ? null : $this->keys($sources), $itemIds === null ? null : array_values(array_unique($itemIds))], fn (): array => $this->built($sources, $itemIds));

        $counts = [];
        foreach ($kept as $key => $parts) {
            $arm = $this->arm($parts['days']);
            if ($arm !== null) {
                $counts[(string) $key] = new FreeForAllCounts($arm, $parts['items'], $parts['raw']);
            }
        }

        return $counts;
    }

    /**
     * @param  list<LootSourceRef>|null  $sources
     * @param  list<int>|null  $itemIds
     * @return array<string, array{days: array<string, array{unkeyed: array<int, int>, keyed: array<int, int>}>, items: array<int, array{drops: int, quantity: int}>, raw: array<int, array{drops: int, quantity: int}>}>
     */
    private function built(?array $sources, ?array $itemIds): array
    {
        $armed = [];
        foreach ($this->windows($sources) as $key => $days) {
            $collapsed = LootArm::collapsed($days);
            $arm = $this->arm($collapsed);
            if ($arm !== null) {
                $armed[$key] = ['days' => $collapsed, 'arm' => $arm];
            }
        }
        $items = $itemIds === [] || $armed === [] ? [] : $this->items(array_map(fn (array $armed): LootArm => $armed['arm'], $armed), $itemIds);

        $answer = [];
        foreach ($armed as $key => $one) {
            $answer[$key] = ['days' => $one['days'], 'items' => $items[$key]['items'] ?? [], 'raw' => $items[$key]['raw'] ?? []];
        }

        return $answer;
    }

    /**
     * @param  array<string, array{unkeyed: array<int, int>, keyed: array<int, int>}>  $days
     */
    private function arm(array $days): ?LootArm
    {
        $arm = LootArm::overDays(
            LootRule::FreeForAll->value, LootRule::words(LootRule::FreeForAll->value), $days, true, false, false, (int) config('scarlet.min_characters'),
        );

        return $arm === null || $arm->reader === null ? null : $arm;
    }

    /**
     * The free-for-all windows by day, character and whether a corpse key counted them, of the sources that may count one
     * reader: some window with no key and two characters or more (`LootArm::overDays` gives every other source its
     * aggregate's windows).
     *
     * @param  list<LootSourceRef>|null  $sources
     * @return array<string, array<string, array{unkeyed: array<int, int>, keyed: array<int, int>}>> by source key, then day
     */
    private function windows(?array $sources): array
    {
        $query = LootRead::Loot->within(DB::table(LootCounted::WindowsCharacter->value.' as v'), 'v.read')->where('v.loot_rule', LootRule::FreeForAll->value)
            ->selectRaw('v.source_kind, v.source_id, v.character_id, v.keyed::int as keyed, '.LootDay::of('v.bucket').' as day, sum(v.windows) as windows')
            ->groupBy('v.source_kind', 'v.source_id', 'v.character_id', 'v.keyed')->groupByRaw(LootDay::of('v.bucket'));
        if ($sources !== null) {
            LootSourceRef::anyOf($query, $sources, 'v.source_kind', 'v.source_id');
        }
        if ($sources === null) {
            $candidates = LootRead::Loot->within(DB::table(LootCounted::WindowsCharacter->value))->where('loot_rule', LootRule::FreeForAll->value)
                ->select('source_kind', 'source_id')->groupBy('source_kind', 'source_id')
                ->havingRaw('count(distinct character_id) >= 2')->havingRaw('bool_or(not keyed)');
            $query->joinSub($candidates, 'c', fn (JoinClause $join) => $join->on('c.source_kind', '=', 'v.source_kind')->on('c.source_id', '=', 'v.source_id'));
        }

        $by = [];
        foreach ($this->read->rows($query) as $row) {
            $key = $row['source_kind'].$row['source_id'];
            $by[$key][(string) $row['day']] ??= ['unkeyed' => [], 'keyed' => []];
            $by[$key][(string) $row['day']][(int) $row['keyed'] === 1 ? 'keyed' : 'unkeyed'][(int) $row['character_id']] = (int) $row['windows'];
        }

        return $by;
    }

    /**
     * What each item listed in the windows every arm counts, and in every free-for-all window of its source. The windows
     * with a key are counted on the days the arm counts them. The reader's windows with no key that listed the item come
     * from the free-for-all item aggregate, which keeps the character, on the days the arm counts them. The aggregate keeps
     * no quantity for one character, so the reader's share of the quantity of the windows with no key is its share of the
     * windows with no key that listed the item that day. Set-based SQL, since the days an arm counts and the readers differ
     * for each source: `unnest` passes them as arrays, one binding each, whatever the number of sources.
     *
     * @param  array<string, LootArm>  $arms
     * @param  list<int>|null  $itemIds
     * @return array<string, array{items: array<int, array{drops: int, quantity: int}>, raw: array<int, array{drops: int, quantity: int}>}>
     */
    private function items(array $arms, ?array $itemIds): array
    {
        $sources = $flags = [];
        foreach ($arms as $key => $arm) {
            $sources[] = substr($key, 0, 1).'|'.(int) substr($key, 1).'|'.(int) $arm->reader;
            foreach ($arm->days as $day => $counted) {
                if ($counted['unkeyed'] && $counted['keyed']) {
                    continue;
                }
                $flags[] = substr($key, 0, 1).'|'.(int) substr($key, 1).'|'.$day.'|'.($counted['unkeyed'] ? 't' : 'f').'|'.($counted['keyed'] ? 't' : 'f');
            }
        }
        $only = $itemIds === null ? null : '{'.implode(',', array_map('intval', $itemIds)).'}';
        $listed = [LootRead::Loot->value, LootRule::FreeForAll->value, $only, $only];
        $bindings = [
            $this->literal($sources), $this->literal($flags),
            ...$listed, ...$listed, ...$listed, LootRead::Loot->value, $only, $only,
        ];

        $query = DB::query()->fromRaw('('.$this->itemsSql().') as arm_items', $bindings)->select('*');
        $answer = [];
        foreach ($this->inBulk(count($arms) > self::BULK, $query) as $row) {
            $key = $row['source_kind'].$row['source_id'];
            $item = (int) $row['item_id'];
            $answer[$key]['items'] ??= [];
            $answer[$key]['raw'] ??= [];
            $answer[$key]['raw'][$item] = ['drops' => (int) $row['raw_drops'], 'quantity' => (int) $row['raw_quantity']];
            if ((int) $row['drops'] > 0) {
                $answer[$key]['items'][$item] = ['drops' => (int) $row['drops'], 'quantity' => (int) round((float) $row['quantity'])];
            }
        }

        return $answer;
    }

    /**
     * The rows of `$query`, read once for the seconds `handle` keeps the answer. For many sources the planner takes the list
     * of sources for one row and joins it by looping over it for each row of the aggregates (170 million tests for 1,500
     * sources, 15 seconds), so there it joins by hash and merge instead.
     *
     * @return list<array<string, mixed>>
     */
    private function inBulk(bool $bulk, Builder $query): array
    {
        return DB::transaction(function () use ($bulk, $query): array {
            if ($bulk) {
                DB::statement('set local enable_nestloop = off');
            }

            return array_values($query->get()->map(fn (object $row): array => (array) $row)->all());
        });
    }

    /**
     * Two aliases of the item rows of one source joined on what they share.
     *
     * @param  literal-string  $one
     * @param  literal-string  $other
     * @return literal-string
     */
    private static function same(string $one, string $other): string
    {
        return "{$one}.source_kind = {$other}.source_kind and {$one}.source_id = {$other}.source_id and {$one}.item_id = {$other}.item_id";
    }

    /** @param  list<int|string>  $values */
    private function literal(array $values): string
    {
        return '{'.implode(',', $values).'}';
    }

    /**
     * The read of `items`: the sources and the days they count one set on come in as arrays, and a table the day has no
     * entry in counts both sets (`LootArm::days`).
     *
     * @return literal-string
     */
    private function itemsSql(): string
    {
        $vDay = LootDay::of('v.bucket');
        $uDay = LootDay::of('u.bucket');
        $items = Rollup::LootFreeForAllItemsUnkeyedDaily->value;
        $oU = self::same('o', 'u');
        $kR = self::same('k', 'r');
        $nR = self::same('n', 'r');

        return <<<SQL
            with s(source_kind, source_id, reader) as (select split_part(t, '|', 1), split_part(t, '|', 2)::int, split_part(t, '|', 3)::bigint from unnest(?::text[]) as t),
            f(source_kind, source_id, day, unkeyed, keyed) as (select split_part(t, '|', 1), split_part(t, '|', 2)::int, split_part(t, '|', 3), split_part(t, '|', 4)::bool, split_part(t, '|', 5)::bool from unnest(?::text[]) as t),
            raw as (
                select v.source_kind, v.source_id, v.item_id, sum(v.windows) as drops, sum(v.quantity) as quantity
                from loot_items_counted v join s on s.source_kind = v.source_kind and s.source_id = v.source_id
                where v.read = ? and v.loot_rule = ? and (?::int[] is null or v.item_id = any (?::int[]))
                group by v.source_kind, v.source_id, v.item_id
            ), keyed as (
                select v.source_kind, v.source_id, v.item_id, sum(v.windows) as drops, sum(v.quantity) as quantity
                from loot_items_counted v join s on s.source_kind = v.source_kind and s.source_id = v.source_id
                left join f on f.source_kind = v.source_kind and f.source_id = v.source_id and f.day = {$vDay}
                where v.keyed and v.read = ? and v.loot_rule = ? and (?::int[] is null or v.item_id = any (?::int[])) and coalesce(f.keyed, true)
                group by v.source_kind, v.source_id, v.item_id
            ), unkeyed as (
                select u.source_kind, u.source_id, u.item_id, sum(o.windows) as drops, sum(u.quantity * o.windows / u.windows) as quantity
                from (
                    select v.source_kind, v.source_id, v.item_id, v.bucket, sum(v.windows) as windows, sum(v.quantity) as quantity
                    from loot_items_counted v join s on s.source_kind = v.source_kind and s.source_id = v.source_id
                    where not v.keyed and v.read = ? and v.loot_rule = ? and (?::int[] is null or v.item_id = any (?::int[]))
                    group by v.source_kind, v.source_id, v.item_id, v.bucket
                ) u
                join (
                    select v.source_kind, v.source_id, v.item_id, v.bucket, sum(v.windows) as windows
                    from {$items} v join s on s.source_kind = v.source_kind and s.source_id = v.source_id and s.reader = v.character_id
                    where v.read = ? and (?::int[] is null or v.item_id = any (?::int[]))
                    group by v.source_kind, v.source_id, v.item_id, v.bucket
                ) o on {$oU} and o.bucket = u.bucket
                left join f on f.source_kind = u.source_kind and f.source_id = u.source_id and f.day = {$uDay}
                where coalesce(f.unkeyed, true)
                group by u.source_kind, u.source_id, u.item_id
            )
            select r.source_kind, r.source_id, r.item_id, r.drops as raw_drops, r.quantity as raw_quantity,
                   coalesce(k.drops, 0) + coalesce(n.drops, 0) as drops, coalesce(k.quantity, 0) + coalesce(n.quantity, 0) as quantity
            from raw r
            left join keyed k on {$kR}
            left join unkeyed n on {$nR}
            SQL;
    }

    /**
     * @param  list<LootSourceRef>  $sources
     * @return list<string>
     */
    private function keys(array $sources): array
    {
        $keys = array_values(array_unique(array_map(fn (LootSourceRef $source): string => $source->key(), $sources)));
        sort($keys);

        return $keys;
    }
}
