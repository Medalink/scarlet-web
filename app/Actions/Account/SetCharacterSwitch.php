<?php

namespace App\Actions\Account;

use App\Models\Account;
use Illuminate\Auth\Access\AuthorizationException;

class SetCharacterSwitch
{
    /**
     * Turn the public page, or the gold and trades or the session pages of it, on or off for one of the account's characters.
     *
     * @throws AuthorizationException when the character is not the account's
     */
    public function handle(Account $account, int $characterId, CharacterSwitch $switch, bool $on): void
    {
        $character = $account->characters()->whereKey($characterId)->first()
            ?? throw new AuthorizationException('This character is not on your account.');

        match ($switch) {
            CharacterSwitch::PublicPage => $character->is_public = $on,
            CharacterSwitch::Economy => $character->show_economy = $on,
            CharacterSwitch::Sessions => $character->show_sessions = $on,
        };
        $character->save();
    }
}
