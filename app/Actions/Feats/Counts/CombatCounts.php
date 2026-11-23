<?php

namespace App\Actions\Feats\Counts;

use App\Actions\Public\Character\Records;
use App\Models\Character;
use App\Support\Feat;
use Illuminate\Support\Facades\DB;

/** The combat feats: kills, deaths and the zones and killers of both. */
class CombatCounts
{
    private const int OWN_CREDIT = 1;

    private const int ROAMER_ZONE_KILLS = 100;

    public function __construct(private readonly Records $records) {}

    /**
     * One count per combat feat, keyed by the enum value; null when the feat does not apply to the character.
     *
     * @return array<string, int|float|null>
     */
    public function handle(Character $character): array
    {
        $id = (int) $character->id;
        $sessions = DB::table('sessions')->where('character_id', $id)->where('counted', true)
            ->selectRaw('coalesce(sum(kills), 0) as kills, coalesce(sum(deaths), 0) as deaths, coalesce(max(kills), 0) as rampage')->first();
        $kills = DB::table('kills')->where('character_id', $id)->where('counted', true)
            ->selectRaw('count(distinct npc_id) filter (where not player) as bestiary')
            ->selectRaw('count(*) filter (where not player and credit = ?) as finisher', [self::OWN_CREDIT])
            ->selectRaw('count(*) filter (where player) as gladiator')->first();
        $zones = DB::table('kills')->where('character_id', $id)->where('counted', true)->where('player', false)->whereNotNull('zone_id')
            ->groupBy('zone_id')->selectRaw('zone_id, count(*) as kills')->pluck('kills', 'zone_id');
        $killers = DB::table('deaths')->where('character_id', $id)->where('counted', true)->whereNotNull('killer')
            ->groupBy('killer')->selectRaw('killer, count(*) as deaths')->pluck('deaths', 'killer');
        $blow = $this->records->handle($id)['blow'];

        return [
            Feat::Slayer->value => (int) ($sessions->kills ?? 0),
            Feat::Bestiary->value => (int) ($kills->bestiary ?? 0),
            Feat::Finisher->value => (int) ($kills->finisher ?? 0),
            Feat::Gladiator->value => (int) ($kills->gladiator ?? 0),
            Feat::Rampage->value => (int) ($sessions->rampage ?? 0),
            Feat::Streak->value => $this->streak($id),
            Feat::Fallen->value => (int) ($sessions->deaths ?? 0),
            Feat::Flattened->value => $blow === null ? 0 : (int) $blow['damage'],
            Feat::Nemesis->value => (int) $killers->max(),
            Feat::Territorial->value => (int) $zones->max(),
            Feat::Roamer->value => $zones->filter(fn (mixed $zoneKills): bool => (int) $zoneKills >= self::ROAMER_ZONE_KILLS)->count(),
        ];
    }

    /** The most kills in a row between two deaths (or before the first), over kills and deaths in time order; a kill and a death in one second count the kill first. */
    private function streak(int $characterId): int
    {
        $row = DB::selectOne(
            'select coalesce(max(run), 0) as best from (
                select count(*) filter (where k = 1) as run from (
                    select k, count(*) filter (where k = 0) over (order by at, k desc rows unbounded preceding) as deaths_so_far from (
                        select at, 1 as k from kills where character_id = ? and counted
                        union all
                        select at, 0 as k from deaths where character_id = ? and counted
                    ) as events
                ) as numbered group by deaths_so_far
            ) as runs',
            [$characterId, $characterId],
        );

        return (int) ($row->best ?? 0);
    }
}
