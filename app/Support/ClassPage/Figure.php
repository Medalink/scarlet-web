<?php

namespace App\Support\ClassPage;

/** One of the page's figures: the six under the head and the five of the band on the character page. `of` follows the value in small type: "of 212". */
final readonly class Figure
{
    public function __construct(
        public string $label,
        public int|float|string $value,
        public Unit $unit = Unit::Count,
        public ?string $of = null,
        public ?Icon $icon = null,
    ) {}
}
