<?php

namespace App\Actions\Public;

use App\Http\Middleware\PublicApi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CreaturePoints
{
    /** More sightings than this are drawn as a density surface, not as dots. */
    public const int DOTS = 600;

    /** How far from a sighting, in seconds, a recorded path point may be to stand for where the player was. */
    private const int WINDOW = 10;

    /**
     * Where a creature was met, as world positions `[u, v]`. A sighting records the player's cell on the zone map, not the
     * creature's position, so a point is the nearest position the same character's path recorded on that map within
     * `WINDOW` seconds, else the middle of the sighting's cell. No time and no character comes with a point. Null when the
     * creature was met more than `DOTS` times or by fewer than `config('scarlet.min_characters')` characters.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return list<array{0: float, 1: float}>|null
     */
    public function handle(int $npcId, int|array|null $rulesetId): ?array
    {
        if ($rulesetId === []) {
            return null;
        }
        $key = 'creature-points:'.md5(json_encode([$npcId, $rulesetId], JSON_THROW_ON_ERROR));

        /** @var list<array{0: float, 1: float}>|null */
        return Cache::remember($key, PublicApi::MAX_AGE, function () use ($npcId, $rulesetId): ?array {
            $nearest = DB::table('path_points as q')
                ->select('q.u', 'q.v')
                ->whereColumn('q.character_id', 's.character_id')
                ->whereColumn('q.map', 's.map')
                ->whereNotNull('q.u')
                ->whereRaw('q.at between s.at - make_interval(secs => ?) and s.at + make_interval(secs => ?)', [self::WINDOW, self::WINDOW])
                ->orderByRaw('abs(extract(epoch from q.at - s.at))')
                ->limit(1);
            $rows = DB::table('creature_sightings as s')
                ->leftJoinLateral($nearest, 'p')
                ->selectRaw('s.character_id, coalesce(p.u, s.u) as u, coalesce(p.v, s.v) as v')
                ->where('s.npc_id', $npcId)
                ->where('s.counted', true)
                ->whereNotNull('s.u')
                ->when($rulesetId !== null, fn ($query) => $query->whereIn('s.ruleset_id', (array) $rulesetId))
                ->orderBy('s.at')
                ->limit(self::DOTS + 1)
                ->get()
                ->map(fn (object $row): array => (array) $row)
                ->all();
            $characters = count(array_unique(array_column($rows, 'character_id')));
            if (count($rows) > self::DOTS || $characters < (int) config('scarlet.min_characters')) {
                return null;
            }

            return array_map(fn (array $row): array => [round((float) $row['u'], 5), round((float) $row['v'], 5)], $rows);
        });
    }
}
