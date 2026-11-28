<?php

namespace App\Actions\Account;

use App\Models\Account;
use App\Models\IdentityProvider;

class DropBattlenetIdentity
{
    /** Remove the account's Battle.net sign-in and leave its API keys, the state `PublicApi` must refuse, for the development sign-in's end-to-end checks. */
    public function handle(Account $account): void
    {
        $account->identities()->where('provider', IdentityProvider::Battlenet)->delete();
    }
}
