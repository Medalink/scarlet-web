<?php

namespace App\Actions\Public\Character;

use App\Support\NameKind;
use Illuminate\Support\Facades\DB;

class ReputationGoals
{
    public const int EXALTED_TIER = 8;

    private const int EXALTED_STANDING = 42000;

    private const array BELOW_NEUTRAL = [[-42000, -6000], [-6000, -3000], [-3000, 0]];

    /**
     * The latest standing with each faction, as the Reputation of the app's Character Overview: its name, tier (1 Hated
     * to 8 Exalted) and standing, with the range the standing is held to, most standing first.
     *
     * @return list<array{id: int, name: string, tier: int, standing: int, from: int, to: int}>
     */
    public function handle(int $characterId): array
    {
        $rows = DB::table('reputation_standings as r')->where('r.character_id', $characterId)->where('r.counted', true)
            ->selectRaw('distinct on (r.faction_id) r.faction_id, r.reaction, r.standing, r.floor, r.ceiling, (select n.name from names n where n.kind = ? and n.id = r.faction_id order by n.votes desc, n.name limit 1) as name', [NameKind::Faction->value])
            ->orderBy('r.faction_id')->orderByDesc('r.at')->get();

        $reps = [];
        foreach ($rows as $row) {
            $tier = max(1, min(self::EXALTED_TIER, (int) $row->reaction));
            $floor = (int) $row->floor;
            $ceiling = (int) $row->ceiling;
            [$from, $to] = match (true) {
                $tier <= 3 && $floor < $ceiling => [$floor, $ceiling],
                $tier <= 3 => self::BELOW_NEUTRAL[$tier - 1],
                default => [0, self::EXALTED_STANDING],
            };
            $reps[] = [
                'id' => (int) $row->faction_id, 'name' => $row->name === null ? 'Faction '.$row->faction_id : (string) $row->name, 'tier' => $tier,
                'standing' => max($from, min($to, $row->standing === null ? $from : (int) $row->standing)), 'from' => $from, 'to' => $to,
            ];
        }
        usort($reps, fn (array $a, array $b): int => [$b['standing'], $a['name']] <=> [$a['standing'], $b['name']]);

        return $reps;
    }
}
