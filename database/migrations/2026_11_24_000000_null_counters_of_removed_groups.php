<?php

use App\Support\SessionRows;
use App\Support\SharingGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Sessions whose owner removed a sharing group before RemoveGroup nulled the group's counters still hold them, and
 * leaderboards and character answers read them. The decoder never writes a counter for a group the session has no part
 * of (`groups`), so a counter set on a session without its group is one left by a removal. Only sessions that list
 * `core` count: every decoded session has it, so one without it was never tagged and its counters are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
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
    }
};
