<?php

namespace App\Actions\Auth;

use App\Models\Account;
use App\Models\IdentityProvider;
use Illuminate\Support\Facades\DB;

class UnlinkIdentity
{
    /** Remove one sign-in method from the account, unless it is the last one. Without Battle.net the account's API keys go too. */
    public function handle(Account $account, IdentityProvider $provider): UnlinkOutcome
    {
        return DB::transaction(function () use ($account, $provider): UnlinkOutcome {
            // Two unlinks at once must not leave the account without a way in.
            Account::query()->whereKey($account->id)->lockForUpdate()->first();

            $identities = $account->identities()->get(['id', 'provider']);
            $identity = $identities->firstWhere('provider', $provider);
            if ($identity === null) {
                return UnlinkOutcome::NotLinked;
            }
            if ($identities->count() < 2) {
                return UnlinkOutcome::Last;
            }
            $identity->delete();
            if ($provider === IdentityProvider::Battlenet) {
                $account->tokens()->delete();
            }

            return UnlinkOutcome::Unlinked;
        });
    }
}
