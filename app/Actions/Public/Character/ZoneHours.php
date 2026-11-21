<?php

namespace App\Actions\Public\Character;

use Illuminate\Support\Facades\DB;

class ZoneHours
{
    /** Zones the page lists; the rest are counted. */
    public const int SHOWN = 8;

    /** Zones read at most: a character plays in far fewer. */
    private const int READ = 500;

    /**
     * The played time of a character by zone, longest first. `linked` is true for a zone that has a page.
     *
     * @return array{zones: list<array{id: int, name: string, seconds: float, linked: bool}>, others: int, other_seconds: float}
     */
    public function handle(int $characterId): array
    {
        $rows = DB::table('play_spans')->where('character_id', $characterId)->where('counted', true)->whereNotNull('zone_id')->groupBy('zone_id')
            ->selectRaw('zone_id, coalesce(max(zone_name), (select z.name from zones z where z.id = zone_id)) as name, (sum(ms) / 1000.0)::float as seconds')
            ->selectRaw('(exists (select 1 from zones z where z.id = zone_id) or exists (select 1 from map_rects m where m.ui_map_id = zone_id)) as linked')
            ->orderByRaw('sum(ms) desc')->orderBy('zone_id')->limit(self::READ)->get();
        $zones = array_values($rows->map(fn (object $row): array => [
            'id' => (int) $row->zone_id, 'name' => (string) ($row->name ?? 'Zone '.$row->zone_id), 'seconds' => (float) $row->seconds, 'linked' => (bool) $row->linked,
        ])->all());
        $others = array_slice($zones, self::SHOWN);

        return [
            'zones' => array_slice($zones, 0, self::SHOWN),
            'others' => count($others),
            'other_seconds' => array_sum(array_column($others, 'seconds')),
        ];
    }
}
