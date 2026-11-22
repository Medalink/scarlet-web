<?php

namespace App\Actions\Feats\Counts;

use App\Models\Character;
use App\Support\Feat;
use App\Support\MovementMode;
use Illuminate\Support\Facades\DB;

/** The world feats: zones entered and lived in, distance walked and the movement modes. */
class WorldCounts
{
    private const int HOUR_MS = 3_600_000;

    /**
     * One count per world feat, keyed by the enum value; null when the feat does not apply to the character.
     *
     * @return array<string, int|float|null>
     */
    public function handle(Character $character): array
    {
        $id = (int) $character->id;
        $zones = DB::table('play_spans')->where('character_id', $id)->where('counted', true)->whereNotNull('zone_id')
            ->groupBy('zone_id')->selectRaw('zone_id, sum(ms) as ms')->pluck('ms', 'zone_id');
        $moved = DB::table('movement_stretches')->where('character_id', $id)->where('counted', true)
            ->selectRaw('coalesce(sum(jumps), 0) as jumps')
            ->selectRaw('coalesce(sum(yards) filter (where mode = ?), 0) as swam', [MovementMode::Swim->value])
            ->selectRaw('coalesce(sum(yards) filter (where mode = ?), 0) as run', [MovementMode::Run->value])
            ->selectRaw('coalesce(sum(yards) filter (where mode = ?), 0) as ride', [MovementMode::Mount->value])
            ->selectRaw('coalesce(sum(yards) filter (where mode = ?), 0) as stroll', [MovementMode::Walk->value])
            ->selectRaw('coalesce(sum(yards) filter (where mode = ?), 0) as fly', [MovementMode::Fly->value])->first();
        $distance = DB::table('sessions')->where('character_id', $id)->where('counted', true)
            ->selectRaw('coalesce(sum(distance), 0) as distance')->value('distance');

        return [
            Feat::Explorer->value => $zones->count(),
            Feat::Globetrotter->value => $zones->filter(fn (mixed $ms): bool => (int) $ms >= self::HOUR_MS)->count(),
            Feat::Local->value => intdiv((int) $zones->max(), self::HOUR_MS),
            Feat::Wayfarer->value => floor((float) $distance * 10) / 10,
            Feat::Jumper->value => (int) ($moved->jumps ?? 0),
            Feat::Swimmer->value => (int) ($moved->swam ?? 0),
            Feat::Runner->value => (int) ($moved->run ?? 0),
            Feat::Rider->value => (int) ($moved->ride ?? 0),
            Feat::Stroller->value => (int) ($moved->stroll ?? 0),
            Feat::Flyer->value => (int) ($moved->fly ?? 0),
        ];
    }
}
