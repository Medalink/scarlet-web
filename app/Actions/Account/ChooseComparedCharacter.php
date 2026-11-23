<?php

namespace App\Actions\Account;

use App\Models\Account;

class ChooseComparedCharacter
{
    /**
     * The character a page compares to everyone: the one of the account named by `$name`, else the most played.
     *
     * @return array{id: int, name: string}|null null when the account has no character
     */
    public function handle(Account $account, ?string $name): ?array
    {
        $characters = $account->characters()->orderByDesc('played_seconds')->orderBy('name_key')->get(['id', 'name']);
        $chosen = $characters->first(fn ($character): bool => $name !== null && strcasecmp((string) $character->name, $name) === 0) ?? $characters->first();

        return $chosen === null ? null : ['id' => (int) $chosen->id, 'name' => (string) $chosen->name];
    }
}
