<?php

namespace App\Actions\Account;

use App\Models\Account;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ListUploads
{
    /** The uploads one page of the full history lists. */
    public const int PAGE = 50;

    /**
     * The uploads of the account's characters, newest first. One upload is the parts of one character stored in
     * the same minute. `$since` keeps the newest only; null reads the whole history. Reads one more than a page so
     * the caller knows whether an older page exists, and counts nothing else. `body` is never read.
     *
     * @return array{uploads: list<array{at: Carbon, character: string, parts: int, sessions: int, bytes: int}>, more: bool}
     */
    public function handle(Account $account, ?Carbon $since = null, int $page = 1, int $perPage = self::PAGE): array
    {
        $rows = DB::table('parts')
            ->join('characters', 'characters.id', '=', 'parts.character_id')
            ->where('characters.account_id', $account->id)
            ->when($since !== null, fn ($query) => $query->where('parts.created_at', '>=', $since))
            ->groupBy('characters.id', 'characters.name')
            ->groupByRaw("date_trunc('minute', parts.created_at)")
            ->orderByRaw("date_trunc('minute', parts.created_at) desc")
            ->orderBy('characters.name')
            ->selectRaw("characters.name, date_trunc('minute', parts.created_at) as minute, count(*) as parts, count(distinct parts.session_start) as sessions, sum(parts.raw_bytes) as bytes")
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get();

        return [
            'uploads' => array_values($rows->take($perPage)->map(fn (object $row): array => [
                'at' => Carbon::parse($row->minute)->utc(),
                'character' => $row->name,
                'parts' => (int) $row->parts,
                'sessions' => (int) $row->sessions,
                'bytes' => (int) $row->bytes,
            ])->all()),
            'more' => $rows->count() > $perPage,
        ];
    }
}
