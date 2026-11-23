<?php

namespace App\Actions\Sessions;

use App\Models\Character;
use App\Support\Recap\Headline;
use Illuminate\Support\Facades\DB;

/** Up to `$limit` sessions of one character, newest first, each with its title. `$after` is the start of the oldest session of the page before. */
class ListCharacterSessions
{
    /**
     * @return array{sessions: list<array<string, mixed>>, next: string|null}
     */
    public function handle(Character $character, ?int $after, int $limit): array
    {
        $rows = DB::table('sessions')->where('character_id', $character->id)->where('played_ms', '>=', ShowSession::MIN_PLAYED_MS)
            ->when($after !== null, fn ($query) => $query->where('session_start', '<', $after))
            ->orderByDesc('session_start')->limit($limit + 1)->get();
        $page = $rows->take($limit);
        $titles = Headline::titles($character->id, array_values($page->all()));
        $sessions = array_values($page->map(fn (object $row): array => [
            'start' => (int) $row->session_start,
            'end' => $row->end_at === null ? (int) $row->session_start + intdiv((int) $row->played_ms, 1000) : (int) strtotime((string) $row->end_at),
            'played' => intdiv((int) $row->played_ms, 1000),
            'level' => [(int) $row->level_start, max((int) $row->level_start, (int) $row->level_end)],
            'title' => $titles[(int) $row->session_start],
            'kills' => $row->kills === null ? null : (int) $row->kills,
            'quests' => $row->quests_done === null ? null : (int) $row->quests_done,
            'deaths' => $row->deaths === null ? null : (int) $row->deaths,
            'xp' => $row->xp_total === null ? null : (int) $row->xp_total,
        ])->all());

        return ['sessions' => $sessions, 'next' => $rows->count() > $limit ? (string) $sessions[count($sessions) - 1]['start'] : null];
    }
}
