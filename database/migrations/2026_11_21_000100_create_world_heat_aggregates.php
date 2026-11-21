<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Heat on the world grid of docs/DESIGN.md "Maps": 768 columns by 512 rows, so a cell is square on the 3:2 world
 * (1002 by 668 world units, about 1.3 units a side). `cell_i = floor(u * 768)`, `cell_j = floor(v * 512)`, kept inside
 * the grid. A point with no `u, v` is not in them. Built like the aggregates before them: materialized only, a policy
 * and a refresh after ingest and after points are placed.
 *
 * A continuous aggregate cannot be created inside a transaction.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    private const string HLL = 'hyperloglog(1024, character_id)';

    private const string CELL_I = 'least(floor(u * 768), 767)::smallint';

    private const string CELL_J = 'least(floor(v * 512), 511)::smallint';

    public function up(): void
    {
        foreach ($this->views() as $name => $select) {
            DB::statement(sprintf('create materialized view %s with (timescaledb.continuous, timescaledb.materialized_only = true) as %s with no data', $name, $select));
            DB::statement(
                "select add_continuous_aggregate_policy(?::regclass, start_offset => null, end_offset => interval '1 hour', schedule_interval => interval '15 minutes')",
                [$name],
            );
        }
    }

    /** @return array<string, string> view name => select */
    private function views(): array
    {
        $hll = self::HLL;
        $i = self::CELL_I;
        $j = self::CELL_J;

        return [
            'world_heat_daily_character' => <<<SQL
                select time_bucket('1 day', at) as bucket, character_id, {$i} as cell_i, {$j} as cell_j, count(*) as points
                from path_points
                where u between 0 and 1 and v between 0 and 1
                group by 1, 2, 3, 4
                SQL,
            'world_heat_daily_ruleset' => <<<SQL
                select time_bucket('1 day', at) as bucket, ruleset_id, {$i} as cell_i, {$j} as cell_j,
                       count(*) as points, {$hll} as characters
                from path_points
                where counted and u between 0 and 1 and v between 0 and 1
                group by 1, 2, 3, 4
                SQL,
            'world_death_heat_daily_character' => <<<SQL
                select time_bucket('1 day', at) as bucket, character_id, {$i} as cell_i, {$j} as cell_j, count(*) as deaths
                from deaths
                where u between 0 and 1 and v between 0 and 1
                group by 1, 2, 3, 4
                SQL,
            'world_death_heat_daily_ruleset' => <<<SQL
                select time_bucket('1 day', at) as bucket, ruleset_id, {$i} as cell_i, {$j} as cell_j,
                       count(*) as deaths, {$hll} as characters
                from deaths
                where counted and u between 0 and 1 and v between 0 and 1
                group by 1, 2, 3, 4
                SQL,
            // Where a creature was met, for the creature page and for the map's creature layer.
            'world_sighting_heat_daily_ruleset' => <<<SQL
                select time_bucket('1 day', at) as bucket, ruleset_id, npc_id, {$i} as cell_i, {$j} as cell_j,
                       count(*) as sightings, {$hll} as characters
                from creature_sightings
                where counted and npc_id is not null and u between 0 and 1 and v between 0 and 1
                group by 1, 2, 3, 4, 5
                SQL,
        ];
    }
};
