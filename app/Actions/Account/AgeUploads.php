<?php

namespace App\Actions\Account;

use App\Models\Account;
use Illuminate\Support\Facades\DB;

class AgeUploads
{
    /**
     * Move the storage time of one of the account's characters' parts back by `$hours`, for the development
     * sign-in's end-to-end checks of the 24 hour list and the full history.
     *
     * @return int the parts moved
     */
    public function handle(Account $account, string $character, int $hours): int
    {
        return DB::update(
            'update parts set created_at = created_at - make_interval(hours => ?) where character_id in (select id from characters where account_id = ? and name = ?)',
            [$hours, $account->id, $character],
        );
    }
}
