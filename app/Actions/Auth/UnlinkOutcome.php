<?php

namespace App\Actions\Auth;

enum UnlinkOutcome
{
    case Unlinked;

    /** It is the only sign-in method the account has. */
    case Last;

    case NotLinked;
}
