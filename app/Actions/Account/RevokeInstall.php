<?php

namespace App\Actions\Account;

use App\Models\Account;
use Illuminate\Auth\Access\AuthorizationException;

class RevokeInstall
{
    /**
     * Stop an install's key: its signed requests are refused from now on. What it uploaded stays.
     *
     * @throws AuthorizationException when the install is not the account's
     */
    public function handle(Account $account, string $installId): void
    {
        $install = $account->installs()->whereKey($installId)->first()
            ?? throw new AuthorizationException('This install is not on your account.');

        if ($install->revoked_at === null) {
            $install->revoked_at = now();
            $install->save();
        }
    }
}
