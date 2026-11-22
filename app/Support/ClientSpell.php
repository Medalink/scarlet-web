<?php

namespace App\Support;

/** One spell name a class can learn and cast on the client: its lowest level over its ranks and its shortest cooldown. */
final class ClientSpell
{
    public function __construct(
        public readonly string $name,
        public readonly int $level,
        public readonly float $cooldown,
    ) {}
}
