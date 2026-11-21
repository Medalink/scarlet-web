<?php

namespace App\Actions\Public\Character;

use App\Support\GameObjects;
use App\Support\KillerKind;
use App\Support\LootSourceKind;
use App\Support\PlayerNameShape;
use Illuminate\Support\Facades\DB;

class Records
{
    /**
     * The facts a character holds a record in, each null or zero when nothing was recorded. The killer of the biggest
     * blow is a creature's name, and null for a player or a source the game did not identify. `nodes` counts
     * gathering windows opened on an object other than the fishing bobber, `fish` those opened on the bobber.
     *
     * @return array{
     *     blow: array{damage: int, level: int|null, zone_id: int|null, zone: string|null, killer: string|null}|null,
     *     slowest: array{level: int, seconds: int}|null,
     *     nodes: int, fish: int, afk: float
     * }
     */
    public function handle(int $characterId): array
    {
        $blow = DB::table('deaths')->where('character_id', $characterId)->where('damage', '>', 0)->orderByDesc('damage')->orderBy('at')
            ->selectRaw('damage, level, zone_id, (select z.name from zones z where z.id = deaths.zone_id) as zone, killer_kind, killer')->first();
        $slowest = DB::table('character_levels')->where('character_id', $characterId)->where('counted', true)->where('completed', true)->whereNotNull('level')
            ->where('seconds', '>', 0)->orderByDesc('seconds')->orderBy('level')->first(['level', 'seconds']);
        $gathered = DB::table('loot_opens')->where('character_id', $characterId)->where('counted', true)->where('source_kind', LootSourceKind::Object->value)
            ->selectRaw('count(*) filter (where source_id is distinct from ?) as nodes, count(*) filter (where source_id = ?) as fish', [GameObjects::FISHING_BOBBER, GameObjects::FISHING_BOBBER])->first();
        $afk = DB::table('play_spans')->where('character_id', $characterId)->where('counted', true)->sum('afk_ms');

        return [
            'blow' => $blow === null ? null : [
                'damage' => (int) $blow->damage, 'level' => $blow->level === null ? null : (int) $blow->level,
                'zone_id' => $blow->zone_id === null ? null : (int) $blow->zone_id, 'zone' => $blow->zone, 'killer' => $this->creature($blow->killer, $blow->killer_kind),
            ],
            'slowest' => $slowest === null ? null : ['level' => (int) $slowest->level, 'seconds' => (int) $slowest->seconds],
            'nodes' => (int) ($gathered->nodes ?? 0),
            'fish' => (int) ($gathered->fish ?? 0),
            'afk' => (float) $afk / 1000,
        ];
    }

    private function creature(?string $killer, ?string $kind): ?string
    {
        if ($killer === null || $kind === KillerKind::Player->value || PlayerNameShape::matches($killer)) {
            return null;
        }

        return $killer;
    }
}
