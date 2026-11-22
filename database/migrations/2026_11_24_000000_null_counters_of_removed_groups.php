<?php

use App\Jobs\RefreshRollups;
use App\Support\DecompressionLimit;
use App\Support\FactTable;
use App\Support\SessionRows;
use App\Support\SharingGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Sessions whose owner removed a sharing group before RemoveGroup nulled the group's counters still hold them, and
 * leaderboards and character answers read them; their play spans still hold the AFK time of a removed `xp`, which the
 * rollups sum. The decoder never writes a value for a group the session has no part of (`groups`), so a value set on a
 * session without its group is one left by a removal. Only sessions that list `core` count: every decoded session has
 * it, so one without it was never tagged and its values are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        DecompressionLimit::lift();
        foreach (SessionRows::GROUP_COUNTERS as $group => $counters) {
            DB::table('sessions')
                ->whereRaw('groups @> jsonb_build_array(?::text)', [SharingGroup::CORE])
                ->whereRaw('not (groups @> jsonb_build_array(?::text))', [$group])
                ->where(function (Builder $query) use ($counters): void {
                    foreach ($counters as $counter) {
                        $query->orWhereNotNull($counter);
                    }
                })
                ->update(array_fill_keys($counters, null));
        }

        foreach (FactTable::cases() as $table) {
            foreach ($table->groupColumns() as $group => $columns) {
                foreach ($columns as $column) {
                    $this->clear($table->value, $column, $group);
                }
            }
        }
    }

    private function clear(string $table, string $column, string $group): void
    {
        $span = DB::selectOne(
            "with cleared as (
                update {$table} f set {$column} = null
                from sessions s
                where s.character_id = f.character_id and s.session_start = f.session_start
                  and f.{$column} is not null
                  and s.groups @> jsonb_build_array(?::text) and not (s.groups @> jsonb_build_array(?::text))
                returning f.at
            )
            select extract(epoch from min(at)) as first, extract(epoch from max(at)) as last from cleared",
            [SharingGroup::CORE, $group],
        );
        if ($span !== null && $span->first !== null) {
            RefreshRollups::dispatch((int) floor((float) $span->first), (int) ceil((float) $span->last));
        }
    }
};
