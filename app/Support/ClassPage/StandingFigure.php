<?php

namespace App\Support\ClassPage;

/**
 * One of the six figures a class page compares with other characters of the class: the number itself, stored under `key`
 * in `class_standings` and drawn as a place among the class. Only the key, the label and the unit are the builder's.
 */
final readonly class StandingFigure
{
    public function __construct(public string $key, public string $label, public int|float $value, public Unit $unit = Unit::Count, public ?Icon $icon = null) {}
}
