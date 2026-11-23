<?php

namespace App\Actions\Ingest;

use App\Jobs\IngestSession;
use Illuminate\Support\Facades\DB;

class QueueUndecodedSessions
{
    /**
     * Queue the sessions that hold a part stored a while ago and still not decoded: the job of an upload that was lost
     * between the commit of its parts and the dispatch, or with the queue's memory. A session whose job still waits is
     * skipped by the job's own identity, and a decoded part leaves the index this reads, so a second run queues nothing
     * the first did not. Oldest first, `sweep_limit` sessions at most, and parts older than `sweep_give_up_hours` are
     * left to `ingest:redecode` so a session that cannot be decoded is not queued for ever. Sessions whose parts have
     * failed `sweep_max_failures` decodes leave the sweep with their last error recorded, so poison cannot spend the
     * window on the same rows every five minutes; a fresh upload or `ingest:redecode` picks them back up.
     *
     * @return int sessions found
     */
    public function handle(): int
    {
        $settled = now()->subSeconds((int) config('scarlet.ingest.sweep_after_seconds'));
        $giveUp = now()->subHours((int) config('scarlet.ingest.sweep_give_up_hours'));

        $sessions = DB::table('parts')
            ->whereNull('decoded_at')
            ->where('created_at', '<', $settled)
            ->where('created_at', '>', $giveUp)
            ->where('failed_attempts', '<', (int) config('scarlet.ingest.sweep_max_failures'))
            ->groupBy('character_id', 'session_start')
            ->orderByRaw('min(created_at)')
            ->limit((int) config('scarlet.ingest.sweep_limit'))
            ->get(['character_id', 'session_start']);

        foreach ($sessions as $session) {
            IngestSession::dispatch((int) $session->character_id, (int) $session->session_start);
        }

        return $sessions->count();
    }
}
