<?php

namespace App\Actions\Account;

use App\Models\Install;
use App\Models\LinkCode;
use App\Support\Database\BinaryValue;
use App\Support\LinkCodeHash;

/** What the confirm page shows about the install a code belongs to, or null when no code is waiting. */
class DescribeLinkInstall
{
    public function handle(string $code): ?Install
    {
        $link = LinkCode::query()->where('code_hash', BinaryValue::bind(LinkCodeHash::of($code)))->first();

        return $link === null ? null : Install::query()->whereKey($link->install_id)->withCount('characters')->first();
    }
}
