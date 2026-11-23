<?php

use App\Actions\Rollups\GroupShareReceipts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The receipts behind "Yours" of the group loot share (docs/LOOT.md, Metrics: Share of group loot), by day, rule, group
 * size and character, as a plain table that `RebuildKeyedLoot` refills for the days it rebuilds. The query that counted
 * them joined every receipt in a group to the rolls its character won, on every page view. Filled once here, from the
 * rows stored now. Named before 2026_12_22 and 2026_12_28, which run `RebuildKeyedLoot` with the code of the day, so a new
 * database has the table by the time they delete from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('create table group_share_receipts (bucket timestamptz not null, loot_rule varchar(24) not null, group_size smallint not null, character_id bigint not null, receipts bigint not null, primary key (bucket, loot_rule, group_size, character_id))');

        app(GroupShareReceipts::class)->handle('-infinity', 'infinity');
    }
};
