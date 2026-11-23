<?php

namespace App\Actions\Feats\Counts;

use App\Models\Character;
use App\Support\Feat;
use App\Support\InstanceType;
use Illuminate\Support\Facades\DB;

/** The dungeon feats: runs, bosses and the time inside. */
class DungeonCounts
{
    private const int HOUR_MS = 3_600_000;

    private const int SOLO = 1;

    /**
     * One count per dungeon feat, keyed by the enum value; null when the feat does not apply to the character.
     *
     * @return array<string, int|float|null>
     */
    public function handle(Character $character): array
    {
        $id = (int) $character->id;
        $runs = DB::table('dungeon_runs')->where('character_id', $id)->where('counted', true)
            ->selectRaw('count(*) filter (where not continues_previous) as delver')
            ->selectRaw('coalesce(sum(bosses), 0) as vanquisher')
            ->selectRaw('count(distinct instance_id) as spelunker')
            ->selectRaw('count(*) filter (where instance_type = ? and not continues_previous) as raider', [InstanceType::Raid->value])
            ->selectRaw('coalesce(sum(bosses) filter (where instance_type = ?), 0) as conqueror', [InstanceType::Raid->value])
            ->selectRaw('coalesce(sum(inside_ms), 0) as inside')
            ->selectRaw('count(*) filter (where not continues_previous and bosses > 0 and deaths = 0) as flawless')
            ->selectRaw('count(*) filter (where not continues_previous and group_size = ?) as soloist', [self::SOLO])->first();
        $familiar = DB::table('dungeon_runs')->where('character_id', $id)->where('counted', true)->where('continues_previous', false)
            ->groupBy('instance_id')->selectRaw('instance_id, count(*) as runs')->pluck('runs', 'instance_id');

        return [
            Feat::Delver->value => (int) ($runs->delver ?? 0),
            Feat::Vanquisher->value => (int) ($runs->vanquisher ?? 0),
            Feat::Spelunker->value => (int) ($runs->spelunker ?? 0),
            Feat::Raider->value => (int) ($runs->raider ?? 0),
            Feat::Conqueror->value => (int) ($runs->conqueror ?? 0),
            Feat::DeepDweller->value => intdiv((int) ($runs->inside ?? 0), self::HOUR_MS),
            Feat::Flawless->value => (int) ($runs->flawless ?? 0),
            Feat::Familiar->value => (int) $familiar->max(),
            Feat::Soloist->value => (int) ($runs->soloist ?? 0),
        ];
    }
}
