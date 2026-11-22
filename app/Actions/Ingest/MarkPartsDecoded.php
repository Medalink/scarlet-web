<?php

namespace App\Actions\Ingest;

use Illuminate\Support\Facades\DB;

class MarkPartsDecoded
{
    /**
     * Mark the parts a decode read as decoded. Only those: a part stored while the decode ran stays waiting, so
     * `ingest:sweep` still finds it if its own job is lost.
     *
     * @param  list<string>  $partIds  lower-case hex
     */
    public function handle(int $characterId, int $sessionStart, array $partIds): void
    {
        if ($partIds === []) {
            return;
        }

        DB::table('parts')
            ->where('character_id', $characterId)
            ->where('session_start', $sessionStart)
            ->whereRaw("encode(id, 'hex') = any(?)", ['{'.implode(',', $partIds).'}'])
            ->update(['decoded_at' => now()]);
    }
}
