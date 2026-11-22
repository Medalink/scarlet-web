<?php

namespace App\Actions\Public\ClassPage;

use App\Models\Character;
use App\Support\ClassPage\ClassBand;

class CharacterClassBand
{
    public function __construct(private readonly ReadClassParts $read) {}

    /** The five class figures on a character's page, linking to its class page. Null when the character has no class page. */
    public function handle(Character $character): ?ClassBand
    {
        $read = $this->read->handle($character);

        return $read === null ? null : new ClassBand($read->class, '/c/'.rawurlencode($character->name).'/'.$read->class->slug(), $read->parts->band, $read->parts->icon);
    }
}
