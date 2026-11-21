<?php

namespace App\Actions\Account;

use App\Models\Account;
use App\Models\LinkCode;
use App\Support\Database\BinaryValue;
use App\Support\LinkCodeHash;
use App\Support\LinkJudge;

class CheckLinkCode
{
    /** What confirming the code would do, without doing it. The code is "XXXX-XXXX". */
    public function handle(Account $account, string $code): LinkState
    {
        $link = LinkCode::query()->where('code_hash', BinaryValue::bind(LinkCodeHash::of($code)))->with('install:id,account_id')->first();

        return LinkJudge::state($account, $link);
    }
}
