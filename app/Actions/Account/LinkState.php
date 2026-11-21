<?php

namespace App\Actions\Account;

/** Where a link code stands for the account that holds it in the browser. */
enum LinkState
{
    /** The code is good: confirming links the install. */
    case Ready;

    /** The install is linked to the account now. */
    case Linked;

    case Unknown;

    case Used;

    case Expired;

    /** The install already belongs to another account. */
    case OtherAccount;

    /** The install already belongs to this account. */
    case AlreadyYours;
}
