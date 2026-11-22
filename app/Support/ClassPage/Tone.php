<?php

namespace App\Support\ClassPage;

/**
 * What a bar, ribbon or part stands for: the class colour carries what the class makes and does, scarlet the recorded
 * totals, grey what was lost or wasted, white what is still held (docs/DESIGN.md, Class pages, Colour).
 */
enum Tone: string
{
    case Own = 'class';
    case Scarlet = 'scarlet';
    case Lost = 'lost';
    case Held = 'held';
}
