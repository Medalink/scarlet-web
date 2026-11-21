<?php

namespace App\Actions\Account;

use App\Models\Account;
use App\Models\Identity;

class ShowSignInMethods
{
    /** @return list<Identity> the sign-in methods the account holds */
    public function handle(Account $account): array
    {
        return array_values($account->identities()->select(['id', 'provider', 'created_at'])->get()->all());
    }
}
