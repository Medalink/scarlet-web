<?php

namespace App\Support;

/** The named series of a ruleset in the public API: `GET /rulesets/{slug}/series/{series}`. */
enum Series: string
{
    case Levels = 'levels';
    case Xp = 'xp';
    case Deaths = 'deaths';
    case Dungeons = 'dungeons';
    case Professions = 'professions';
    case Gold = 'gold';
    case Activity = 'activity';

    public function summary(): string
    {
        return match ($this) {
            self::Levels => 'Characters, median time to reach each level, and the middle half',
            self::Xp => 'Experience by level and source',
            self::Deaths => 'Deaths by level, top killers and the zones with most deaths',
            self::Dungeons => 'Runs, time and bosses by dungeon',
            self::Professions => 'Characters and skill level by skill',
            self::Gold => 'Mean copper earned and spent by level',
            self::Activity => 'Play time by weekday, by level and by zone',
        };
    }
}
