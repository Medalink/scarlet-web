<?php

namespace App\Support\ClassPage;

/** Two series by level from one axis, one up and one down (mana for health, healing against damage). It draws at most `MOST_POINTS` levels. */
final readonly class Mirror implements Block
{
    public const int MOST_POINTS = 60;

    /** @var list<MirrorPoint> in level order */
    public array $points;

    /** @param  list<MirrorPoint>  $points  in level order */
    public function __construct(array $points, public Series $up, public Series $down, public string $label, public Unit $unit = Unit::Short)
    {
        $this->points = array_slice($points, 0, self::MOST_POINTS);
    }
}
