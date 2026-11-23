<?php

namespace App\Actions\Public;

use App\Models\Character;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CharacterBattlegrounds
{
    /** Matches the page lists, the newest first. */
    public const int MATCHES = 10;

    private const int NAMES_PER_QUERY = 10_000;

    /**
     * The matches of a character whose scoreboard its client read (`BO` rows), the newest first, each with every
     * player of the scoreboard. A player's name is in the answer for the character's owner, and for a visitor only
     * when that player's own character is public; any other row has a null name. Null when the character is unknown
     * or private to this viewer.
     *
     * @return array{character: Character, owner: bool, matches: list<array<string, mixed>>}|null
     */
    public function handle(string $name, ?int $viewerAccountId): ?array
    {
        $character = Character::query()->where('name_key', Character::keyFor($name))->first();
        if ($character === null || ! $character->visibleTo($viewerAccountId)) {
            return null;
        }
        $owner = $viewerAccountId !== null && $character->account_id === $viewerAccountId;

        $stamps = DB::table('battleground_scores')->where('character_id', $character->id)
            ->select('match_at')->distinct()->orderByDesc('match_at')->limit(self::MATCHES)->pluck('match_at')->map(fn (mixed $at): string => (string) $at)->all();
        /** @var array<string, list<array<string, mixed>>> $rows */
        $rows = DB::table('battleground_scores')->where('character_id', $character->id)->whereIn('match_at', $stamps)
            ->orderBy('faction')->orderByDesc('killing_blows')->orderByDesc('honor_gained')->orderBy('name')
            ->get(['match_at', 'name', 'faction', 'class_file', 'killing_blows', 'honorable_kills', 'deaths', 'honor_gained', 'damage_done', 'healing_done'])
            ->map(fn (object $row): array => (array) $row)->groupBy('match_at')->map(fn (Collection $group): array => $group->all())->all();
        /** @var array<string, array<string, mixed>> $ended */
        $ended = DB::table('battleground_matches')->where('character_id', $character->id)->whereIn('at', $stamps)
            ->get(['at', 'zone_name', 'result', 'arena'])->map(fn (object $row): array => (array) $row)->keyBy('at')->all();

        $names = [];
        foreach ($rows as $group) {
            foreach ($group as $row) {
                $names[] = Character::keyFor((string) $row['name']);
            }
        }
        $public = [];
        if (! $owner && $names !== []) {
            foreach (array_chunk(array_values(array_unique($names)), self::NAMES_PER_QUERY) as $chunk) {
                $known = Character::query()->whereIn('name_key', $chunk)->where('is_public', true)->pluck('name_key')->all();
                $public += array_fill_keys($known, 1);
            }
        }

        $matches = [];
        foreach ($stamps as $stamp) {
            $stamp = (string) $stamp;
            $matches[] = [
                'at' => Carbon::parse($stamp, 'UTC'),
                'zone' => $ended[$stamp]['zone_name'] ?? null,
                'result' => $ended[$stamp]['result'] ?? null,
                'arena' => $ended[$stamp]['arena'] ?? null,
                'rows' => array_map(function (array $row) use ($owner, $public): array {
                    $key = $row['name'] === null ? null : Character::keyFor((string) $row['name']);
                    $shown = $key !== null && isset($public[$key]);

                    return [
                        'name' => $key !== null && ($owner || $shown) ? (string) $row['name'] : null,
                        'public' => $shown,
                        'faction' => $row['faction'],
                        'class' => $row['class_file'],
                        'killing_blows' => $row['killing_blows'],
                        'honorable_kills' => $row['honorable_kills'],
                        'deaths' => $row['deaths'],
                        'honor_gained' => $row['honor_gained'],
                        'damage_done' => $row['damage_done'],
                        'healing_done' => $row['healing_done'],
                    ];
                }, $rows[$stamp] ?? []),
            ];
        }

        return ['character' => $character, 'owner' => $owner, 'matches' => $matches];
    }
}
