<?php

namespace App\Actions\Account;

use App\Models\Account;
use App\Models\RefusedGroup;
use App\Support\SharingGroups;
use Illuminate\Auth\Access\AuthorizationException;

class SetGroupShared
{
    public function __construct(private readonly SharingGroups $groups) {}

    /**
     * Turn a group off or on for the account. Off stores a refusal, so the app's next sync is answered
     * `group_refused` and the app turns its switch off; what the server holds stays until it is removed.
     * On clears the refusal.
     *
     * @throws AuthorizationException for a group that is not a switch (`core`, `names`) or does not exist
     */
    public function handle(Account $account, string $group, bool $shared): void
    {
        if (! $this->groups->isSwitchable($group)) {
            throw new AuthorizationException('This group cannot be switched.');
        }

        if ($shared) {
            RefusedGroup::query()->where('account_id', $account->id)->where('group_key', $group)->delete();

            return;
        }
        RefusedGroup::query()->insertOrIgnore([['account_id' => $account->id, 'group_key' => $group, 'created_at' => now()]]);
    }
}
