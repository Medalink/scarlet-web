<?php

namespace App\Actions\Account;

use App\Actions\Feats\MarkFeatsSeen;
use App\Models\Account;
use Illuminate\Auth\Access\AuthorizationException;

class MarkOwnedFeatsSeen
{
    public function __construct(private readonly MarkFeatsSeen $mark) {}

    /**
     * Record the feat tiers shown to the owner of one of the account's characters.
     *
     * @param  array<array-key, int>  $shown  feat key => the tier that was shown
     *
     * @throws AuthorizationException when the character is not the account's
     */
    public function handle(Account $account, int $characterId, array $shown): void
    {
        $character = $account->characters()->whereKey($characterId)->first()
            ?? throw new AuthorizationException('This character is not on your account.');

        $this->mark->handle($character, $shown);
    }
}
