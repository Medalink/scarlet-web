<?php

namespace App\Actions\Public\Character;

use App\Actions\Public\QuestRecord;
use Illuminate\Support\Facades\DB;

class QuestsDone
{
    public const int SHOWN = 5;

    /**
     * @return array{total: int, zones: list<array{id: int, name: string, quests: int, linked: bool}>, others: int, levels: array<int, int>}
     */
    public function handle(int $characterId): array
    {
        $turned = fn () => DB::table('quest_events')->where('character_id', $characterId)->where('counted', true)->where('event', QuestRecord::TURNED_IN);
        $zones = $turned()->whereNotNull('zone_id')->groupBy('zone_id')
            ->selectRaw('zone_id, (select z.name from zones z where z.id = zone_id) as name, count(*) as quests')
            ->selectRaw('(exists (select 1 from zones z where z.id = zone_id) or exists (select 1 from map_rects m where m.ui_map_id = zone_id)) as linked')
            ->orderByRaw('count(*) desc')->orderBy('zone_id')->get();
        $levels = $turned()->whereNotNull('level')->groupBy('level')->orderBy('level')->selectRaw('level, count(*) as quests')->pluck('quests', 'level');

        return [
            'total' => (int) $turned()->count(),
            'zones' => array_values($zones->take(self::SHOWN)->map(fn (object $row): array => [
                'id' => (int) $row->zone_id, 'name' => (string) ($row->name ?? 'Zone '.$row->zone_id), 'quests' => (int) $row->quests, 'linked' => (bool) $row->linked,
            ])->all()),
            'others' => max(0, $zones->count() - self::SHOWN),
            'levels' => array_map(fn (mixed $count): int => (int) $count, $levels->all()),
        ];
    }
}
