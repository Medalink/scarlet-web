<?php

namespace App\Actions\Account;

use Illuminate\Support\Facades\DB;

class ShowPlayedTogether
{
    /** Seconds two sessions must overlap to count as played together. */
    public const int MIN_SECONDS = 60;

    /**
     * Per pair of characters, the time their sessions overlapped. Raw SQL for the interval arithmetic of the overlap, which
     * the builder has no verb for; the only value is bound.
     *
     * @param  list<int>  $characterIds
     * @return list<array{a: int, b: int, seconds: int, sessions: int, last: string}> longest first
     */
    public function handle(array $characterIds): array
    {
        if (count($characterIds) < 2) {
            return [];
        }

        return array_values(DB::table('sessions as a')
            ->join('sessions as b', fn ($join) => $join->on('a.character_id', '<', 'b.character_id')->whereColumn('a.start_at', '<', 'b.end_at')->whereColumn('b.start_at', '<', 'a.end_at'))
            ->whereIn('a.character_id', $characterIds)->whereIn('b.character_id', $characterIds)
            ->whereRaw('extract(epoch from least(a.end_at, b.end_at) - greatest(a.start_at, b.start_at)) >= ?', [self::MIN_SECONDS])
            ->groupBy('a.character_id', 'b.character_id')
            ->selectRaw('a.character_id as a, b.character_id as b, sum(extract(epoch from least(a.end_at, b.end_at) - greatest(a.start_at, b.start_at)))::int as seconds, count(*) as sessions, max(least(a.end_at, b.end_at)) as last')
            ->orderByDesc('seconds')->orderBy('a.character_id')->orderBy('b.character_id')->get()
            ->map(fn (object $row): array => ['a' => (int) $row->a, 'b' => (int) $row->b, 'seconds' => (int) $row->seconds, 'sessions' => (int) $row->sessions, 'last' => (string) $row->last])->all());
    }
}
