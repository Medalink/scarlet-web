<?php

namespace App\Actions\Account;

use App\Actions\Upload\RemoveEverything;
use App\Models\Account;

class RemoveAccountUploads
{
    public function __construct(private readonly RemoveEverything $remove) {}

    /**
     * The same removal as `DELETE /api/v1/upload`: every part, every decoded row and the characters themselves.
     * Installs, their keys and the account stay.
     *
     * @return array{characters_removed: int, parts_removed: int, rows_removed: int}
     */
    public function handle(Account $account): array
    {
        $install = $account->installs()->orderBy('created_at')->first();
        if ($install === null) {
            return ['characters_removed' => 0, 'parts_removed' => 0, 'rows_removed' => 0];
        }

        return $this->remove->handle($install);
    }
}
