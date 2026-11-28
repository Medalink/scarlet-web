<?php

namespace App\Actions\Account;

enum ApiKeyOutcome
{
    case Created;

    /** The account has no Battle.net sign-in. */
    case NoBattlenet;

    /** The account already holds the most keys it may. */
    case TooMany;

    /** The lifetime is not between one day and the most a key may live. */
    case BadLifetime;
}
