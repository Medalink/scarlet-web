<?php

namespace App\Support\Recap;

use App\Support\MovementMode;
use App\Support\SessionTitle;
use Illuminate\Support\Facades\DB;
use stdClass;

/** The titles of sessions from the rows alone, as BuildRecap sets them, without building the recaps: what a list of sessions needs. */
final class Headline
{
    /**
     * Four queries for all the sessions of one character.
     *
     * @param  list<stdClass>  $sessions  rows of `sessions`
     * @return array<int, array{rule: string, text: string}> the title of each session, by `session_start`
     */
    public static function titles(int $character, array $sessions): array
    {
        $starts = array_map(fn (stdClass $session): int => (int) $session->session_start, $sessions);
        $stretches = Stretches::readMany($character, $starts);
        $gathered = Counters::gatheredMany($character, $starts);
        $yards = DB::table('movement_stretches')->where('character_id', $character)->whereIn('session_start', $starts)
            ->whereIn('mode', [MovementMode::Run->value, MovementMode::Walk->value, MovementMode::Mount->value, MovementMode::Swim->value])
            ->selectRaw('session_start, sum(yards) as yards')->groupBy('session_start')->pluck('yards', 'session_start');

        $titles = [];
        foreach ($sessions as $session) {
            $start = (int) $session->session_start;
            $level = (int) $session->level_start;
            $titles[$start] = SessionTitle::make(
                [$level, max($level, (int) $session->level_end)],
                $stretches[$start]['zone'],
                $stretches[$start]['dungeon'],
                (int) $session->quests_done,
                (int) $session->kills,
                $gathered[$start] ?? [],
                (int) ($yards[$start] ?? 0) / 1760,
                count(array_unique(array_column($stretches[$start]['zones'], 'name'))),
                intdiv((int) $session->played_ms, 1000),
            );
        }

        return $titles;
    }
}
