<?php

namespace App\Support\ClassPage;

/** One part of a split: its name in the key, its size and its colour. */
final readonly class SplitPart
{
    public function __construct(public string $label, public int|float $value, public Tone|Hue $tone) {}
}
