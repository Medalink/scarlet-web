<?php

namespace App\Actions\Ingest;

use App\Jobs\IngestSession;
use App\Support\SharingGroup;
use Illuminate\Support\Facades\DB;

class QueueTalentBackfill
{
    /**
     * Decode again every stored session that has a build part, which fills the talent tables from parts uploaded
     * before the decoder knew them. Safe to run twice: a session is queued once at a time and decodes to the same rows.
     *
     * @return int sessions queued
     */
    public function handle(): int
    {
        $queued = 0;
        $sessions = DB::table('parts')
            ->where('group_key', SharingGroup::BUILD)
            ->select('character_id', 'session_start')
            ->distinct()
            ->orderBy('character_id')->orderBy('session_start');

        foreach ($sessions->cursor() as $row) {
            IngestSession::dispatch((int) $row->character_id, (int) $row->session_start, true);
            $queued++;
        }

        return $queued;
    }
}
