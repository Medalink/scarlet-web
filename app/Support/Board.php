<?php

namespace App\Support;

use App\View\Format;

/** The leaderboards. The value is the name in URLs and in the API (`/leaderboards/{board}`). */
enum Board: string
{
    case FastestTo60 = 'fastest-to-60';
    case FastestTo40 = 'fastest-to-40';
    case FastestTo20 = 'fastest-to-20';
    case HighestWithoutDying = 'highest-level-without-dying';
    case MostQuests = 'most-quests';
    case MostDungeonRuns = 'most-dungeon-runs';
    case MostDistance = 'most-distance';

    public function title(): string
    {
        return match ($this) {
            self::FastestTo60, self::FastestTo40, self::FastestTo20 => 'Fastest to level '.$this->level(),
            self::HighestWithoutDying => 'Highest level without dying',
            self::MostQuests => 'Most quests turned in',
            self::MostDungeonRuns => 'Most dungeon runs',
            self::MostDistance => 'Most distance travelled',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FastestTo60, self::FastestTo40, self::FastestTo20 => 'Played time from the first recorded session to level '.$this->level().'. Only characters whose owner made them public, and whose recording covers every level, are listed.',
            self::HighestWithoutDying => 'The highest level reached by public characters with no recorded death.',
            self::MostQuests => 'Quests turned in by public characters, from counted sessions.',
            self::MostDungeonRuns => 'Dungeon runs by public characters, from counted sessions.',
            self::MostDistance => 'The length of the recorded path on the world map, in world widths: 1.0 is the width of the map. Teleports and loading screens are left out.',
        };
    }

    /** The level a "fastest to" board races to, or null. */
    public function level(): ?int
    {
        return match ($this) {
            self::FastestTo60 => 60,
            self::FastestTo40 => 40,
            self::FastestTo20 => 20,
            default => null,
        };
    }

    /** The heading of the value column. */
    public function column(): string
    {
        return match ($this) {
            self::FastestTo60, self::FastestTo40, self::FastestTo20 => 'Played to '.$this->level(),
            self::HighestWithoutDying => 'Level',
            self::MostQuests => 'Quests',
            self::MostDungeonRuns => 'Runs',
            self::MostDistance => 'World widths',
        };
    }

    /** The heading of the last column: a fastest board shows the day the level was reached. */
    public function dateColumn(): string
    {
        return $this->level() === null ? 'Last played' : 'Reached';
    }

    /** A board value as the table shows it. */
    public function format(int|float $value): string
    {
        return match ($this) {
            self::FastestTo60, self::FastestTo40, self::FastestTo20 => Format::exact($value),
            self::MostDistance => Format::number($value, 1),
            default => Format::number($value),
        };
    }
}
