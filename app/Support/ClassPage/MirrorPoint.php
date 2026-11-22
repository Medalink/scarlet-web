<?php

namespace App\Support\ClassPage;

/** One level of a mirror chart: the amount drawn up from the axis and the amount drawn down. */
final readonly class MirrorPoint
{
    public function __construct(public int $level, public int|float $up, public int|float $down) {}
}
