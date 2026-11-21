<?php

namespace App\View\Design;

/**
 * The seeded random numbers docs/comps/assets/comp.js draws its invented data from, ported bit for bit, so
 * the gallery draws the same pictures as the comps.
 */
final class Rng
{
    private int $state;

    public function __construct(int $seed)
    {
        $this->state = $seed & 0xFFFFFFFF;
    }

    public function next(): float
    {
        $s = $this->state;
        $this->state = (self::imul($s ^ ($s >> 15), 2246822507) + 0x9E3779B9) & 0xFFFFFFFF;

        return $this->state / 4294967296;
    }

    /** The low 32 bits of a product of two 32-bit numbers, without overflowing a 64-bit integer. */
    private static function imul(int $a, int $b): int
    {
        return ((($a & 0xFFFF) * $b) + (((($a >> 16) * $b) & 0xFFFF) << 16)) & 0xFFFFFFFF;
    }
}
