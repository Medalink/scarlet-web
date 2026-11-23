<?php

namespace App\Actions\Public;

use App\Actions\Account\OwnCharacterIds;
use App\Models\Account;

class MapSubject
{
    public function __construct(private readonly VisibleCharacter $visible, private readonly OwnCharacterIds $ownIds) {}

    /**
     * Whose trails a personal world map shows: one character the viewer may see (`$character`), or every character of the
     * signed-in viewer (`$character` null), private ones included. Null when the character is unknown or private to this
     * viewer, or when nobody is signed in to own the characters.
     *
     * @return array{name: string|null, ids: list<int>}|null name is null for the viewer's own characters
     */
    public function handle(?string $character, ?Account $viewer): ?array
    {
        if ($character === null) {
            return $viewer === null ? null : ['name' => null, 'ids' => $this->ownIds->handle($viewer)];
        }
        $found = $this->visible->handle($character, $viewer === null ? null : (int) $viewer->getKey());

        return $found === null ? null : ['name' => $found->name, 'ids' => [$found->id]];
    }
}
