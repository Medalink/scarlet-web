<?php

use App\Actions\Rollups\RebuildKeyedLoot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The loot aggregates that count a corpse once (docs/LOOT.md, Units of counting, rule 7), beside the ones that count one
 * character's first reads (`loot_windows_daily` and the rest stay for the readers that pair a character's windows with
 * its own receipts). A window with no `corpse_key` keeps the first-read rule, so five continuous aggregates repeat the
 * old definitions with `corpse_key is null`, and refresh like them. A window with a key counts once however many
 * characters read or received it, which no daily sum can do, so the keyed counts are five plain tables that
 * `RebuildKeyedLoot` fills whole (a delete and an insert in one transaction) inside every aggregate refresh. Three views
 * put each keyed table under its unkeyed twin, with a `keyed` column, so a reader changes one table name. The tables are
 * built once here, from the rows stored now. A continuous aggregate cannot be created inside a transaction.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    private const string HLL = 'hyperloglog(1024, character_id)';

    public function up(): void
    {
        foreach ($this->aggregates() as $name => $select) {
            DB::statement(sprintf('create materialized view %s with (timescaledb.continuous, timescaledb.materialized_only = true) as %s with no data', $name, $select));
            DB::statement(
                "select add_continuous_aggregate_policy(?::regclass, start_offset => null, end_offset => interval '1 hour', schedule_interval => interval '15 minutes')",
                [$name],
            );
            DB::statement('call refresh_continuous_aggregate(?::regclass, null, null)', [$name]);
        }
        foreach ($this->tables() as $name => $columns) {
            DB::statement(sprintf('create table %s (%s)', $name, $columns));
        }
        DB::statement('create index loot_windows_keyed_daily_source_index on loot_windows_keyed_daily (source_kind, source_id)');
        DB::statement('create index loot_items_keyed_daily_source_index on loot_items_keyed_daily (source_kind, source_id)');
        DB::statement('create index loot_items_keyed_daily_item_index on loot_items_keyed_daily (item_id)');
        DB::statement('create index loot_windows_character_keyed_daily_source_index on loot_windows_character_keyed_daily (source_kind, source_id)');
        DB::statement('create index loot_kills_keyed_daily_npc_index on loot_kills_keyed_daily (npc_id)');
        DB::statement('create index loot_kill_items_keyed_daily_npc_index on loot_kill_items_keyed_daily (npc_id)');
        foreach ($this->views() as $name => $select) {
            DB::statement(sprintf('create view %s as %s', $name, $select));
        }

        app(RebuildKeyedLoot::class)->handle();
    }

    /** @return array<string, string> view name => select */
    private function aggregates(): array
    {
        $hll = self::HLL;

        return [
            'loot_windows_unkeyed_daily' => <<<SQL
                select time_bucket('1 day', at) as bucket, source_kind, source_id, read, loot_rule, group_size, zone_id,
                       count(*) as windows, sum(item_rows) as item_rows, {$hll} as characters
                from loot_opens
                where counted and has_items and first_read is not false and source_id is not null and corpse_key is null
                group by 1, 2, 3, 4, 5, 6, 7
                SQL,
            'loot_items_unkeyed_daily' => <<<SQL
                select time_bucket('1 day', at) as bucket, source_kind, source_id, read, loot_rule, group_size, zone_id, item_id,
                       count(*) as windows, sum(quantity) as quantity, {$hll} as characters
                from loot_drops
                where counted and via = 'window' and first_read is not false and source_id is not null and corpse_key is null
                group by 1, 2, 3, 4, 5, 6, 7, 8
                SQL,
            'loot_windows_character_unkeyed_daily' => <<<'SQL'
                select time_bucket('1 day', at) as bucket, source_kind, source_id, read, loot_rule, group_size, character_id,
                       count(*) as windows, sum(item_rows) as item_rows, sum(item_rows::bigint * item_rows) as item_rows_squared
                from loot_opens
                where counted and has_items and first_read is not false and source_id is not null and corpse_key is null
                group by 1, 2, 3, 4, 5, 6, 7
                SQL,
            'loot_freeforall_items_unkeyed_daily' => <<<'SQL'
                select time_bucket('1 day', at) as bucket, source_kind, source_id, read, group_size, character_id, item_id,
                       count(*) as windows
                from loot_drops
                where counted and via = 'window' and first_read is not false and source_id is not null and corpse_key is null and loot_rule = 'freeforall'
                group by 1, 2, 3, 4, 5, 6, 7
                SQL,
            'kills_solo_unkeyed_daily' => <<<SQL
                select time_bucket('1 day', at) as bucket, npc_id, count(*) as kills, {$hll} as characters
                from kills
                where counted and not player and npc_id is not null and credit = 1 and group_size in (0, 1)
                  and at >= timestamptz '2026-11-20 21:00:00+00' and corpse_key is null
                group by 1, 2
                SQL,
        ];
    }

    /** @return array<string, string> table name => column list */
    private function tables(): array
    {
        return [
            'loot_windows_keyed_daily' => 'bucket timestamptz not null, source_kind varchar(1) not null, source_id integer not null, read varchar(5), loot_rule varchar(24) not null, group_size smallint, zone_id integer, windows bigint not null, item_rows bigint, characters hyperloglog not null',
            'loot_items_keyed_daily' => 'bucket timestamptz not null, source_kind varchar(1) not null, source_id integer not null, read varchar(5), loot_rule varchar(24) not null, group_size smallint, zone_id integer, item_id integer not null, windows bigint not null, quantity bigint not null, characters hyperloglog not null',
            'loot_windows_character_keyed_daily' => 'bucket timestamptz not null, source_kind varchar(1) not null, source_id integer not null, read varchar(5), loot_rule varchar(24) not null, group_size smallint, character_id bigint not null, windows bigint not null, item_rows bigint, item_rows_squared bigint',
            'loot_kills_keyed_daily' => 'bucket timestamptz not null, npc_id integer not null, loot_rule varchar(24) not null, group_size smallint, kills bigint not null, looted bigint not null, characters hyperloglog not null',
            'loot_kill_items_keyed_daily' => 'bucket timestamptz not null, npc_id integer not null, loot_rule varchar(24) not null, group_size smallint, item_id integer not null, windows bigint not null, characters hyperloglog not null',
        ];
    }

    /** @return array<string, string> view name => select */
    private function views(): array
    {
        return [
            'loot_windows_counted' => <<<'SQL'
                select bucket, source_kind, source_id, read, loot_rule, group_size, zone_id, windows, item_rows, characters, false as keyed from loot_windows_unkeyed_daily
                union all
                select bucket, source_kind, source_id, read, loot_rule, group_size, zone_id, windows, item_rows, characters, true as keyed from loot_windows_keyed_daily
                SQL,
            'loot_items_counted' => <<<'SQL'
                select bucket, source_kind, source_id, read, loot_rule, group_size, zone_id, item_id, windows, quantity, characters, false as keyed from loot_items_unkeyed_daily
                union all
                select bucket, source_kind, source_id, read, loot_rule, group_size, zone_id, item_id, windows, quantity, characters, true as keyed from loot_items_keyed_daily
                SQL,
            'loot_windows_character_counted' => <<<'SQL'
                select bucket, source_kind, source_id, read, loot_rule, group_size, character_id, windows, item_rows, item_rows_squared, false as keyed from loot_windows_character_unkeyed_daily
                union all
                select bucket, source_kind, source_id, read, loot_rule, group_size, character_id, windows, item_rows, item_rows_squared, true as keyed from loot_windows_character_keyed_daily
                SQL,
        ];
    }
};
