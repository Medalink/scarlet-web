<?php

namespace App\Actions\Public\Character;

use App\Support\ItemQuality;
use App\Support\NameKind;
use Illuminate\Support\Facades\DB;

class MostCrafted
{
    /** Items the page lists. */
    public const int SHOWN = 6;

    /**
     * The items a character crafted most, by the quantity made.
     *
     * @return list<array{item_id: int, name: string, quality: int, quantity: int}>
     */
    public function handle(int $characterId): array
    {
        $rows = DB::table('crafts as c')->leftJoin('items as i', 'i.item_id', '=', 'c.item_id')->where('c.character_id', $characterId)->where('c.counted', true)->whereNotNull('c.item_id')
            ->groupBy('c.item_id', 'i.quality')
            ->selectRaw('c.item_id, i.quality, sum(coalesce(c.quantity, 1))::bigint as quantity, (select n.name from names n where n.kind = ? and n.id = c.item_id order by n.votes desc, n.name limit 1) as name', [NameKind::Item->value])
            ->orderByRaw('sum(coalesce(c.quantity, 1)) desc')->orderBy('c.item_id')->limit(self::SHOWN)->get();

        return array_values($rows->map(fn (object $row): array => [
            'item_id' => (int) $row->item_id, 'name' => (string) ($row->name ?? 'Item '.$row->item_id),
            'quality' => $row->quality === null ? ItemQuality::Common->value : (int) $row->quality, 'quantity' => (int) $row->quantity,
        ])->all());
    }
}
