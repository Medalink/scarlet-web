<?php

namespace App\Actions\Auth;

/** How a sign-in ended. */
enum SignInOutcome
{
    /** The person is now signed in as the account that holds the identity. */
    case SignedIn;

    /** The identity belongs to the account that was already signed in (now, or before). */
    case Linked;

    /** The identity belongs to another account than the one signed in. */
    case Taken;

    /** The signed-in account already has a different identity from this provider. */
    case OtherSubject;

    /** The provider gave no usable subject id. */
    case Refused;
}
