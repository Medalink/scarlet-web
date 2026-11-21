<?php

namespace App\Actions\Account;

use App\Actions\Upload\RemoveGroup;
use App\Models\Account;
use App\Support\SharingGroups;
use Illuminate\Auth\Access\AuthorizationException;

class RemoveGroupFromServer
{
    public function __construct(private readonly SharingGroups $groups, private readonly RemoveGroup $remove) {}

    /**
     * The same removal as `DELETE /api/v1/upload/groups/{key}`: the group's parts and decoded rows go for every
     * character of the account, and the group stays refused. It runs through any install of the account, since the
     * removal covers the whole owner. An account with no install holds no characters.
     *
     * @return array{parts_removed: int, rows_removed: int}
     *
     * @throws AuthorizationException for a group that is not a switch
     */
    public function handle(Account $account, string $group): array
    {
        if (! $this->groups->isSwitchable($group)) {
            throw new AuthorizationException('This group cannot be removed.');
        }
        $install = $account->installs()->orderBy('created_at')->first();
        if ($install === null) {
            return ['parts_removed' => 0, 'rows_removed' => 0];
        }
        $removed = $this->remove->handle($install, $group);

        return ['parts_removed' => $removed['parts_removed'], 'rows_removed' => $removed['rows_removed']];
    }
}
