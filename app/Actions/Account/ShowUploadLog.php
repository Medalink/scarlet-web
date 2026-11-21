<?php

namespace App\Actions\Account;

use App\Models\Account;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ShowUploadLog
{
    /** The newest uploads listed. */
    private const int GROUPS = 20;

    /** The newest parts of one character that are grouped, so the query never reads a character's whole history. */
    private const int PARTS_PER_CHARACTER = 2000;

    /** The days of upload totals listed, today included. */
    private const int DAYS = 7;

    /**
     * What the Recent uploads module of /account shows, all derived from existing tables.
     *
     * @return array{
     *     installs: list<array{name: string, last_seen_at: Carbon|null, revoked: bool}>,
     *     uploads: list<array{at: Carbon, character: string, parts: int, sessions: int, bytes: int}>,
     *     days: list<array{day: Carbon, requests: int, bytes: int}>
     * }
     */
    public function handle(Account $account): array
    {
        $installs = $account->installs()
            ->select(['id', 'name', 'last_seen_at', 'revoked_at'])
            ->orderByRaw('last_seen_at desc nulls last')
            ->get();

        // Raw SQL, bound: one upload is the parts of one character stored in the same minute, and a lateral join
        // reads only each character's newest rows through the (character_id, created_at) index. `body` is never read.
        /** @var list<object{name: string, minute: string, parts: int|string, sessions: int|string, bytes: int|string}> $rows */
        $rows = DB::select(
            <<<'SQL'
            select c.name, g.minute, g.parts, g.sessions, g.bytes
            from characters c
            cross join lateral (
                select date_trunc('minute', p.created_at) as minute,
                       count(*) as parts,
                       count(distinct p.session_start) as sessions,
                       sum(p.raw_bytes) as bytes
                from (
                    select created_at, session_start, raw_bytes
                    from parts
                    where character_id = c.id
                    order by created_at desc
                    limit ?
                ) p
                group by 1
                order by 1 desc
                limit ?
            ) g
            where c.account_id = ?
            order by g.minute desc, c.name
            limit ?
            SQL,
            [self::PARTS_PER_CHARACTER, self::GROUPS, $account->id, self::GROUPS],
        );

        $usage = DB::table('upload_usage')
            ->whereIn('install_id', $account->installs()->select('id'))
            ->where('day', '>=', Carbon::now('UTC')->subDays(self::DAYS - 1)->toDateString())
            ->groupBy('day')
            ->orderByDesc('day')
            ->selectRaw('day, sum(requests) as requests, sum(bytes) as bytes')
            ->get();

        return [
            'installs' => array_values($installs->map(fn ($install): array => [
                'name' => $install->name ?? 'Scarlet app',
                'last_seen_at' => $install->last_seen_at,
                'revoked' => $install->isRevoked(),
            ])->all()),
            'uploads' => array_map(fn (object $row): array => [
                'at' => Carbon::parse($row->minute)->utc(),
                'character' => $row->name,
                'parts' => (int) $row->parts,
                'sessions' => (int) $row->sessions,
                'bytes' => (int) $row->bytes,
            ], $rows),
            'days' => array_values($usage->map(fn (object $row): array => [
                'day' => Carbon::parse($row->day, 'UTC'),
                'requests' => (int) $row->requests,
                'bytes' => (int) $row->bytes,
            ])->all()),
        ];
    }
}
