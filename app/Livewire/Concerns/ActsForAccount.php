<?php

namespace App\Livewire\Concerns;

use App\Models\Account;
use Livewire\Attributes\On;

/**
 * Every component of the account page acts for the signed-in account and for nobody else. The account comes
 * from the session on each request; an id sent by the browser is checked against it by the Action that uses it.
 */
trait ActsForAccount
{
    protected function account(): Account
    {
        $account = request()->user();

        return $account instanceof Account ? $account : abort(403);
    }

    /** Another component changed something this one shows; the call alone re-renders it. */
    #[On('account-changed')]
    public function refreshed(): void {}
}
