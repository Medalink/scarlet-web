<?php

namespace App\Actions\Public\ClassPage;

use App\Actions\Public\CountBattlegroundMatches;
use App\Models\Character;
use App\Support\ClassPage\Links;
use App\Support\PlayerClass;

class CharacterLinks
{
    public function __construct(private readonly HasClassPage $has, private readonly CountBattlegroundMatches $matches) {}

    /**
     * The line of links under a character's head: the character, its class page and its battlegrounds, each only when
     * that page has something to show. A caller that already knows whether the class page exists passes the answer, so a
     * request asks the rule once.
     */
    public function handle(Character $character, ?bool $hasClassPage = null): Links
    {
        $path = '/c/'.rawurlencode($character->name);
        $class = PlayerClass::tryFrom(strtoupper((string) $character->class));
        $classPage = $class !== null && ($hasClassPage ?? $this->has->handle($character)) ? $path.'/'.$class->slug() : null;

        return new Links($path, $classPage, null, $this->matches->handle($character->id) > 0 ? $path.'/battlegrounds' : null);
    }
}
