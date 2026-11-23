<?php

namespace App\Actions\Feats;

use Illuminate\Support\Facades\DB;

/** Deletes the `feat_events` rows older than `scarlet.feats.event_days`; the pages read the last week and the latest few of a feat. */
class PruneFeatEvents
{
    /** @return int rows deleted */
    public function handle(): int
    {
        return DB::table('feat_events')->where('at', '<', now()->subDays((int) config('scarlet.feats.event_days')))->delete();
    }
}
