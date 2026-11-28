<?php

namespace App\Actions\Account;

use App\Models\Account;

class ExpireApiKeys
{
    /** Make the account's keys named `$name` expire a minute ago, for the development sign-in's end-to-end checks. */
    public function handle(Account $account, string $name): void
    {
        $account->tokens()->where('name', $name)->update(['expires_at' => now()->subMinute()]);
    }
}
