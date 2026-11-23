<?php

namespace App\Actions\Public\ClassPage;

use App\Models\Character;
use App\Support\ClassPage\CharacterLine;
use App\Support\ClassPage\ClassPage;
use App\View\Format;

class BuildClassPage
{
    public function __construct(
        private readonly ReadClassParts $read,
        private readonly ReadClassComparison $comparison,
        private readonly ClassFeatCards $feats,
        private readonly CharacterLinks $links,
    ) {}

    /** The class page of a character whose visibility the caller has settled, or null when its class has no page for it. */
    public function handle(Character $character): ?ClassPage
    {
        $read = $this->read->handle($character);
        if ($read === null) {
            return null;
        }
        $parts = $read->parts;

        return new ClassPage(
            $read->class,
            new CharacterLine(Format::characterName($character->name), (int) $character->level, $read->facts->hours, $read->facts->build, $parts->icon),
            $this->links->handle($character, true),
            $parts->callouts,
            $parts->figures,
            $parts->sheet,
            $parts->talents,
            $parts->party,
            $this->comparison->handle($read),
            $this->feats->handle($read),
        );
    }
}
