<?php

namespace App\Actions\Ingest;

use Illuminate\Support\Facades\DB;

/**
 * Sets `sessions.distance` from the placed path points of the sessions it is given: the summed length of the steps
 * between consecutive points, in world widths (the world is 3:2, so a step down counts two thirds). A step longer
 * than `MAX_STEP` is a teleport or a loading screen, not walking, and is left out.
 * A session with no step that counts is measured as 0,
 * so `maps:place-points` does not come back to it.
 */
class MeasureDistance
{
    /** The longest step that counts, in world widths (about 50 world units). */
    public const float MAX_STEP = 0.05;

    /**
     * Measure one session.
     */
    public function handle(int $characterId, int $sessionStart): void
    {
        DB::update(
            'update sessions set distance = (
                select coalesce(sum(step), 0) from (
                    select sqrt(power(u - lag(u) over w, 2) + power((v - lag(v) over w) * (2.0 / 3.0), 2)) as step
                    from path_points
                    where character_id = ? and session_start = ? and u is not null
                    window w as (order by at)
                ) steps where step <= ?
            ) where character_id = ? and session_start = ?',
            [$characterId, $sessionStart, self::MAX_STEP, $characterId, $sessionStart],
        );
    }
}
