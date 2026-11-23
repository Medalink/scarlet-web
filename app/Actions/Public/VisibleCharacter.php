<?php

namespace App\Actions\Public;

use App\Models\Character;

class VisibleCharacter
{
    /** The character of a name when the viewer may see its page: public, or the viewer's own. Null for an unknown name and for a character private to this viewer, so the two cannot be told apart. */
    public function handle(string $name, ?int $viewerAccountId): ?Character
    {
        $character = Character::query()->where('name_key', Character::keyFor($name))->first(['id', 'name', 'account_id', 'is_public']);

        return $character !== null && $character->visibleTo($viewerAccountId) ? $character : null;
    }
}
