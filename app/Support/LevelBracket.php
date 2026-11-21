<?php

namespace App\Support;

/** The level brackets the talent pages read a build in. A character is counted in a bracket by its latest build read at a level inside it. */
enum LevelBracket: string
{
    case All = 'all';
    case L10 = '10-19';
    case L20 = '20-29';
    case L30 = '30-39';
    case L40 = '40-49';
    case L50 = '50-59';
    case L60 = '60';

    /** @return array{0: int, 1: int} */
    public function range(): array
    {
        return match ($this) {
            self::All => [1, 255],
            self::L10 => [10, 19],
            self::L20 => [20, 29],
            self::L30 => [30, 39],
            self::L40 => [40, 49],
            self::L50 => [50, 59],
            self::L60 => [60, 255],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::All => 'All levels',
            self::L60 => 'Level 60',
            default => 'Levels '.str_replace('-', ' to ', $this->value),
        };
    }
}
