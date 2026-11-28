<?php

namespace App\Actions\Account;

/** What CreateApiKey did; `key` is the text of the new key, set only for Created. */
final readonly class ApiKeyResult
{
    public function __construct(public ApiKeyOutcome $outcome, public ?string $key = null) {}
}
