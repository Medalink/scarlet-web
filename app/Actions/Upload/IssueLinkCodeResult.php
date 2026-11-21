<?php

namespace App\Actions\Upload;

use Carbon\CarbonImmutable;

final readonly class IssueLinkCodeResult
{
    public function __construct(public string $code, public CarbonImmutable $expiresAt) {}
}
