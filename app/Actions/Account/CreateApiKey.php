<?php

namespace App\Actions\Account;

use App\Models\Account;

class CreateApiKey
{
    /**
     * Make an API key with the one ability of the public read API, valid for `$days` days. The text of the key is
     * returned once; only its hash is stored. An account makes keys only while it signs in with Battle.net.
     */
    public function handle(Account $account, string $name, int $days = Account::API_KEY_DAYS): ApiKeyResult
    {
        if ($days < 1 || $days > Account::API_KEY_MAX_DAYS) {
            return new ApiKeyResult(ApiKeyOutcome::BadLifetime);
        }
        if (! $account->hasBattlenet()) {
            return new ApiKeyResult(ApiKeyOutcome::NoBattlenet);
        }
        if ($account->tokens()->count() >= Account::MAX_API_KEYS) {
            return new ApiKeyResult(ApiKeyOutcome::TooMany);
        }

        return new ApiKeyResult(ApiKeyOutcome::Created, $account->createToken($name, [Account::API_ABILITY], now()->addDays($days))->plainTextToken);
    }
}
