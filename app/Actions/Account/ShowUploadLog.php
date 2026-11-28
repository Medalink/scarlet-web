<?php

namespace App\Actions\Account;

use App\Models\Account;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ShowUploadLog
{
    /** The hours of uploads listed. */
    public const int HOURS = 24;

    /** The most uploads listed; the full history has the rest. */
    private const int UPLOADS = 100;

    /** The days of upload totals listed, today included. */
    private const int DAYS = 7;

    public function __construct(private readonly ListUploads $uploads) {}

    /**
     * What the uploads section of /account/storage shows, all derived from existing tables.
     *
     * @return array{
     *     uploads: list<array{at: Carbon, character: string, parts: int, sessions: int, bytes: int}>,
     *     days: list<array{day: Carbon, requests: int, bytes: int}>
     * }
     */
    public function handle(Account $account): array
    {
        $usage = DB::table('upload_usage')
            ->whereIn('install_id', $account->installs()->select('id'))
            ->where('day', '>=', Carbon::now('UTC')->subDays(self::DAYS - 1)->toDateString())
            ->groupBy('day')
            ->orderByDesc('day')
            ->selectRaw('day, sum(requests) as requests, sum(bytes) as bytes')
            ->get();

        return [
            'uploads' => $this->uploads->handle($account, Carbon::now('UTC')->subHours(self::HOURS), 1, self::UPLOADS)['uploads'],
            'days' => array_values($usage->map(fn (object $row): array => [
                'day' => Carbon::parse($row->day, 'UTC'),
                'requests' => (int) $row->requests,
                'bytes' => (int) $row->bytes,
            ])->all()),
        ];
    }
}
