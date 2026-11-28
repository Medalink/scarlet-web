<?php

namespace App\Actions\Admin;

use App\Models\ApiCall;
use App\Models\ApiSurface;
use Illuminate\Support\Facades\DB;

/**
 * Adds one request to its caller's `api_calls` row of the day and, when it named a character, to that character's row,
 * which has no caller, so a scraper walking many characters adds one row per character and not one per address and
 * character. The increment is raw SQL because an upsert can only add to the stored count through an expression on the
 * conflicting row. A 429 counts as refused and as limited; the busiest minute keeps the largest number of requests the key had in one rate-limit window. Both writes share a two-second statement cap, so a blocked
 * row cannot hold a php-fpm worker.
 */
class CountApiCall
{
    public function handle(ApiSurface $surface, string $endpoint, string $address, int $accountId, string $installId, string $characterKey, bool $refused, bool $limited = false, int $busiestMinute = 0): void
    {
        $row = [
            'day' => now()->toDateString(),
            'surface' => $surface->value,
            'endpoint' => mb_substr($endpoint, 0, 96),
            'address' => mb_substr($address, 0, 45),
            'account_id' => $accountId,
            'install_id' => $installId,
            'character_key' => '',
            'requests' => 1,
            'refused' => $refused ? 1 : 0,
            'limited' => $limited ? 1 : 0,
            'busiest_minute' => $busiestMinute,
            'first_at' => now(),
            'last_at' => now(),
        ];
        $rows = [$row];
        if ($characterKey !== '') {
            $rows[] = ['address' => '', 'account_id' => 0, 'install_id' => '', 'character_key' => mb_substr($characterKey, 0, 64), 'busiest_minute' => 0] + $row;
        }

        DB::transaction(function () use ($rows): void {
            DB::statement("set local statement_timeout = '2s'");
            ApiCall::query()->upsert($rows, ApiCall::KEY, [
                'requests' => DB::raw('api_calls.requests + 1'),
                'refused' => DB::raw('api_calls.refused + excluded.refused'),
                'limited' => DB::raw('api_calls.limited + excluded.limited'),
                'busiest_minute' => DB::raw('greatest(api_calls.busiest_minute, excluded.busiest_minute)'),
                'last_at' => DB::raw('excluded.last_at'),
            ]);
        });
    }
}
