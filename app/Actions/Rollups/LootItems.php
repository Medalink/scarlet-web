<?php

namespace App\Actions\Rollups;

use App\Support\CountedLoot;
use App\Support\LootCounted;
use App\Support\LootFilter;
use App\Support\LootListing;
use App\Support\LootRead;
use App\Support\LootRule;
use App\Support\LootSort;
use App\Support\LootSourceKind;
use App\Support\NameKind;
use App\Support\RateEstimate;
use App\Support\RollupRead;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class LootItems
{
    public function __construct(private readonly RollupRead $read, private readonly CountedLoot $counted) {}

    /**
     * The items that dropped, one page: in how many windows (first reads of every source read for loot, skinning, herbs,
     * ore or a container, every loot rule but personal loot), from how many sources, the best source with its rate and
     * the zone the item dropped most in. Narrowed by zone, by the item's quality or better and by a text that matches the
     * item's name or id. Ordered by windows, or by sources for `LootSort::Items`. `more` is true when a next page exists. A
     * free-for-all drop counts only the windows of its source's arm, and a best source's rate is over the arm's windows
     * (`FreeForAllArms`), as the creature page counts them.
     *
     * @return array{rows: list<array<string, mixed>>, more: bool}
     */
    public function handle(LootFilter $filter, int $offset, int $limit): array
    {
        $found = $this->read->rows($this->page($filter, $offset, $limit + 1));
        $rows = array_slice($found, 0, $limit);
        if ($rows === []) {
            return ['rows' => [], 'more' => false];
        }
        $ids = array_map(fn (array $row): int => (int) $row['item_id'], $rows);
        $best = $this->best($filter, $ids);
        $zones = $this->zones($filter, $ids);

        return [
            'rows' => array_map(fn (array $row): array => [
                'item_id' => (int) $row['item_id'], 'name' => $row['name'], 'quality' => $row['quality'] === null ? null : (int) $row['quality'],
                'dropped' => (int) $row['dropped'], 'sources' => (int) $row['sources'],
                'best' => $best[(int) $row['item_id']] ?? null, 'zone' => $zones[(int) $row['item_id']] ?? null,
            ], $rows),
            'more' => count($found) > $limit,
        ];
    }

    private function page(LootFilter $filter, int $offset, int $take): Builder
    {
        $shift = $this->counted->dropShift($filter->zone);
        $query = $this->within($this->read->table(LootCounted::Items->value.' as d', null), $filter)
            ->leftJoin('items as i', 'i.item_id', '=', 'd.item_id')
            ->selectRaw('d.item_id, (select n.name from names n where n.kind = ? and n.id = d.item_id order by n.votes desc, n.name limit 1) as name', [NameKind::Item->value])
            ->selectRaw('i.quality, count(distinct (d.source_kind, d.source_id)) as sources')
            ->when($filter->quality !== null, fn (Builder $query): Builder => $query->where('i.quality', '>=', $filter->quality))
            ->groupBy('d.item_id', 'i.quality')
            ->havingRaw('distinct_count(rollup(d.characters)) >= ?', [(int) config('scarlet.min_characters')]);
        $query->selectRaw($shift === null ? 'sum(d.windows) as dropped' : 'sum(d.windows) + coalesce(max(x.delta), 0) as dropped');
        if ($shift !== null) {
            $query->leftJoinSub($shift, 'x', 'x.item_id', '=', 'd.item_id');
        }
        $like = $filter->like();
        if ($like !== null) {
            $query->where(function (Builder $any) use ($filter, $like): void {
                $any->whereExists(fn (Builder $own) => $own->selectRaw('1')->from('names as n')
                    ->where('n.kind', NameKind::Item->value)->whereColumn('n.id', 'd.item_id')->where('n.name', 'ilike', $like));
                if ($filter->id() !== null) {
                    $any->orWhere('d.item_id', $filter->id());
                }
            });
        }

        $ordered = match ($filter->sort) {
            LootSort::Items => $query->orderByRaw('count(distinct (d.source_kind, d.source_id)) desc')->orderByDesc('dropped'),
            default => $query->orderByDesc('dropped'),
        };

        return $ordered->orderBy('d.item_id')->offset($offset)->limit($take);
    }

    /** The windows the items table lists, over the aggregate aliased `d`: every read but fishing, every rule but personal, one zone. */
    private function within(Builder $query, LootFilter $filter): Builder
    {
        return LootListing::ofSources(LootRule::within($query, 'd.loot_rule', null), 'd.read', 'd.source_kind', 'd.source_id')
            ->when($filter->zone !== null, fn (Builder $query): Builder => $query->where('d.zone_id', $filter->zone));
    }

    /**
     * The source each item has the best rate in: the sources it dropped from ranked as the item page ranks them, by the low
     * end of the 95% range, then by what was seen.
     *
     * @param  list<int>  $ids
     * @return array<int, array{source_kind: string, source_id: int, source: string|null, estimate: RateEstimate}>
     */
    private function best(LootFilter $filter, array $ids): array
    {
        $windows = LootListing::ofSources(LootRule::within(DB::table(LootCounted::Windows->value), 'loot_rule', null))
            ->when($filter->zone !== null, fn (Builder $query): Builder => $query->where('zone_id', $filter->zone))
            ->selectRaw('source_kind, source_id, read, sum(windows) as windows')->groupBy('source_kind', 'source_id', 'read');
        $sources = $this->counted->sourcesListing($ids);
        $shift = $this->counted->windowShift($sources, $filter->zone === null ? 'source' : 'source_zone');
        $query = LootSourceKind::withName($this->within($this->counted->items('d', $sources, $ids), $filter)
            ->joinSub($windows, 'o', fn (JoinClause $join) => $join->on('o.source_kind', '=', 'd.source_kind')->on('o.source_id', '=', 'd.source_id')->on('o.read', '=', 'd.read'))
            ->whereIn('d.item_id', $ids)
            ->select('d.item_id', 'd.source_kind', 'd.source_id', 'd.read'))
            ->selectRaw('sum(d.windows) as drops, o.windows as opens')
            ->groupBy('d.item_id', 'd.source_kind', 'd.source_id', 'd.read', 'o.windows')
            ->havingRaw('distinct_count(rollup(d.characters)) >= ?', [(int) config('scarlet.min_characters')]);

        $best = [];
        foreach ($this->read->rows($query) as $row) {
            $item = (int) $row['item_id'];
            $opens = (int) $row['opens'] + ($row['read'] === LootRead::Loot->value ? ($shift[$row['source_kind'].$row['source_id'].($filter->zone === null ? '' : '|'.$filter->zone)] ?? 0) : 0);
            $estimate = RateEstimate::of((int) $row['drops'], $opens);
            if (! isset($best[$item]) || RateEstimate::compare($estimate, $best[$item]['estimate']) < 0) {
                $best[$item] = ['source_kind' => (string) $row['source_kind'], 'source_id' => (int) $row['source_id'], 'source' => $row['source'], 'estimate' => $estimate];
            }
        }

        return $best;
    }

    /**
     * The zone each item dropped most in.
     *
     * @param  list<int>  $ids
     * @return array<int, array{id: int, name: string|null}>
     */
    private function zones(LootFilter $filter, array $ids): array
    {
        $rows = $this->read->rows($this->within($this->counted->items('d', $this->counted->sourcesOf($ids), $ids), $filter)
            ->selectRaw('d.item_id, d.zone_id, (select z.name from zones z where z.id = d.zone_id) as zone, sum(d.windows) as windows')
            ->whereIn('d.item_id', $ids)->whereNotNull('d.zone_id')->groupBy('d.item_id', 'd.zone_id')
            ->orderByRaw('sum(d.windows) desc')->orderBy('d.zone_id'));
        $zones = [];
        foreach ($rows as $row) {
            $zones[(int) $row['item_id']] ??= ['id' => (int) $row['zone_id'], 'name' => $row['zone']];
        }

        return $zones;
    }
}
