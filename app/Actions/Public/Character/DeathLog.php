<?php

namespace App\Actions\Public\Character;

use App\Actions\Public\ResolvePlayerName;
use App\Support\KillerKind;
use App\Support\PlayerNameShape;
use Illuminate\Support\Facades\DB;

class DeathLog
{
    /** Deaths the log lists, newest first. */
    public const int SHOWN = 50;

    public function __construct(private readonly ResolvePlayerName $players) {}

    /**
     * Every death of a character by level, and its latest deaths with where and by what. `killer` is a creature's name,
     * a player's name only when ResolvePlayerName may show it, `ResolvePlayerName::UNKNOWN` for any other player, or
     * null when the death has no killer. A guild is never read.
     *
     * @return array{levels: array<int, int>, rows: list<array{level: int|null, zone_id: int|null, zone: string|null, killer: string|null, damage: int|null}>}
     */
    public function handle(int $characterId, bool $owner): array
    {
        $levels = DB::table('deaths')->where('character_id', $characterId)->whereNotNull('level')->groupBy('level')->selectRaw('level, count(*) as deaths')->pluck('deaths', 'level');
        $rows = DB::table('deaths')->where('character_id', $characterId)->orderByDesc('at')->limit(self::SHOWN)
            ->selectRaw('level, zone_id, (select z.name from zones z where z.id = deaths.zone_id) as zone, killer_kind, killer, damage')->get();
        $pseudonyms = [];
        foreach ($rows as $row) {
            if ($row->killer !== null && $row->killer_kind === KillerKind::Player->value) {
                $pseudonyms[] = (string) $row->killer;
            }
        }
        $names = $this->players->handle($characterId, $owner, $pseudonyms);

        return [
            'levels' => array_map(fn (mixed $count): int => (int) $count, $levels->all()),
            'rows' => array_values($rows->map(fn (object $row): array => [
                'level' => $row->level === null ? null : (int) $row->level,
                'zone_id' => $row->zone_id === null ? null : (int) $row->zone_id,
                'zone' => $row->zone,
                'killer' => $this->killer($row->killer, $row->killer_kind, $names),
                'damage' => $row->damage === null ? null : (int) $row->damage,
            ])->all()),
        ];
    }

    /** @param  array<string, string>  $names */
    private function killer(?string $killer, ?string $kind, array $names): ?string
    {
        if ($killer === null) {
            return null;
        }
        if ($kind === KillerKind::Player->value) {
            return $names[$killer] ?? ResolvePlayerName::UNKNOWN;
        }

        return PlayerNameShape::matches($killer) ? ResolvePlayerName::UNKNOWN : $killer;
    }
}
