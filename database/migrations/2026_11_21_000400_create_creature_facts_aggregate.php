<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * What the creature and zone pages need to know about a creature without scanning sightings: where it was met, its
 * level range, kind and family, and how many characters met it. Built like the aggregates before it: materialized
 * only, a policy, refreshed after ingest.
 *
 * A continuous aggregate cannot be created inside a transaction.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement(<<<'SQL'
            create materialized view creature_facts_daily with (timescaledb.continuous, timescaledb.materialized_only = true) as
            select time_bucket('1 day', at) as bucket, ruleset_id, npc_id, zone_id, npc_level, classification, creature_type, family,
                   count(*) as sightings, hyperloglog(1024, character_id) as characters
            from creature_sightings
            where counted and npc_id is not null
            group by 1, 2, 3, 4, 5, 6, 7, 8
            with no data
            SQL);
        DB::statement(
            "select add_continuous_aggregate_policy('creature_facts_daily'::regclass, start_offset => null, end_offset => interval '1 hour', schedule_interval => interval '15 minutes')",
        );
    }
};
