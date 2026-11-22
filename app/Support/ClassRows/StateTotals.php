<?php

namespace App\Support\ClassRows;

/** The time a character spent in one state (an aura, a pet, a totem), with its starts and the part inside fights, in all and by level. */
final readonly class StateTotals
{
    /**
     * @param  array<int, array{starts: int, ms: int, fightMs: int}>  $byLevel
     */
    public function __construct(
        public int $refId,
        public string $name,
        public string $extra,
        public int $starts,
        public int $ms,
        public int $fightMs,
        public array $byLevel,
    ) {}

    public function hours(): float
    {
        return $this->ms / 3_600_000;
    }

    public function msAt(int $level): int
    {
        return $this->byLevel[$level]['ms'] ?? 0;
    }
}
