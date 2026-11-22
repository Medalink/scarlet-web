<?php

namespace App\Actions\Public\ClassPage;

use App\Actions\Public\CountBattlegroundMatches;
use App\Models\Character;
use App\Support\ClassPage\CharacterLine;
use App\Support\ClassPage\ClassPage;
use App\Support\ClassPage\Links;
use App\Support\PlayerClass;
use App\View\Format;

class CharacterClassPage
{
    public function __construct(
        private readonly ReadClassParts $read,
        private readonly ReadClassComparison $comparison,
        private readonly ClassFeatCards $feats,
        private readonly CountBattlegroundMatches $matches,
    ) {}

    /**
     * The class page of a character: what the class's builder made of its rows, the character line, the links to its
     * other pages and its feats. Null when the character is unknown or private to this viewer, when `$slug` is not the
     * character's own class, or when the class has no page for it (no builder, or no cast counted).
     *
     * @return array{character: Character, owner: bool, page: ClassPage}|null
     */
    public function handle(string $name, string $slug, ?int $viewerAccountId): ?array
    {
        $character = Character::query()->where('name_key', Character::keyFor($name))->first();
        $class = PlayerClass::tryFrom(strtoupper($slug));
        if ($character === null || ! $character->visibleTo($viewerAccountId) || $class === null || strtoupper((string) $character->class) !== $class->value) {
            return null;
        }
        $read = $this->read->handle($character);
        if ($read === null) {
            return null;
        }
        $path = '/c/'.rawurlencode($character->name);
        $parts = $read->parts;
        $page = new ClassPage(
            $class,
            new CharacterLine(Format::characterName($character->name), (int) $character->level, $read->facts->hours, $read->facts->build, $parts->icon),
            new Links($path, $path.'/'.$class->slug(), null, $this->matches->handle($character->id) > 0 ? $path.'/battlegrounds' : null),
            $parts->callouts,
            $parts->figures,
            $parts->sheet,
            $parts->talents,
            $parts->party,
            $this->comparison->handle($read),
            $this->feats->handle($read),
        );

        return ['character' => $character, 'owner' => $viewerAccountId !== null && $character->account_id === $viewerAccountId, 'page' => $page];
    }
}
