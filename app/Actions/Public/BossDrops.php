<?php

namespace App\Actions\Public;

use Illuminate\Support\Facades\DB;

class BossDrops
{
    /** One drop reaches every uploader in the group, within seconds: rows of an encounter and item this close are one drop. */
    private const int GROUP_WINDOW_SECONDS = 600;

    /**
     * What boss encounters dropped, counted once per uploader group (`boss_loot`, from `EL`). Within a window, the
     * uploader who saw most of an encounter and item gives its count; the windows add up. Rows are kept only
     * where enough characters uploaded them. The raw SQL is the window arithmetic the query builder has no word for.
     *
     * @param  int|null  $itemId  only drops of this item
     * @param  int|null  $encounterId  only drops of this encounter
     * @return list<array{encounter_id: int, item_id: int, dropped: int, characters: int}> most dropped first
     */
    public function handle(?int $itemId, ?int $encounterId, int $limit = 50): array
    {
        $rows = fn () => DB::table('boss_loot')->where('counted', true)->whereNotNull('encounter_id')->whereNotNull('item_id')
            ->when($itemId !== null, fn ($query) => $query->where('item_id', $itemId))
            ->when($encounterId !== null, fn ($query) => $query->where('encounter_id', $encounterId));

        $perUploader = $rows()
            ->selectRaw('encounter_id, item_id, floor(extract(epoch from at) / ?) as bucket, sum(coalesce(quantity, 1)) as n', [self::GROUP_WINDOW_SECONDS])
            ->groupByRaw('encounter_id, item_id, character_id, bucket');
        $perDrop = DB::query()->fromSub($perUploader, 'u')
            ->selectRaw('encounter_id, item_id, bucket, max(n) as n')
            ->groupBy('encounter_id', 'item_id', 'bucket');
        $uploaders = $rows()
            ->selectRaw('encounter_id, item_id, count(distinct character_id) as characters')
            ->groupBy('encounter_id', 'item_id')
            ->havingRaw('count(distinct character_id) >= ?', [(int) config('scarlet.min_characters')]);

        $answer = DB::query()->fromSub($perDrop, 'd')
            ->joinSub($uploaders, 'c', fn ($join) => $join->on('c.encounter_id', '=', 'd.encounter_id')->on('c.item_id', '=', 'd.item_id'))
            ->selectRaw('d.encounter_id, d.item_id, sum(d.n)::bigint as dropped, c.characters')
            ->groupBy('d.encounter_id', 'd.item_id', 'c.characters')
            ->orderByRaw('sum(d.n) desc')
            ->orderBy('d.encounter_id')
            ->orderBy('d.item_id')
            ->limit($limit)
            ->get();

        return array_values($answer->map(fn (object $row): array => [
            'encounter_id' => (int) $row->encounter_id,
            'item_id' => (int) $row->item_id,
            'dropped' => (int) $row->dropped,
            'characters' => (int) $row->characters,
        ])->all());
    }
}
