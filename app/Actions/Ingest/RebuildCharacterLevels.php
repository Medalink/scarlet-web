<?php

namespace App\Actions\Ingest;

use Illuminate\Support\Facades\DB;

class RebuildCharacterLevels
{
    /**
     * Rebuild the character's `character_levels` rows from all its play spans: one row per level with the
     * time spent at it and the time before it (the time to reach it), across sessions.
     *
     * @return array{from: int, until: int}|null epoch seconds the rollups must look at again: the old and
     *                                           the new rows together, null when the character had none
     */
    public function handle(int $characterId): ?array
    {
        $before = $this->extent($characterId);

        DB::delete('delete from character_levels where character_id = ?', [$characterId]);
        DB::insert(
            <<<'SQL'
            insert into character_levels (at, character_id, session_start, ruleset_id, level, zone_id, counted, reached, seconds, before_seconds, completed)
            select first_at, ?, first_session, first_ruleset, level, null, all_counted, level,
                   round(spent_ms / 1000.0)::int, round(before_ms / 1000.0)::int, level < max(level) over ()
            from (
                select level,
                       min(at) as first_at,
                       (array_agg(session_start order by at))[1] as first_session,
                       (array_agg(ruleset_id order by at))[1] as first_ruleset,
                       bool_and(counted) as all_counted,
                       sum(ms) as spent_ms,
                       coalesce(sum(sum(ms)) over (order by level rows between unbounded preceding and 1 preceding), 0) as before_ms
                from play_spans
                where character_id = ? and level is not null
                group by level
            ) as levels
            SQL,
            [$characterId, $characterId],
        );

        $after = $this->extent($characterId);
        $extents = array_values(array_filter([$before, $after]));
        if ($extents === []) {
            return null;
        }

        return ['from' => min(array_column($extents, 'from')), 'until' => max(array_column($extents, 'until'))];
    }

    /** @return array{from: int, until: int}|null */
    private function extent(int $characterId): ?array
    {
        $row = DB::table('character_levels')
            ->where('character_id', $characterId)
            ->selectRaw('extract(epoch from min(at)) as first, extract(epoch from max(at)) as last')
            ->first();

        return $row === null || $row->first === null ? null : ['from' => (int) floor((float) $row->first), 'until' => (int) ceil((float) $row->last)];
    }
}
