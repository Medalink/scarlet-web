<?php

namespace App\Actions\Public\Character;

use App\Support\ItemQuality;
use App\Support\LootVia;
use App\Support\NameKind;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ItemsFound
{
    /** Best finds the page lists. */
    public const int SHOWN = 8;

    /**
     * What a character looted, by item quality, and its best finds (uncommon or better). Only looted items count: a
     * `window` row is an item in a loot window, and a `receipt` row counts only when it has a loot source and its
     * session has no window rows (an older log with no item lists). Crafts, purchases and rewards have no source.
     *
     * @return array{qualities: array<int, int>, best: list<array{item_id: int, name: string, quality: int, item_level: int|null, quantity: int}>}
     */
    public function handle(int $characterId): array
    {
        $qualities = $this->looted($characterId)->whereBetween('i.quality', [ItemQuality::Poor->value, ItemQuality::Epic->value])
            ->groupBy('i.quality')->selectRaw('i.quality as quality, sum(d.quantity) as items')->pluck('items', 'quality');
        $best = $this->looted($characterId)->where('i.quality', '>=', ItemQuality::Uncommon->value)->groupBy('d.item_id', 'i.quality', 'i.item_level')
            ->selectRaw('d.item_id, (select n.name from names n where n.kind = ? and n.id = d.item_id order by n.votes desc, n.name limit 1) as name', [NameKind::Item->value])
            ->selectRaw('i.quality, i.item_level, sum(d.quantity) as quantity')
            ->orderByDesc('i.quality')->orderByRaw('i.item_level desc nulls last')->orderBy('d.item_id')->limit(self::SHOWN)->get();

        return [
            'qualities' => array_map(fn (mixed $count): int => (int) $count, $qualities->all()),
            'best' => array_values($best->map(fn (object $row): array => [
                'item_id' => (int) $row->item_id, 'name' => (string) ($row->name ?? 'Item '.$row->item_id), 'quality' => (int) $row->quality,
                'item_level' => $row->item_level === null ? null : (int) $row->item_level, 'quantity' => (int) $row->quantity,
            ])->all()),
        ];
    }

    private function looted(int $characterId): Builder
    {
        return DB::table('loot_drops as d')->join('items as i', 'i.item_id', '=', 'd.item_id')->where('d.character_id', $characterId)->where('d.counted', true)
            ->where(function (Builder $query): void {
                $query->where('d.via', LootVia::Window->value)->orWhere(function (Builder $receipt): void {
                    $receipt->where('d.via', LootVia::Receipt->value)->whereNotNull('d.source_kind')->whereNotExists(function (Builder $window): void {
                        $window->selectRaw('1')->from('loot_drops as w')->whereColumn('w.character_id', 'd.character_id')->whereColumn('w.session_start', 'd.session_start')
                            ->where('w.via', LootVia::Window->value);
                    });
                });
            });
    }
}
