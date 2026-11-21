<?php

namespace App\Actions\Account;

use App\Models\Account;
use App\Models\RefusedGroup;
use App\Support\GroupDescriptions;
use App\Support\SharingGroup;
use App\Support\SharingGroups;

class ShowSharing
{
    public function __construct(private readonly SharingGroups $groups) {}

    /**
     * One row per sharing group of docs/groups.tsv that a person can see: `core` on and locked, the others on
     * unless the account refused them. `names` is not a switch and has no row.
     *
     * @return list<array{key: string, label: string, body: string, on: bool, locked: bool}>
     */
    public function handle(Account $account): array
    {
        /** @var list<string> $refused */
        $refused = RefusedGroup::query()->where('account_id', $account->id)->pluck('group_key')->all();

        $rows = [];
        foreach ($this->groups->labels() as $key => $label) {
            $locked = $key === SharingGroup::CORE;
            if (! $locked && ! $this->groups->isSwitchable($key)) {
                continue;
            }
            $rows[] = ['key' => $key, 'label' => $label, 'body' => GroupDescriptions::of($key), 'on' => $locked || ! in_array($key, $refused, strict: true), 'locked' => $locked];
        }

        return $rows;
    }
}
