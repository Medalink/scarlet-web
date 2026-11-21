<?php

namespace App\Actions\Account;

use App\Models\Account;

class CreateApiKey
{
    /**
     * Make an API key with the one ability of the public read API. The text of the key is returned once; only its
     * hash is stored.
     *
     * @return string|null the key, or null when the account already holds the most it may
     */
    public function handle(Account $account, string $name): ?string
    {
        if ($account->tokens()->count() >= Account::MAX_API_KEYS) {
            return null;
        }

        return $account->createToken($name, [Account::API_ABILITY])->plainTextToken;
    }
}
