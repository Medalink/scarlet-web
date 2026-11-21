<?php

namespace App\Actions\Account;

use App\Models\Account;

class ShowOtherCharacters
{
    /** Names the owner bar lists. */
    public const int LIMIT = 12;

    /** @return list<string> the names of the account's other characters, most played first */
    public function handle(Account $account, int $characterId): array
    {
        return array_values($account->characters()->whereKeyNot($characterId)->orderByDesc('played_seconds')->orderBy('name')->limit(self::LIMIT)->pluck('name')->all());
    }
}
