<?php

namespace App\Actions\Ingest;

use App\Support\SessionJoin;
use Illuminate\Support\Facades\DB;

class RebuildCharacterLevels
{
    /**
     * Rebuild the character's `character_levels` rows (docs/DESIGN.md, "Mid-life characters"): one row for each level
     * whose start Scarlet saw, with the client's played total at that start (`before_seconds`, the time to reach the
     * level). A level's start is seen when Scarlet watched the level-up into it (a `level_ups` row with `ding`), or when
     * the first recorded session began at level 1 with the played counter at about zero. The level a character was at
     * when first seen has no row, so it has no time to reach it.
     *
     * `completed` is true when Scarlet also saw the start of the next level and no session between the two follows
     * unrecorded play (`sessions.joins`). `seconds` is then the counter's difference between the two starts; on any
     * other row it is the time recorded at the level.
     *
     * @return array{from: int, until: int}|null epoch seconds the rollups must look at again: the old and
     *                                           the new rows together, null when the character had none
     */
    public function handle(int $characterId): ?array
    {
        $before = $this->extent($characterId);
        $tolerance = (int) config('scarlet.continuity_seconds') * 1000;

        DB::delete('delete from character_levels where character_id = ?', [$characterId]);
        DB::insert(
            <<<'SQL'
            insert into character_levels (at, character_id, session_start, ruleset_id, level, zone_id, counted, reached, seconds, before_seconds, completed)
            with seen as (
                select distinct on (d.reached) d.reached as level, d.at as entry_at, d.session_start, d.ruleset_id,
                       s.counter_start_ms + round(greatest(extract(epoch from (d.at - s.start_at)), 0) * 1000) as anchor
                from level_ups d
                join sessions s on s.character_id = d.character_id and s.session_start = d.session_start
                where d.character_id = ? and d.ding and s.counter_start_ms is not null
                order by d.reached, d.at
            ), entries as (
                select level, entry_at, session_start, ruleset_id, anchor from seen
                union all
                select 1, f.start_at, f.session_start, f.ruleset_id, f.counter_start_ms
                from (select * from sessions where character_id = ? order by session_start limit 1) f
                where f.level_start = 1 and f.counter_start_ms is not null and f.counter_start_ms <= ?
                  and not exists (select 1 from seen where level = 1)
            )
            select e.entry_at, ?, e.session_start, e.ruleset_id, e.level, null, coalesce(sp.all_counted, true), e.level,
                   round(case when done.completed then (e.next_anchor - e.anchor) / 1000.0 else coalesce(sp.spent_ms, 0) / 1000.0 end)::int,
                   round(e.anchor / 1000.0)::int,
                   done.completed
            from (
                select cur.*, nxt.anchor as next_anchor, nxt.session_start as next_session
                from entries cur left join entries nxt on nxt.level = cur.level + 1
            ) e
            left join (
                select level, sum(ms) as spent_ms, bool_and(counted) as all_counted
                from play_spans where character_id = ? and level is not null group by level
            ) sp on sp.level = e.level
            cross join lateral (
                select e.next_anchor is not null and not exists (
                    select 1 from sessions x
                    where x.character_id = ? and x.session_start > e.session_start and x.session_start <= e.next_session
                      and coalesce(x.joins, ?) in (?, ?)
                ) as completed
            ) done
            SQL,
            [
                $characterId, $characterId, $tolerance, $characterId, $characterId, $characterId,
                SessionJoin::Unknown->value, ...SessionJoin::BROKEN,
            ],
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
