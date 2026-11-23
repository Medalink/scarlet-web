<?php

namespace App\Actions\Sessions;

use App\Models\Character;
use App\Support\Recap\Headline;
use Illuminate\Support\Facades\DB;

/** A page of a character's sessions, newest first, with the title each one has. */
class ListSessions
{
    public const int PAGE = 20;

    /**
     * Null when the character is unknown or its session pages are not public. `$after` is the start of the oldest session of the page before.
     *
     * @return array{sessions: list<array<string, mixed>>, next: string|null}|null
     */
    public function handle(string $name, ?int $after): ?array
    {
        $character = Character::query()->where('name_key', Character::keyFor($name))->first();
        if ($character === null || ! $character->sessionsVisibleTo(null)) {
            return null;
        }
        $rows = DB::table('sessions')->where('character_id', $character->id)->where('played_ms', '>=', ShowSession::MIN_PLAYED_MS)
            ->when($after !== null, fn ($query) => $query->where('session_start', '<', $after))
            ->orderByDesc('session_start')->limit(self::PAGE + 1)->get();
        $page = $rows->take(self::PAGE);
        $titles = Headline::titles($character->id, array_values($page->all()));
        $sessions = $page->map(fn (object $row): array => [
            'start' => (int) $row->session_start,
            'end' => $row->end_at === null ? (int) $row->session_start + intdiv((int) $row->played_ms, 1000) : (int) strtotime((string) $row->end_at),
            'played' => intdiv((int) $row->played_ms, 1000),
            'level' => [(int) $row->level_start, max((int) $row->level_start, (int) $row->level_end)],
            'title' => $titles[(int) $row->session_start],
            'kills' => $row->kills === null ? null : (int) $row->kills,
            'quests' => $row->quests_done === null ? null : (int) $row->quests_done,
            'deaths' => $row->deaths === null ? null : (int) $row->deaths,
            'xp' => $row->xp_total === null ? null : (int) $row->xp_total,
        ])->all();

        $sessions = array_values($sessions);

        return ['sessions' => $sessions, 'next' => $rows->count() > self::PAGE ? (string) $sessions[count($sessions) - 1]['start'] : null];
    }
}
