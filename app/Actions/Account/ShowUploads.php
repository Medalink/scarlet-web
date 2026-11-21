<?php

namespace App\Actions\Account;

use App\Models\Account;
use App\Models\Part;
use App\Models\PlaySession;

class ShowUploads
{
    /**
     * What "Delete everything I uploaded" removes.
     *
     * @return array{characters: int, sessions: int, parts: int}
     */
    public function handle(Account $account): array
    {
        $ids = $account->characters()->select('id');

        return [
            'characters' => $account->characters()->count(),
            'sessions' => PlaySession::query()->whereIn('character_id', $ids)->count(),
            'parts' => Part::query()->whereIn('character_id', $ids)->count(),
        ];
    }
}
