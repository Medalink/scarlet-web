<?php

namespace App\Actions\Public\Character;

use App\Support\StateKind;
use Illuminate\Support\Facades\DB;

/** Where a character's recorded time went: seconds on each way of travel, in combat and in a group. */
class TimeSplits
{
    /**
     * @return array{moving: array<string, int>, combat: int, group: int}
     */
    public function handle(int $characterId): array
    {
        $moving = DB::table('movement_stretches')->where('character_id', $characterId)->where('counted', true)->whereNotNull('mode')
            ->groupBy('mode')->selectRaw('mode, coalesce(sum(seconds), 0) as seconds')->pluck('seconds', 'mode')->map(fn (mixed $seconds): int => (int) $seconds)->all();
        $flights = (int) intdiv((int) DB::table('flights')->where('character_id', $characterId)->where('counted', true)->sum('ms'), 1000);
        $combat = intdiv((int) DB::table('state_times')->where('character_id', $characterId)->where('counted', true)->where('kind', StateKind::Fight->value)->sum('ms'), 1000);
        $group = (int) DB::table('group_spans')->where('character_id', $characterId)->where('counted', true)->where('size', '>', 1)->sum(DB::raw('extract(epoch from (ended_at - at))'));

        return ['moving' => [...$moving, 'taxi' => $flights], 'combat' => $combat, 'group' => $group];
    }
}
