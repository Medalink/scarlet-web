<?php

namespace App\Support\ClassPage;

/** One figure's place among the class in a level bracket: the character is above `percent` of them, 0 to 100. */
final readonly class Standing
{
    public function __construct(public string $label, public int|float|string $value, public int $percent, public Unit $unit = Unit::Count, public ?Icon $icon = null) {}
}
