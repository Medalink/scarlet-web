<?php

namespace App\Actions\Account;

/** The two switches of a character page that its owner controls. */
enum CharacterSwitch
{
    /** The character page is public. */
    case PublicPage;

    /** A public page also shows gold and trades. */
    case Economy;
}
