<?php

namespace App\Actions\Feats;

use App\Support\ClassPage\ClassRead;
use App\Support\ClassPage\StoredBand;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** Keeps the band of class figures a character's page shows in `class_bands`: one row, or none when the character has no class page. */
class StoreClassBand
{
    public function handle(int $characterId, ?ClassRead $read, CarbonInterface $now): void
    {
        if ($read === null) {
            DB::table('class_bands')->where('character_id', $characterId)->delete();

            return;
        }
        DB::table('class_bands')->upsert(
            [['character_id' => $characterId, 'class' => $read->class->value, 'counted_at' => $now, ...StoredBand::columns($read->parts->band, $read->parts->icon)]],
            ['character_id'],
            ['class', 'icon', 'figures', 'counted_at'],
        );
    }
}
