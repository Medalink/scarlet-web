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
    case MostQuestsCompleted = 'most-quests-completed';
    case MostKills = 'most-kills';
    case MostJumps = 'most-jumps';
    case MostDungeonRuns = 'most-dungeon-runs';
    case MostDistance = 'most-distance';
    case MostTimeByTheFire = 'most-time-by-the-fire';

    public function title(): string
    {
        return match ($this) {
            self::FastestTo60, self::FastestTo40, self::FastestTo20 => 'Fastest to level '.$this->level(),
            self::HighestWithoutDying => 'Highest level without dying',
            self::MostQuests => 'Most quests turned in',
            self::MostQuestsCompleted => 'Most quests completed',
            self::MostKills => 'Most kills',
            self::MostJumps => 'Most jumps',
            self::MostDungeonRuns => 'Most dungeon runs',
            self::MostDistance => 'Most distance travelled',
            self::MostTimeByTheFire => 'Most time by the fire',
        };
    }

    /** The glyph of the board, a name of `<x-icon>`. */
    public function icon(): string
    {
        return match ($this) {
            self::FastestTo60, self::FastestTo40, self::FastestTo20 => 'level',
            self::HighestWithoutDying => 'shield',
            self::MostQuests, self::MostQuestsCompleted => 'quests',
            self::MostKills => 'kills',
            self::MostJumps => 'jumps',
            self::MostDungeonRuns => 'dungeons',
            self::MostDistance => 'distance',
            self::MostTimeByTheFire => 'hours',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FastestTo60, self::FastestTo40, self::FastestTo20 => 'The client\'s played time when the character reached level '.$this->level().'. Only public characters whose level-up to '.$this->level().' Scarlet recorded are listed.',
            self::HighestWithoutDying => 'The highest level reached by public characters that Scarlet recorded from their first level, with no death and no play missing.',
            self::MostQuests => 'Quests turned in by public characters, counted from each one\'s first recorded session.',
            self::MostQuestsCompleted => 'Quests the game says public characters have completed over their whole life, as the latest read Scarlet recorded for each. It includes quests done before Scarlet was installed.',
            self::MostKills => 'Creatures killed by public characters, counted from each one\'s first recorded session.',
            self::MostJumps => 'Jumps by public characters, counted from each one\'s first recorded session.',
            self::MostDungeonRuns => 'Dungeon runs by public characters, counted from each one\'s first recorded session.',
            self::MostDistance => 'The length of the recorded path on the world map, in world widths: 1.0 is the width of the map. Teleports and loading screens are left out.',
            self::MostTimeByTheFire => 'Time spent near a campfire by public characters, counted from each one\'s first recorded session.',
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
            self::MostQuestsCompleted => 'Quests completed',
            self::MostKills => 'Kills',
            self::MostJumps => 'Jumps',
            self::MostDungeonRuns => 'Runs',
            self::MostDistance => 'World widths',
            self::MostTimeByTheFire => 'Time by the fire',
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
            self::MostTimeByTheFire => Format::hours($value),
            default => Format::number($value),
        };
    }
}
