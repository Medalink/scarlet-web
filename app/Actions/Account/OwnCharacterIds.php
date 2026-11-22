<?php

namespace App\Actions\Account;

use App\Models\Account;

class OwnCharacterIds
{
    /**
     * The ids of every character of the account: the only characters a player's own page reads.
     *
     * @return list<int>
     */
    public function handle(Account $account): array
    {
        return array_values(array_map(fn (mixed $id): int => (int) $id, $account->characters()->pluck('id')->all()));
    }
}
