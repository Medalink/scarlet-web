<?php

namespace App\Support\ClassRows;

/** A spell that a damage meter listed among a fight's five largest, with the ranks of one name folded: its amount, the fights it was listed in and the icon of the rank listed most. */
final readonly class MeterSpell
{
    public function __construct(public string $name, public int $iconId, public int $amount, public int $fights) {}
}
