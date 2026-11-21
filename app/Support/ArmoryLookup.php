<?php

namespace App\Support;

/** How one look at a character on the Forever Armory ended. */
enum ArmoryLookup: string
{
    /** The page named the ruleset, and this code knows it. */
    case Found = 'found';

    /** The page named a ruleset this code has no kind for; the slug is stored. */
    case Unknown = 'unknown';

    /** The Armory has no page for the character (it answers 404 or 500 for one it does not hold). */
    case Missing = 'missing';

    /** The Armory could not be reached or asked to slow down; nothing was learned and nothing counts against the character. */
    case Transient = 'transient';
}
