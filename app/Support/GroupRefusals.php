<?php

namespace App\Support;

use App\Models\Install;
use App\Models\RefusedGroup;
use Illuminate\Database\Eloquent\Builder;

/**
 * The sharing groups an install's owner removed from the server. The owner is the account when the
 * install is linked to one, else the install itself.
 */
class GroupRefusals
{
    /**
     * @param  list<string>  $groups
     * @return list<string> the groups among these that are refused
     */
    public function among(Install $install, array $groups): array
    {
        /** @var list<string> */
        return $this->query($install)->whereIn('group_key', $groups)->orderBy('group_key')->pluck('group_key')->all();
    }

    /** @return list<string> */
    public function all(Install $install): array
    {
        /** @var list<string> */
        return $this->query($install)->orderBy('group_key')->pluck('group_key')->all();
    }

    public function add(Install $install, string $group): void
    {
        $owner = $install->account_id !== null ? ['account_id' => $install->account_id] : ['install_id' => $install->id];
        RefusedGroup::query()->insertOrIgnore([$owner + ['group_key' => $group, 'created_at' => now()]]);
    }

    public function clear(Install $install, string $group): void
    {
        $this->query($install)->where('group_key', $group)->delete();
    }

    /** @return Builder<RefusedGroup> */
    private function query(Install $install): Builder
    {
        return RefusedGroup::query()->where(function (Builder $owner) use ($install): void {
            $owner->where('install_id', $install->id);
            if ($install->account_id !== null) {
                $owner->orWhere('account_id', $install->account_id);
            }
        });
    }
}
