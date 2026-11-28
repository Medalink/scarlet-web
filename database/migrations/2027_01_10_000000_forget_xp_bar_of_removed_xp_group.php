<?php

use App\Support\SessionRows;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The `S` line is core's and carries the experience bar, so sessions decoded without the `xp` group, or whose owner
 * removed it, hold `xp` and `xp_max` in `extra`. SessionRows now leaves a missing group's keys out; this clears the
 * rows written before.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (SessionRows::GROUP_EXTRA_KEYS as $group => $keys) {
            $list = '{'.implode(',', $keys).'}';
            DB::update(
                'update sessions set extra = extra - ?::text[] where not (groups @> jsonb_build_array(?::text)) and jsonb_exists_any(extra, ?::text[])',
                [$list, $group, $list],
            );
        }
    }
};
