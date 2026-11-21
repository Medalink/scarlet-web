<?php

namespace App\Actions\Rulesets;

use App\Jobs\IngestSession;
use Illuminate\Support\Facades\DB;

class RefileSessions
{
    /**
     * File sessions under the kind of their character: every session whose ruleset row carries another kind than the
     * one its character plays is decoded again, which files it under the right row and brings the rollups up to date
     * (the ingest does both). Characters whose kind is not known yet are left as they are. Safe to run twice.
     *
     * @return int sessions queued
     */
    public function handle(): int
    {
        $queued = 0;
        $mismatched = DB::table('sessions as s')
            ->join('rulesets as r', 'r.id', '=', 's.ruleset_id')
            ->join('characters as c', 'c.id', '=', 's.character_id')
            ->leftJoin('realms as re', 're.id', '=', 'c.home_realm_id')
            ->whereRaw('coalesce(c.armory_kind, c.declared_kind, re.kind) is not null')
            ->whereRaw('r.kind is distinct from coalesce(c.armory_kind, c.declared_kind, re.kind)')
            ->orderBy('s.character_id')->orderBy('s.session_start')
            ->select('s.character_id', 's.session_start');

        foreach ($mismatched->cursor() as $row) {
            IngestSession::dispatch((int) $row->character_id, (int) $row->session_start);
            $queued++;
        }

        return $queued;
    }
}
