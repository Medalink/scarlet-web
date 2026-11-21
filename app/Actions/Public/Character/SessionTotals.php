<?php

namespace App\Actions\Public\Character;

use Illuminate\Support\Facades\DB;

class SessionTotals
{
    /**
     * What a character's counted sessions add up to, and its longest session and its session with the most kills.
     * `at` is the session's start in UTC.
     *
     * @return array{kills: int, deaths: int, quests: int, distance: float, longest: array{seconds: float, at: string}|null, busiest: array{kills: int, at: string}|null, questiest: array{quests: int, at: string}|null}
     */
    public function handle(int $characterId): array
    {
        $sessions = fn () => DB::table('sessions')->where('character_id', $characterId)->where('counted', true);
        $sum = $sessions()->selectRaw('coalesce(sum(kills), 0) as kills, coalesce(sum(deaths), 0) as deaths, coalesce(sum(quests_done), 0) as quests, coalesce(sum(distance), 0) as distance')->first();
        $longest = $sessions()->where('played_ms', '>', 0)->orderByDesc('played_ms')->orderBy('start_at')->first(['played_ms', 'start_at']);
        $busiest = $sessions()->where('kills', '>', 0)->orderByDesc('kills')->orderBy('start_at')->first(['kills', 'start_at']);
        $questiest = $sessions()->where('quests_done', '>', 0)->orderByDesc('quests_done')->orderBy('start_at')->first(['quests_done', 'start_at']);

        return [
            'kills' => (int) ($sum->kills ?? 0), 'deaths' => (int) ($sum->deaths ?? 0), 'quests' => (int) ($sum->quests ?? 0), 'distance' => (float) ($sum->distance ?? 0),
            'longest' => $longest === null ? null : ['seconds' => $longest->played_ms / 1000, 'at' => (string) $longest->start_at],
            'busiest' => $busiest === null ? null : ['kills' => (int) $busiest->kills, 'at' => (string) $busiest->start_at],
            'questiest' => $questiest === null ? null : ['quests' => (int) $questiest->quests_done, 'at' => (string) $questiest->start_at],
        ];
    }
}
