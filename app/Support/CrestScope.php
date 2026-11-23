<?php

namespace App\Support;

/** The shield a feat's crest wears: the character's, or the class shield of every feat that depends on the class. */
enum CrestScope: string
{
    case Character = 'character';
    case ClassBased = 'class';
}
