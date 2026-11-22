<?php

namespace App\Support\ClassPage;

/**
 * One row of a rank. `share` is the bar's length from 0 to 1; left out, the rank sets it from the row's value against
 * the largest. `aside` is a second, smaller label after the first, such as a pet's name.
 */
final readonly class RankRow
{
    public function __construct(
        public string $label,
        public int|float $value,
        public ?Icon $icon = null,
        public Tone|Hue|null $tone = null,
        public ?string $aside = null,
        public ?float $share = null,
    ) {}
}
