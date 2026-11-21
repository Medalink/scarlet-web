<?php

namespace App\Actions\Account;

use App\Models\Account;
use App\Models\Character;
use Illuminate\Auth\Access\AuthorizationException;

class ShowOwnedCharacter
{
    /**
     * One of the account's characters, for the owner bar of its page.
     *
     * @throws AuthorizationException when the character is not the account's
     */
    public function handle(Account $account, int $characterId): Character
    {
        return $account->characters()
            ->select(['id', 'name', 'sessions_decoded', 'last_seen_at', 'is_public', 'show_economy'])
            ->whereKey($characterId)
            ->first() ?? throw new AuthorizationException('This character is not on your account.');
    }
}
