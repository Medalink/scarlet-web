<?php

namespace App\Support;

/** The title of a session, by the first rule that applies (docs/DESIGN.md, "The title"). The same rules run in the addon and the app. */
final class SessionTitle
{
    public const int QUESTS = 5;

    public const int KILLS = 50;

    public const int GATHERS = 20;

    public const float TRAVEL_MILES = 3.0;

    public const int TRAVEL_ZONES = 3;

    private const array GATHER_WORDS = ['herb' => ['herb', 'herbs'], 'ore' => ['ore', 'ore'], 'fish' => ['fish', 'fish'], 'skin' => ['skin', 'skins']];

    /**
     * @param  array{0: int, 1: int}  $level  the level at the start and at the end
     * @param  string  $zone  the zone with the most played time, empty when none was entered
     * @param  array{name: string, runs: int}|null  $dungeon  set when the zone with the most time is a dungeon or a raid
     * @param  array<string, int>  $gathered  gathers and catches by kind: herb, ore, fish, skin
     * @return array{rule: string, text: string}
     */
    public static function make(array $level, string $zone, ?array $dungeon, int $quests, int $kills, array $gathered, float $miles, int $zones, int $played): array
    {
        $zone = $zone === '' ? 'Azeroth' : $zone;
        $gathers = array_sum($gathered);

        return match (true) {
            $level[0] > 0 && $level[1] > $level[0] => ['rule' => 'level', 'text' => 'Level '.$level[0].' to '.$level[1].' in '.$zone],
            $dungeon !== null => ['rule' => 'dungeon', 'text' => $dungeon['runs'] <= 1 ? 'A run of '.$dungeon['name'] : $dungeon['runs'].' runs of '.$dungeon['name']],
            $quests >= self::QUESTS => ['rule' => 'quests', 'text' => $quests.' quests in '.$zone],
            $kills >= self::KILLS => ['rule' => 'kills', 'text' => $kills.' kills in '.$zone],
            $gathers >= self::GATHERS => ['rule' => 'gather', 'text' => self::gathered($gathered).' in '.$zone],
            $miles >= self::TRAVEL_MILES && $zones >= self::TRAVEL_ZONES => ['rule' => 'travel', 'text' => number_format($miles, 1).' miles across '.$zones.' zones'],
            default => ['rule' => 'time', 'text' => self::duration($played).' in '.$zone],
        };
    }

    public static function duration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds, 60) % 60;

        return match (true) {
            $hours > 0 => $hours.'h '.$minutes.'m',
            $minutes > 0 => $minutes.'m',
            default => $seconds.'s',
        };
    }

    /** @param  array<string, int>  $gathered */
    private static function gathered(array $gathered): string
    {
        $gathered = array_filter($gathered, fn (int $n, string $kind): bool => $n > 0 && isset(self::GATHER_WORDS[$kind]), ARRAY_FILTER_USE_BOTH);
        arsort($gathered);
        $parts = [];
        foreach (array_slice($gathered, 0, 2, true) as $kind => $n) {
            $parts[] = $n.' '.self::GATHER_WORDS[$kind][$n === 1 ? 0 : 1];
        }

        return implode(' and ', $parts);
    }
}
