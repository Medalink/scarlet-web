<?php

namespace App\Actions\Account;

use App\Models\Account;
use Laravel\Sanctum\PersonalAccessToken;

class ShowApiKeys
{
    /** @return list<PersonalAccessToken> the account's keys, newest first (never the key text) */
    public function handle(Account $account): array
    {
        /** @var list<PersonalAccessToken> */
        return $account->tokens()->select(['id', 'name', 'created_at', 'last_used_at'])->orderByDesc('created_at')->get()->all();
    }
}
