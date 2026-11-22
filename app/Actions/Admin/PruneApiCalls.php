<?php

namespace App\Actions\Admin;

use App\Models\ApiCall;

/** Deletes the `api_calls` rows older than `scarlet.api_calls_days` (docs/DESIGN.md, Privacy). */
class PruneApiCalls
{
    /** @return int the rows deleted, keeping today and the `scarlet.api_calls_days` full days before it, the days /admin/api draws */
    public function handle(): int
    {
        $before = now()->subDays((int) config('scarlet.api_calls_days'))->toDateString();

        return ApiCall::query()->where('day', '<', $before)->delete();
    }
}
