<?php

namespace App\Actions\Auth;

use App\Models\Account;

final readonly class SignInResult
{
    public function __construct(public ?Account $account, public SignInOutcome $outcome) {}
}
