<?php

use App\Support\SessionRows;
use App\Support\SharingGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sessions whose owner removed the `xp` group before RemoveGroup cleared the experience bar keys of `extra` still hold
 * them, and the character page draws its level bar from them. A session that lists `core` but not `xp` never had the
 * keys written by the decoder, so any it holds are left by a removal.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (SessionRows::GROUP_EXTRA_KEYS as $group => $keys) {
            foreach ($keys as $key) {
                DB::update(
                    'update sessions set extra = extra - ?::text where groups @> jsonb_build_array(?::text) and not (groups @> jsonb_build_array(?::text)) and jsonb_exists(extra, ?)',
                    [$key, SharingGroup::CORE, $group, $key],
                );
            }
        }
    }
};
