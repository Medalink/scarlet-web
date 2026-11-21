<?php

namespace App\Actions\Ingest;

use App\Jobs\IngestSession;
use Illuminate\Support\Facades\DB;

class QueueRedecode
{
    /**
     * Decode again every stored session, so rows written by an older decoder follow the current one (a new column, a
     * changed rule). Safe to run twice: a session is queued once at a time and decodes to the same rows.
     *
     * @return int sessions queued
     */
    public function handle(): int
    {
        $queued = 0;
        $sessions = DB::table('parts')->select('character_id', 'session_start')->distinct()->orderBy('character_id')->orderBy('session_start');

        foreach ($sessions->cursor() as $row) {
            IngestSession::dispatch((int) $row->character_id, (int) $row->session_start);
            $queued++;
        }

        return $queued;
    }
}
