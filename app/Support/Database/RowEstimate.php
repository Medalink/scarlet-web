<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

/**
 * The planner's row count of a table, so an admin page never runs `count(*)` over a big one: Timescale's
 * `approximate_row_count` for a hypertable (it sums the chunks), `reltuples` for any other table. Raw SQL because both
 * are catalog functions with no query builder form.
 */
final class RowEstimate
{
    public static function of(string $table): int
    {
        $row = DB::selectOne(
            'select case when exists (select 1 from timescaledb_information.hypertables where hypertable_schema = current_schema() and hypertable_name = ?)
                then approximate_row_count(to_regclass(?)) else (select greatest(reltuples, 0)::bigint from pg_class where oid = to_regclass(?)) end as n',
            [$table, $table, $table],
        );

        return (int) ($row->n ?? 0);
    }
}
