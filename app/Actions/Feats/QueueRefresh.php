<?php

namespace App\Actions\Feats;

use App\Jobs\RefreshCharacterFeats;
use Illuminate\Support\Facades\DB;

/** Queues a feat recount for one character, or for every character that has a stored session. */
class QueueRefresh
{
    /**
     * @param  int|null  $characterId  one character, or null for every character with a session
     * @return int characters queued
     */
    public function handle(?int $characterId): int
    {
        if ($characterId !== null) {
            RefreshCharacterFeats::dispatch($characterId);

            return 1;
        }

        $queued = 0;
        $characters = DB::table('characters')->select('id')
            ->whereIn('id', fn ($query) => $query->select('character_id')->distinct()->from('sessions'))
            ->orderBy('id');
        $characters->chunkById(500, function ($rows) use (&$queued): void {
            foreach ($rows as $row) {
                RefreshCharacterFeats::dispatch((int) $row->id);
                $queued++;
            }
        });

        return $queued;
    }
}
