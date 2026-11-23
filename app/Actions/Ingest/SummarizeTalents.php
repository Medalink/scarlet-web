<?php

namespace App\Actions\Ingest;

use Illuminate\Support\Facades\DB;

class SummarizeTalents
{
    /**
     * Copy the latest talent snapshot of a character onto the character: the tab with most points, the points
     * spent and the build hash. A character with no snapshot gets nulls.
     */
    public function handle(int $characterId): void
    {
        DB::update(
            <<<'SQL'
            update characters set
                spec_tab_id = latest.spec_tab_id,
                talent_points = latest.points,
                build_hash = latest.build_hash
            from (
                select c.id,
                    s.spec_tab_id,
                    s.build_hash,
                    (select least(greatest(sum(split_part(e, ':', 3)::bigint), 0), 32767)::smallint from string_to_table(s.ranks, ',') as e where e <> '') as points
                from characters c
                left join lateral (
                    select spec_tab_id, build_hash, ranks from talent_snapshots
                    where character_id = c.id
                    order by at desc
                    limit 1
                ) s on true
                where c.id = ?
            ) latest
            where characters.id = latest.id
            SQL,
            [$characterId],
        );
    }
}
