<?php

namespace App\Actions\Rollups;

use App\Support\LootCounted;
use App\Support\LootFilter;
use App\Support\LootListing;
use App\Support\LootRead;
use App\Support\LootRule;
use App\Support\LootSort;
use App\Support\LootSourceKind;
use App\Support\LootView;
use App\Support\NameKind;
use App\Support\RateEstimate;
use App\Support\RollupRead;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class LootSources
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * The creatures, objects or containers of `$filter`'s view that were looted, one page: how many windows (first reads,
     * every loot rule but personal loot, the read the view lists), how many distinct items they gave, the levels, the
     * zone with most of the windows and the item that was in most of them with its rate. Narrowed by zone, level range,
     * the quality of an item the source gave, and a text that matches the source's name or id or an item it gave.
     * Ordered by the filter's sort. `more` is true when a next page exists. Sources under `scarlet.min_characters`
     * characters are left out. The levels are the creature's for a creature and the looter's for an object or a container.
     *
     * @return array{rows: list<array<string, mixed>>, more: bool}
     */
    public function handle(LootFilter $filter, int $offset, int $limit): array
    {
        $kind = $filter->view->sourceKind();
        if ($kind === null) {
            return ['rows' => [], 'more' => false];
        }
        $found = $this->read->rows($this->page($filter, $kind, $offset, $limit + 1));
        $rows = array_slice($found, 0, $limit);
        if ($rows === []) {
            return ['rows' => [], 'more' => false];
        }
        $ids = array_map(fn (array $row): int => (int) $row['source_id'], $rows);
        $zones = $this->zones($filter, $kind, $ids);
        $tops = $this->tops($filter, $kind, $ids);

        return [
            'rows' => array_map(function (array $row) use ($zones, $tops): array {
                $id = (int) $row['source_id'];
                $windows = (int) $row['windows'];

                return [
                    'source_id' => $id, 'name' => $row['name'], 'windows' => $windows, 'items' => (int) $row['items'], 'characters' => (int) round((float) $row['characters']),
                    'level_min' => $row['lo'] === null ? null : (int) $row['lo'], 'level_max' => $row['hi'] === null ? null : (int) $row['hi'],
                    'zone' => $zones[$id] ?? null,
                    'top' => isset($tops[$id]) ? ['item_id' => $tops[$id]['item_id'], 'item' => $tops[$id]['item'], 'estimate' => RateEstimate::of($tops[$id]['drops'], $windows)] : null,
                ];
            }, $rows),
            'more' => count($found) > $limit,
        ];
    }

    private function page(LootFilter $filter, LootSourceKind $kind, int $offset, int $take): Builder
    {
        $items = $this->within(DB::table(LootCounted::Items->value.' as d'), 'd', $filter, $kind)
            ->selectRaw('d.source_id, count(distinct d.item_id) as items')->groupBy('d.source_id');
        $query = $this->within($this->read->table(LootCounted::Windows->value.' as w', null), 'w', $filter, $kind)
            ->leftJoinSub($items, 'i', fn (JoinClause $join) => $join->on('i.source_id', '=', 'w.source_id'))
            ->leftJoinSub($this->levels($kind), 'l', fn (JoinClause $join) => $join->on('l.source_id', '=', 'w.source_id'))
            ->selectRaw('w.source_id, (select n.name from names n where n.kind = ? and n.id = w.source_id order by n.votes desc, n.name limit 1) as name', [$filter->view->nameKind()->value])
            ->selectRaw('sum(w.windows) as windows, distinct_count(rollup(w.characters)) as characters, coalesce(i.items, 0) as items, l.lo, l.hi')
            ->when($filter->levelMin !== null, fn (Builder $query): Builder => $query->where('l.hi', '>=', $filter->levelMin))
            ->when($filter->levelMax !== null, fn (Builder $query): Builder => $query->where('l.lo', '<=', $filter->levelMax))
            ->when($filter->quality !== null, fn (Builder $query): Builder => $query->whereExists(fn (Builder $gave) => $gave->selectRaw('1')->from(LootCounted::Items->value.' as qd')
                ->join('items as qi', 'qi.item_id', '=', 'qd.item_id')
                ->whereColumn('qd.source_kind', 'w.source_kind')->whereColumn('qd.source_id', 'w.source_id')->where('qi.quality', '>=', $filter->quality)))
            ->groupBy('w.source_id', 'i.items', 'l.lo', 'l.hi')
            ->havingRaw('distinct_count(rollup(w.characters)) >= ?', [(int) config('scarlet.min_characters')]);
        $query = $this->matching($query, $filter);

        $ordered = match ($filter->sort) {
            LootSort::Windows => $query->orderByRaw('sum(w.windows) desc'),
            LootSort::Items => $query->orderByRaw('coalesce(i.items, 0) desc')->orderByRaw('sum(w.windows) desc'),
            LootSort::Level => $query->orderByRaw('l.lo asc nulls last'),
        };

        return $ordered->orderBy('w.source_id')->offset($offset)->limit($take);
    }

    /** The windows a view lists, over the rows of the aggregate aliased `$alias`: one kind of source, its read, every rule but personal, one zone. */
    private function within(Builder $query, string $alias, LootFilter $filter, LootSourceKind $kind): Builder
    {
        $query = LootRule::within($query->where($alias.'.source_kind', $kind->value), $alias.'.loot_rule', null);
        $query = match ($filter->view) {
            LootView::Creatures => LootRead::Loot->within($query, $alias.'.read'),
            LootView::Containers => LootRead::Bag->within($query, $alias.'.read'),
            default => LootListing::ofObjects($query, $alias.'.read', $alias.'.source_kind', $alias.'.source_id'),
        };

        return $query->when($filter->zone !== null, fn (Builder $query): Builder => $query->where($alias.'.zone_id', $filter->zone));
    }

    /** The lowest and highest level of each source of the kind. */
    private function levels(LootSourceKind $kind): Builder
    {
        if ($kind === LootSourceKind::Creature) {
            return DB::table('creature_facts_daily')->selectRaw('npc_id as source_id, min(npc_level) as lo, max(npc_level) as hi')->whereNotNull('npc_level')->groupBy('npc_id');
        }

        return DB::table('loot_opens')->selectRaw('source_id, min(level) as lo, max(level) as hi')
            ->where('counted', true)->where('has_items', true)->whereRaw('first_read is not false')
            ->where('source_kind', $kind->value)->whereNotNull('source_id')->whereNotNull('level')->groupBy('source_id');
    }

    /** Keeps the sources whose name or id matches the filter's text, or that gave an item whose name does. */
    private function matching(Builder $query, LootFilter $filter): Builder
    {
        $like = $filter->like();
        if ($like === null) {
            return $query;
        }

        return $query->where(function (Builder $any) use ($filter, $like): void {
            $any->whereExists(fn (Builder $own) => $own->selectRaw('1')->from('names as n')
                ->where('n.kind', $filter->view->nameKind()->value)->whereColumn('n.id', 'w.source_id')->where('n.name', 'ilike', $like))
                ->orWhereExists(fn (Builder $gave) => $gave->selectRaw('1')->from(LootCounted::Items->value.' as qd')
                    ->join('names as qn', fn (JoinClause $join) => $join->on('qn.id', '=', 'qd.item_id')->where('qn.kind', '=', NameKind::Item->value))
                    ->whereColumn('qd.source_kind', 'w.source_kind')->whereColumn('qd.source_id', 'w.source_id')->where('qn.name', 'ilike', $like));
            if ($filter->id() !== null) {
                $any->orWhere('w.source_id', $filter->id());
            }
        });
    }

    /**
     * The zone with most of each source's windows.
     *
     * @param  list<int>  $ids
     * @return array<int, array{id: int, name: string|null}>
     */
    private function zones(LootFilter $filter, LootSourceKind $kind, array $ids): array
    {
        $rows = $this->read->rows($this->within($this->read->table(LootCounted::Windows->value.' as w', null), 'w', $filter, $kind)
            ->selectRaw('w.source_id, w.zone_id, (select z.name from zones z where z.id = w.zone_id) as zone, sum(w.windows) as windows')
            ->whereIn('w.source_id', $ids)->whereNotNull('w.zone_id')->groupBy('w.source_id', 'w.zone_id')
            ->orderByRaw('sum(w.windows) desc')->orderBy('w.zone_id'));
        $zones = [];
        foreach ($rows as $row) {
            $zones[(int) $row['source_id']] ??= ['id' => (int) $row['zone_id'], 'name' => $row['zone']];
        }

        return $zones;
    }

    /**
     * The item that was in the most windows of each source.
     *
     * @param  list<int>  $ids
     * @return array<int, array{item_id: int, item: string|null, drops: int}>
     */
    private function tops(LootFilter $filter, LootSourceKind $kind, array $ids): array
    {
        $rows = $this->read->rows($this->within($this->read->table(LootCounted::Items->value.' as d', null), 'd', $filter, $kind)
            ->selectRaw('d.source_id, d.item_id, (select n.name from names n where n.kind = ? and n.id = d.item_id order by n.votes desc, n.name limit 1) as item, sum(d.windows) as drops', [NameKind::Item->value])
            ->whereIn('d.source_id', $ids)->groupBy('d.source_id', 'd.item_id')
            ->orderByRaw('sum(d.windows) desc')->orderBy('d.item_id'));
        $tops = [];
        foreach ($rows as $row) {
            $tops[(int) $row['source_id']] ??= ['item_id' => (int) $row['item_id'], 'item' => $row['item'], 'drops' => (int) $row['drops']];
        }

        return $tops;
    }
}
