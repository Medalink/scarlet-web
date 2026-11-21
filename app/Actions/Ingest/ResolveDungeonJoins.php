<?php

namespace App\Actions\Ingest;

use Illuminate\Support\Facades\DB;

class ResolveDungeonJoins
{
    /** A return to the same instance within this long is the same run (docs/ROWS.md, joining sessions). */
    private const int SAME_RUN_MS = 1_800_000;

    /**
     * Decide whether the first dungeon run of this session, and of the next session that has runs, carries on
     * the last run before it. Deciding both makes the result the same whatever order the sessions arrive in.
     */
    public function handle(int $characterId, int $sessionStart): void
    {
        $this->decide($characterId, $sessionStart);

        $next = DB::table('dungeon_runs')->where('character_id', $characterId)->where('session_start', '>', $sessionStart)->min('session_start');
        if ($next !== null) {
            $this->decide($characterId, (int) $next);
        }
    }

    private function decide(int $characterId, int $sessionStart): void
    {
        $first = DB::table('dungeon_runs')
            ->where('character_id', $characterId)
            ->where('session_start', $sessionStart)
            ->where('run_index', 0)
            ->selectRaw('instance_id, (extract(epoch from at) * 1000)::bigint as at_ms')
            ->first();
        if ($first === null) {
            return;
        }

        $previous = DB::table('dungeon_runs')
            ->where('character_id', $characterId)
            ->where('session_start', '<', $sessionStart)
            ->orderByDesc('session_start')
            ->orderByDesc('run_index')
            ->selectRaw('instance_id, (extract(epoch from left_at) * 1000)::bigint as left_ms')
            ->first();

        $joins = $previous !== null
            && $previous->left_ms !== null
            && (int) $previous->instance_id === (int) $first->instance_id
            && (int) $first->at_ms - (int) $previous->left_ms < self::SAME_RUN_MS;

        DB::table('dungeon_runs')
            ->where('character_id', $characterId)
            ->where('session_start', $sessionStart)
            ->where('run_index', 0)
            ->update(['continues_previous' => $joins]);
    }
}
