<?php

namespace App\Support\Recap;

use App\Support\MovementMode;
use App\Support\SessionTitle;
use stdClass;

/** The title of a session from the rows alone, as BuildRecap sets it, without building the recap: what a list of sessions needs. */
final class Headline
{
    /** @return array{rule: string, text: string} */
    public static function title(Scope $scope, stdClass $session): array
    {
        $stretches = Stretches::read($scope);
        $yards = (int) $scope->rows('movement_stretches')
            ->whereIn('mode', [MovementMode::Run->value, MovementMode::Walk->value, MovementMode::Mount->value, MovementMode::Swim->value])->sum('yards');
        $start = (int) $session->level_start;

        return SessionTitle::make(
            [$start, max($start, (int) $session->level_end)],
            $stretches['zone'],
            $stretches['dungeon'],
            (int) $session->quests_done,
            (int) $session->kills,
            Counters::gathered($scope),
            $yards / 1760,
            count(array_unique(array_column($stretches['zones'], 'name'))),
            intdiv((int) $session->played_ms, 1000),
        );
    }
}
