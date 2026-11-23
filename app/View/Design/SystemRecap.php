<?php

namespace App\View\Design;

/** An invented SessionRecap for /design/system: 2 h 14 min over three zones, two levels, one death, one feat. */
final class SystemRecap
{
    /**
     * @return array<string, mixed>
     */
    public static function recap(): array
    {
        $start = 1_763_920_800;
        $minutes = 134;
        $buckets = (int) ceil($minutes / 5);
        $xp = [];
        $combat = [];
        for ($i = 0; $i < $buckets; $i++) {
            $busy = $i > 3 && ! in_array($i, [12, 13, 20], true);
            $xp[] = $busy ? 900 + (($i * 37) % 11) * 260 : 120;
            $combat[] = $busy ? 150 + (($i * 53) % 9) * 15 : 20;
        }

        return [
            'v' => 1,
            'character' => 'Sable',
            'class' => 'WARLOCK',
            'start' => $start,
            'end' => $start + $minutes * 60,
            'played' => $minutes * 60 - 420,
            'level' => [31, 33],
            'zone' => 'Stranglethorn Vale',
            'title' => ['rule' => 'level', 'text' => 'Two levels in Stranglethorn Vale'],
            'xp' => ['kills' => 41200, 'dungeons' => 0, 'quests' => 9400, 'explore' => 1800, 'other' => 0],
            'runs' => 0,
            'counters' => ['kills' => 212, 'quests' => 9, 'deaths' => 1, 'foot' => 5300, 'mount' => 4200, 'levels' => 2],
            'buckets' => ['secs' => 300, 'xp' => $xp, 'combat' => $combat],
            'zones' => [
                ['name' => 'Duskwood', 'at' => 0, 'secs' => 1380],
                ['name' => 'Stranglethorn Vale', 'at' => 1380, 'secs' => 5400],
                ['name' => 'Booty Bay', 'at' => 6780, 'secs' => 1260],
            ],
            'levels' => [['level' => 32, 'at' => 3000], ['level' => 33, 'at' => 5700]],
            'moments' => [
                ['at' => 2400, 'kind' => 'discovery', 'text' => 'Discovered Nesingwary\'s Expedition'],
                ['at' => 4300, 'kind' => 'death', 'text' => 'Died to a Bloodscalp Warrior'],
                ['at' => 5200, 'kind' => 'feat', 'text' => 'Rampage reached Rare', 'feat' => 'combat.kills', 'tier' => 2],
                ['at' => 6200, 'kind' => 'boss', 'text' => 'Defeated Bhag\'thera'],
            ],
            'route' => ['sheet' => ['aspect' => 1.4, 'points' => self::path(), 'breaks' => [], 'air' => []]],
        ];
    }

    /**
     * @return list<int>
     */
    private static function path(): array
    {
        $points = [];
        for ($i = 0; $i < 60; $i++) {
            $t = $i / 59;
            $points[] = (int) round(80 + $t * 840 + sin($t * 9) * 40);
            $points[] = (int) round(500 + sin($t * 5.5) * 330 * (1 - $t * 0.3));
        }

        return $points;
    }
}
