<?php

namespace App\Actions\Ingest;

use Illuminate\Support\Facades\DB;

class FlagCompleteLevelMoney
{
    /**
     * Flag each of the character's money rows with whether its level is complete (docs/DESIGN.md, "Complete
     * levels"): the ruleset gold rollup averages whole levels only, so a row at a level the character was first
     * seen at, is still in, or played unrecorded time in is not complete. The character's own gold rollup reads
     * every row, like `counted`. Runs after RebuildCharacterLevels, whose rows the flags come from. Only rows
     * whose flag changes are written, and the days they span come back so the rollups look at them again.
     *
     * @return array{from: int, until: int}|null epoch seconds the rollups must look at again, null when no flag changed
     */
    public function handle(int $characterId): ?array
    {
        $extent = DB::selectOne(<<<'SQL'
            with changed as (
                update money_changes m
                set level_complete = coalesce((
                    select cl.completed from character_levels cl
                    where cl.character_id = m.character_id and cl.reached = m.level limit 1
                ), false)
                where m.character_id = ?
                    and m.level_complete is distinct from coalesce((
                        select cl.completed from character_levels cl
                        where cl.character_id = m.character_id and cl.reached = m.level limit 1
                    ), false)
                returning m.at
            )
            select extract(epoch from min(at)) as first, extract(epoch from max(at)) as last from changed
            SQL, [$characterId]);

        return $extent === null || $extent->first === null ? null : [
            'from' => (int) floor((float) $extent->first),
            'until' => (int) ceil((float) $extent->last),
        ];
    }
}
