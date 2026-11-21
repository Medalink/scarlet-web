<?php

namespace App\Actions\Account;

use App\Models\Account;
use App\Models\Character;

class ShowCharacters
{
    /** @return list<Character> the account's characters by name, with what the account page lists */
    public function handle(Account $account): array
    {
        return array_values($account->characters()
            ->select(['id', 'name', 'class', 'race', 'level', 'sessions_decoded', 'last_seen_at', 'is_public', 'show_economy'])
            ->orderBy('name')
            ->get()
            ->all());
    }
}
