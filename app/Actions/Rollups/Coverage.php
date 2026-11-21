<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Coverage
{
    /**
     * When the recorded play starts and when the newest session was decoded.
     *
     * @return array{since: Carbon|null, updated_at: Carbon|null}
     */
    public function handle(): array
    {
        $since = DB::table(Rollup::PlayHourlyRuleset->value)->min('bucket');
        $updated = DB::table('sessions')->max('decoded_at');

        return [
            'since' => is_string($since) ? Carbon::parse($since, 'UTC') : null,
            'updated_at' => is_string($updated) ? Carbon::parse($updated, 'UTC') : null,
        ];
    }
}
