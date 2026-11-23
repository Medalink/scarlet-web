<?php

namespace App\Support;

/** How a general feat is counted across the characters of an account (docs/DESIGN.md, feat crests). */
enum AccountRule: string
{
    case Sum = 'sum';
    case Max = 'max';
    case None = 'none';

    /** @param  array{0: int, 1: int, 2: int, 3: int, 4: int}  $thresholds a character's thresholds
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int}|null
     */
    public function thresholds(array $thresholds): ?array
    {
        return match ($this) {
            self::Sum => [$thresholds[0], $thresholds[1] * 2, $thresholds[2] * 2, $thresholds[3] * 2, $thresholds[4] * 2],
            self::Max => $thresholds,
            self::None => null,
        };
    }
}
