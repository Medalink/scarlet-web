<?php

namespace App\Actions\Public\ClassPage;

use App\Models\Character;
use App\Support\ClassPage\ClassPage;
use App\Support\PlayerClass;

class CharacterClassPage
{
    public function __construct(private readonly BuildClassPage $build) {}

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
        $page = $this->build->handle($character);
        if ($page === null) {
            return null;
        }

        return ['character' => $character, 'owner' => $viewerAccountId !== null && $character->account_id === $viewerAccountId, 'page' => $page];
    }
}
