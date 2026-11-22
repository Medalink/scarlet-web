<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Path heat on the finest world grid of docs/DESIGN.md "Maps": 1536 columns by 1024 rows, twice the 768 by 512 grid, so
 * every cell of the older grid is four cells here and the coarser levels are read by integer division. Built like
 * `create_world_heat_aggregates`, which stays in place. The `characters` sketch of the ruleset view keeps the minimum-characters rule per cell.
 *
 * A continuous aggregate cannot be created inside a transaction.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    private const string HLL = 'hyperloglog(1024, character_id)';

    private const string CELL_I = 'least(floor(u * 1536), 1535)::smallint';

    private const string CELL_J = 'least(floor(v * 1024), 1023)::smallint';

    public function up(): void
    {
        foreach ($this->views() as $name => $select) {
            DB::statement(sprintf('create materialized view %s with (timescaledb.continuous, timescaledb.materialized_only = true) as %s with no data', $name, $select));
            DB::statement(
                "select add_continuous_aggregate_policy(?::regclass, start_offset => null, end_offset => interval '1 hour', schedule_interval => interval '15 minutes')",
                [$name],
            );
        }
        DB::statement('create index if not exists creature_sightings_npc_id_at_index on creature_sightings (npc_id, at) where npc_id is not null');
    }

    /** @return array<string, string> view name => select */
    private function views(): array
    {
        $hll = self::HLL;
        $i = self::CELL_I;
        $j = self::CELL_J;

        return [
            'world_heat_fine_daily_character' => <<<SQL
                select time_bucket('1 day', at) as bucket, character_id, {$i} as cell_i, {$j} as cell_j, count(*) as points
                from path_points
                where u between 0 and 1 and v between 0 and 1
                group by 1, 2, 3, 4
                SQL,
            'world_heat_fine_daily_ruleset' => <<<SQL
                select time_bucket('1 day', at) as bucket, ruleset_id, {$i} as cell_i, {$j} as cell_j,
                       count(*) as points, {$hll} as characters
                from path_points
                where counted and u between 0 and 1 and v between 0 and 1
                group by 1, 2, 3, 4
                SQL,
        ];
    }
};
