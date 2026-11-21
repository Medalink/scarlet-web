<?php

namespace App\Support;

use App\Actions\Account\LinkState;
use App\Models\Account;
use App\Models\LinkCode;

/** The one reading of a link code's row, shared by the check and the link. */
final class LinkJudge
{
    public static function state(Account $account, ?LinkCode $link): LinkState
    {
        if ($link === null) {
            return LinkState::Unknown;
        }
        $owner = $link->install?->account_id;
        if ($owner !== null) {
            return $owner === $account->id ? LinkState::AlreadyYours : LinkState::OtherAccount;
        }
        if ($link->used_at !== null) {
            return LinkState::Used;
        }

        return $link->expires_at->isPast() ? LinkState::Expired : LinkState::Ready;
    }
}
