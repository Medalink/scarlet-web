<?php

namespace App\Actions\Ingest;

use Illuminate\Support\Facades\DB;

class RebuildCharacterLevels
{
    /**
     * Rebuild the character's `character_levels` rows from all its play spans: one row per level with the
     * time spent at it and the time before it (the time to reach it), across sessions. Where the session the
     * level began in has the client's played counter, both come from it (the counter at the level's first span,
     * and up to the next level's, or the end of the last session), so they include the reloads between sessions;
     * otherwise from the sum of the spans.
     * A level of a character whose first recorded level is above 1 counts only where the played counter gives its
     * time, since the sum of the spans misses all the play before the recording began.
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
            select first_at, ?, first_session, first_ruleset, level, null, all_counted and (min(level) over () = 1 or anchor_ms is not null), level,
                   round(case when anchor_ms is not null and next_ms is not null then (next_ms - anchor_ms) / 1000.0 else spent_ms / 1000.0 end)::int,
                   round(coalesce(anchor_ms, before_ms) / 1000.0)::int,
                   level < max(level) over ()
            from (
                select *,
                       coalesce(lead(anchor_ms) over (order by level), case when level = max(level) over () then final_ms end) as next_ms
                from (
                    select l.*,
                           case when s.counter_start_ms is not null
                               then s.counter_start_ms + greatest(extract(epoch from (l.first_at - s.start_at)) * 1000, 0)
                           end as anchor_ms,
                           coalesce(sum(l.spent_ms) over (order by l.level rows between unbounded preceding and 1 preceding), 0) as before_ms,
                           (select e.counter_end_ms from sessions e where e.character_id = ? and e.counter_end_ms is not null order by e.session_start desc limit 1) as final_ms
                    from (
                        select level,
                               min(at) as first_at,
                               (array_agg(session_start order by at))[1] as first_session,
                               (array_agg(ruleset_id order by at))[1] as first_ruleset,
                               bool_and(counted) as all_counted,
                               sum(ms) as spent_ms
                        from play_spans
                        where character_id = ? and level is not null
                        group by level
                    ) as l
                    left join sessions s on s.character_id = ? and s.session_start = l.first_session
                ) as anchored
            ) as levels
            SQL,
            [$characterId, $characterId, $characterId, $characterId],
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
