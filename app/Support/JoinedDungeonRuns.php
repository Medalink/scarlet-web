<?php

namespace App\Support;

/**
 * The query text of a character's dungeon runs joined along `continues_previous` (docs/ROWS.md, joining sessions),
 * which reconcile reads for `run` claims and the audit checks them against.
 */
final class JoinedDungeonRuns
{
    /**
     * A common table expression `joined(character_id, instance_id, at, end_at, level, zone_id)`: one row per joined
     * run, its first entry and its last leave (the entry of the session's next run when the run has no `left_at`, a
     * direct move to another dungeon, and else the entry plus the time inside).
     *
     * @param  string  $characters  a condition on `r.character_id` with whatever `?` it needs
     */
    public static function cte(string $characters): string
    {
        return <<<SQL
            joined as (
                select numbered.character_id, min(numbered.instance_id) as instance_id, min(numbered.at) as at,
                    max(coalesce(numbered.left_at, numbered.next_at, numbered.at + numbered.inside_ms * interval '1 millisecond')) as end_at,
                    (array_agg(numbered.level order by numbered.at))[1] as level,
                    (array_agg(numbered.zone_id order by numbered.at))[1] as zone_id
                from (
                    select r.character_id, r.instance_id, r.at, r.left_at, r.inside_ms, r.level, r.zone_id,
                        lead(r.at) over (partition by r.character_id, r.session_start order by r.run_index) as next_at,
                        sum(case when r.continues_previous then 0 else 1 end) over (partition by r.character_id order by r.session_start, r.run_index) as chain
                    from dungeon_runs r
                    where {$characters}
                ) numbered
                group by numbered.character_id, numbered.chain
            )
            SQL;
    }
}
