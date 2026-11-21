<?php

namespace App\Actions\Account;

use App\Models\Account;
use Illuminate\Auth\Access\AuthorizationException;

class DeleteApiKey
{
    /** @throws AuthorizationException when the key is not the account's */
    public function handle(Account $account, int $keyId): void
    {
        $token = $account->tokens()->whereKey($keyId)->first()
            ?? throw new AuthorizationException('This key is not on your account.');

        $token->delete();
    }
}
