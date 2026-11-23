<?php

namespace App\Actions\Upload;

use Carbon\CarbonImmutable;

final readonly class IssueLinkCodeResult
{
    /** @param string $redeemMessage the line the install signs so its holder can redeem the code */
    public function __construct(public string $code, public CarbonImmutable $expiresAt, public string $redeemMessage) {}
}
