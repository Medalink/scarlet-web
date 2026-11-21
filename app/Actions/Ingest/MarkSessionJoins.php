<?php

namespace App\Actions\Ingest;

use App\Support\SessionJoin;
use Illuminate\Support\Facades\DB;

class MarkSessionJoins
{
    /** Allowed on top of the tolerance for each session between two counter reads: a session without a read misses its reload. */
    private const int RELOAD_MS = 15_000;

    /**
     * Tell, for every session of a character, whether play that Scarlet did not record sits between it and the
     * session before it (docs/DESIGN.md, "Mid-life characters"). Two ways rule it out: the clock (less than the
     * tolerance passed between the end of one session and the start of the next, and a character cannot play where
     * it is not logged in) and the client's played counter (it stands within the tolerance of where the session
     * before it ended). A session without a counter read is carried on from the last read by its own length.
     * Safe to run twice.
     */
    public function handle(int $characterId): void
    {
        $tolerance = (int) config('scarlet.continuity_seconds') * 1000;
        $rows = DB::table('sessions')->where('character_id', $characterId)->orderBy('session_start')
            ->selectRaw('id, joins, unrecorded_ms, played_ms, counter_start_ms, counter_end_ms')
            ->selectRaw('(extract(epoch from start_at) * 1000)::bigint as start_ms, (extract(epoch from end_at) * 1000)::bigint as end_ms')
            ->get();

        $endedAt = $counterEnd = null;
        $carried = 0;
        foreach ($rows as $row) {
            $counterGap = $row->counter_start_ms === null || $counterEnd === null ? null : (int) $row->counter_start_ms - $counterEnd;
            [$join, $unrecorded] = $endedAt === null
                ? [SessionJoin::First, null]
                : $this->join((int) $row->start_ms - $endedAt, $counterGap, $tolerance + $carried * self::RELOAD_MS);
            if ($row->joins !== $join->value || ($row->unrecorded_ms === null ? null : (int) $row->unrecorded_ms) !== $unrecorded) {
                DB::table('sessions')->where('id', $row->id)->update(['joins' => $join->value, 'unrecorded_ms' => $unrecorded]);
            }

            $endedAt = (int) $row->end_ms;
            $read = $row->counter_end_ms !== null;
            $counterEnd = $read ? (int) $row->counter_end_ms : ($counterEnd === null ? null : $counterEnd + (int) $row->played_ms);
            $carried = $read ? 0 : ($counterEnd === null ? $carried : $carried + 1);
        }
    }

    /** @return array{0: SessionJoin, 1: int|null} the join and the unrecorded play in ms */
    private function join(int $wallMs, ?int $counterGapMs, int $toleranceMs): array
    {
        if ($wallMs <= $toleranceMs) {
            return [SessionJoin::Continuous, null];
        }
        if ($counterGapMs === null || $counterGapMs < -$toleranceMs) {
            return [SessionJoin::Unknown, null];
        }

        return $counterGapMs <= $toleranceMs ? [SessionJoin::Continuous, null] : [SessionJoin::Gap, $counterGapMs];
    }
}
