<?php

namespace App\Actions\Account;

use App\Models\Account;
use App\Models\Install;

class ShowInstalls
{
    /** @return list<Install> the account's installs, newest link first, each with `characters_count` */
    public function handle(Account $account): array
    {
        return array_values($account->installs()
            ->select(['id', 'name', 'linked_at', 'created_at', 'last_seen_at', 'revoked_at'])
            ->withCount('characters')
            ->orderByDesc('linked_at')
            ->orderByDesc('created_at')
            ->get()
            ->all());
    }
}
