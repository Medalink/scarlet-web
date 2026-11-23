<?php

namespace App\Actions\Account;

use App\Models\Account;

class OwnCharacterNames
{
    /**
     * The names of every character of the account, private ones included, in name order.
     *
     * @return list<string>
     */
    public function handle(Account $account): array
    {
        return array_values(array_map(fn (mixed $name): string => (string) $name, $account->characters()->orderBy('name_key')->pluck('name')->all()));
    }
}
