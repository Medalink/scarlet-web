<?php

namespace App\Actions\Public\Character;

use App\Actions\Public\QuestRecord;
use App\Support\ItemClass;
use App\Support\ItemOrigin;
use App\Support\ItemQuality;
use App\Support\LootVia;
use App\Support\NameKind;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ItemsFound
{
    /** Best items the page lists. */
    public const int SHOWN = 8;

    /** Seconds around an item receipt in which a quest turn-in counts as its source. */
    public const int QUEST_WINDOW = 5;

    /** A quest turned in within `QUEST_WINDOW` seconds of the row `d`; bind the event and the window twice. */
    private const string QUEST_NEAR = 'exists (select 1 from quest_events q where q.character_id = d.character_id and q.counted and q.event = ? and q.at between d.at - make_interval(secs => ?) and d.at + make_interval(secs => ?))';

    /**
     * What a character looted by item quality, and the best items it received from any source except crafting.
     *
     * Looted: a `window` row is an item in a loot window, and a `receipt` row counts only when it has a loot source and its
     * session has no window rows (an older log with no item lists).
     *
     * Received: the looted items, plus every receipt with no loot source (quest rewards, purchases, mail, trades, group
     * rolls), minus any item the character crafted. `best` lists the uncommon or better of them by quality and then item
     * level, and `gear` is the best weapon or armour among them, the first one in that same order.
     *
     * @return array{
     *     qualities: array<int, int>,
     *     best: list<array{item_id: int, name: string, quality: int, item_level: int|null, quantity: int, origin: int}>,
     *     gear: array{item_id: int, name: string, quality: int, item_level: int|null, quantity: int, origin: int}|null
     * }
     */
    public function handle(int $characterId): array
    {
        $qualities = $this->looted($characterId)->whereBetween('i.quality', [ItemQuality::Poor->value, ItemQuality::Epic->value])
            ->groupBy('i.quality')->selectRaw('i.quality as quality, sum(d.quantity) as items')->pluck('items', 'quality');
        $best = $this->finds($this->received($characterId))->limit(self::SHOWN)->get();
        $gears = [ItemClass::Weapon->value, ItemClass::Armor->value];
        $gear = $best->first(fn (\stdClass $row): bool => in_array((int) $row->class_id, $gears, true))
            ?? ($best->count() < self::SHOWN ? null : $this->finds($this->received($characterId)->whereIn('i.class_id', $gears))->first());

        return [
            'qualities' => array_map(fn (mixed $count): int => (int) $count, $qualities->all()),
            'best' => array_values($best->map(fn (\stdClass $row): array => $this->find($row))->all()),
            'gear' => $gear === null ? null : $this->find($gear),
        ];
    }

    /** Uncommon or better items of a query from `received()`, best first, with where the best copy of each came from. */
    private function finds(Builder $received): Builder
    {
        return $received->where('i.quality', '>=', ItemQuality::Uncommon->value)->groupBy('d.item_id', 'i.quality', 'i.item_level', 'i.class_id')
            ->selectRaw('d.item_id, (select n.name from names n where n.kind = ? and n.id = d.item_id order by n.votes desc, n.name limit 1) as name', [NameKind::Item->value])
            ->selectRaw('i.quality, i.item_level, i.class_id, sum(d.quantity) as quantity')
            ->selectRaw(
                'min(case when d.via = ? or d.source_kind is not null then ? when '.self::QUEST_NEAR.' then ? else ? end) as origin',
                [LootVia::Window->value, ItemOrigin::Looted->value, ...$this->questBindings(), ItemOrigin::Quest->value, ItemOrigin::Other->value],
            )
            ->orderByDesc('i.quality')->orderByRaw('i.item_level desc nulls last')->orderBy('d.item_id');
    }

    /** @return array{item_id: int, name: string, quality: int, item_level: int|null, quantity: int, origin: int} */
    private function find(\stdClass $row): array
    {
        return [
            'item_id' => (int) $row->item_id, 'name' => (string) ($row->name ?? 'Item '.$row->item_id), 'quality' => (int) $row->quality,
            'item_level' => $row->item_level === null ? null : (int) $row->item_level, 'quantity' => (int) $row->quantity, 'origin' => (int) $row->origin,
        ];
    }

    /** @return list<string|int> the bindings of `QUEST_NEAR`: the event and the seconds on each side of the receipt */
    private function questBindings(): array
    {
        return [QuestRecord::TURNED_IN, self::QUEST_WINDOW, self::QUEST_WINDOW];
    }

    private function counted(int $characterId): Builder
    {
        return DB::table('loot_drops as d')->join('items as i', 'i.item_id', '=', 'd.item_id')->where('d.character_id', $characterId)->where('d.counted', true);
    }

    private function looted(int $characterId): Builder
    {
        return $this->counted($characterId)->where(function (Builder $query): void {
            $query->where('d.via', LootVia::Window->value)->orWhere(function (Builder $receipt): void {
                $receipt->where('d.via', LootVia::Receipt->value)->whereNotNull('d.source_kind')->whereNotExists($this->windowInSession());
            });
        });
    }

    /** What the character received from any source except crafting: loot windows and every receipt that is not a duplicate of one. */
    private function received(int $characterId): Builder
    {
        return $this->counted($characterId)
            ->whereNotIn('d.item_id', fn (Builder $crafts) => $crafts->select('c.item_id')->from('crafts as c')->where('c.character_id', $characterId)->where('c.counted', true)->whereNotNull('c.item_id'))
            ->where(function (Builder $query): void {
                $query->where('d.via', LootVia::Window->value)->orWhere(function (Builder $receipt): void {
                    $receipt->where('d.via', LootVia::Receipt->value)->where(function (Builder $unsourced): void {
                        $unsourced->whereNull('d.source_kind')->orWhereNotExists($this->windowInSession());
                    });
                });
            });
    }

    /** A loot window row in the session of the outer row. */
    private function windowInSession(): \Closure
    {
        return function (Builder $window): void {
            $window->selectRaw('1')->from('loot_drops as w')->whereColumn('w.character_id', 'd.character_id')->whereColumn('w.session_start', 'd.session_start')
                ->where('w.via', LootVia::Window->value);
        };
    }
}
