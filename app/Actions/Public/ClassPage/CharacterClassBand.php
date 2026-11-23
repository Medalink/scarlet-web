<?php

namespace App\Actions\Public\ClassPage;

use App\Models\Character;
use App\Support\ClassPage\ClassBand;
use App\Support\ClassPage\StoredBand;
use App\Support\PlayerClass;
use Illuminate\Support\Facades\DB;

class CharacterClassBand
{
    /**
     * The class figures on a character's page, linking to its class page, from the row `StoreClassStandings` kept: one
     * read of `class_bands`, no class table. Null when the character has no stored band, or the band is of another class.
     */
    public function handle(Character $character): ?ClassBand
    {
        $class = PlayerClass::tryFrom(strtoupper((string) $character->class));
        $row = $class === null ? null : DB::table('class_bands')->where('character_id', $character->id)->first(['class', 'icon', 'figures']);
        if ($class === null || $row === null || $row->class !== $class->value) {
            return null;
        }

        return StoredBand::restore($class, '/c/'.rawurlencode($character->name).'/'.$class->slug(), $row->icon === null ? null : (string) $row->icon, (string) $row->figures);
    }
}
