<?php

namespace App\Support\ClassPage;

/** Two series by level from one axis, one up and one down (mana for health, healing against damage). */
final readonly class Mirror implements Block
{
    /** @param  list<MirrorPoint>  $points  in level order */
    public function __construct(public array $points, public Series $up, public Series $down, public string $label, public Unit $unit = Unit::Short) {}
}
