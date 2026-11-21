<?php

namespace App\View\Design;

use App\View\Charts;
use App\View\Format;

/** The data of docs/comps/rulesets.html, leaderboards.html and search.html. */
final class ListPages
{
    /**
     * @return array<string, mixed>
     */
    public static function data(string $page): array
    {
        return match ($page) {
            'rulesets' => self::rulesets(),
            'leaderboards' => self::leaderboards(),
            default => self::search(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function rulesets(): array
    {
        $day = 86400;
        $rows = [
            ['Normal', false, 612, 19870, 5 * $day + 21 * 3600, 0.41, 0.14, 0.47],
            ['PvP', false, 288, 9412, 6 * $day + 9 * 3600, 0.58, 0.11, 0.44],
            ['Roleplay', true, 204, 6330, 7 * $day + 2 * 3600, 0.03, 0.04, 0.52],
            ['Roleplay PvP', false, 131, 5105, 11 * $day + 6 * 3600, 0.37, 0.03, 0.49],
            ['Roleplay PvP', false, 49, 1190, null, 0.05, 0.0, 0.5],
        ];
        $rows = array_map(fn (array $r): array => ['cells' => [
            ['text' => $r[0], 'href' => Gallery::url('stats'), 'tag' => $r[1] ? 'HC' : null],
            Format::number($r[2]), Format::number($r[3]), $r[4] === null ? Format::TOO_FEW : Format::duration($r[4]),
            Format::number($r[5], 2), Format::percent($r[6]), Format::percent($r[7]),
        ]], $rows);

        $curve = function (float $k): array {
            $pts = [];
            $t = 0.0;
            for ($l = 1; $l <= 60; $l++) {
                $t += (0.12 + (($l / 60) ** 2.1) * 5.2) * $k;
                $pts[] = [$l, $t];
            }

            return $pts;
        };
        $survival = [];
        for ($i = 0; $i < 60; $i++) {
            $survival[] = [$i + 1, 100 * exp(-$i / 26)];
        }

        return [
            'rows' => $rows,
            'compare' => Charts::line(
                ['x0' => 1, 'x1' => 60, 'y1' => 300, 'yt' => Charts::ticks([0, 100, 200, 300], 'h'), 'xt' => Charts::ticks([1, 10, 20, 30, 40, 50, 60])],
                [['cls' => 'c', 'pts' => $curve(1.85)], ['cls' => 'b', 'pts' => $curve(1.2)], ['cls' => 'a', 'pts' => $curve(1)]],
            ),
            'survival' => Charts::line(
                ['w' => 380, 'h' => 250, 'x0' => 1, 'x1' => 60, 'y1' => 100, 'yt' => Charts::ticks([0, 50, 100], '%'), 'xt' => Charts::ticks([1, 20, 40, 60])],
                [['cls' => 'a', 'pts' => $survival]],
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function leaderboards(): array
    {
        $entries = [
            ['Maren-Ashlow', 'Orc Warrior', [3, 19, 2], 'Normal', false], ['Tolvir-Greane', 'Troll Mage', [3, 22, 41], 'Normal', false],
            ['Ysolde-Harrowick', 'Undead Priest', [4, 1, 16], 'PvP', false], ['Bram-Coldwater', 'Tauren Druid', [4, 3, 50], 'Normal', false],
            ['Senna-Vale', 'Orc Hunter', [4, 6, 12], 'Roleplay', true], ['Dorrin-Ashby', 'Troll Shaman', [4, 9, 33], 'Normal', false],
            ['Liss-Underhill', 'Undead Rogue', [4, 11, 8], 'PvP', false], ['Corwen-Pike', 'Tauren Warrior', [4, 14, 47], 'Roleplay PvP', false],
        ];
        $rows = [];
        foreach ($entries as $i => [$name, $class, [$d, $h, $m], $ruleset, $hc]) {
            $rows[] = ['class' => $i === 4 ? 'me' : null, 'cells' => [
                (string) ($i + 1),
                ['text' => $name, 'href' => Gallery::url('character'), 'dot' => true],
                $class,
                Format::exact($d * 86400 + $h * 3600 + $m * 60),
                ['text' => $ruleset, 'tag' => $hc ? 'HC' : null],
                Format::number($hc ? 0 : 20 + $i * 7),
                (18 + $i).' Nov',
            ]];
        }

        return [
            'rows' => $rows,
            'boards' => ['Fastest to level 60', 'Fastest to level 40', 'Fastest to level 20', 'Highest level without dying', 'Most quests turned in', 'Most dungeon runs', 'Most distance travelled'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function search(): array
    {
        $zone = Gallery::url('zone');
        $creature = Gallery::url('creature');
        $character = Gallery::url('character');

        return [
            'kinds' => [['All 23', true], ['Characters 2'], ['Creatures 9'], ['Items 6'], ['Zones 2'], ['Quests 4']],
            'characters' => [
                ['href' => $character, 'title' => 'Marsha-Venn', 'meta' => 'Level 44 Troll Hunter · Normal', 'value' => Format::duration(5 * 86400 + 2 * 3600).' played'],
                ['href' => $character, 'title' => 'Tolmarsh-Greane', 'meta' => 'Level 12 Orc Warrior · Roleplay', 'value' => Format::duration(9 * 3600).' played'],
            ],
            'zones' => [
                ['href' => $zone, 'title' => 'Saltmarsh Reach', 'meta' => Format::range(18, 27).' · '.Format::number(6480).' deaths', 'value' => Format::number(3912).' hours'],
                ['href' => $zone, 'title' => 'Marshlight Fen', 'meta' => Format::range(33, 38).' · '.Format::number(1022).' deaths', 'value' => Format::number(804).' hours'],
            ],
            'creatures' => [
                ['href' => $creature, 'title' => 'Marsh Stalker', 'meta' => 'Level 22 to 24 beast · Saltmarsh Reach', 'value' => Format::number(88412).' kills · '.Format::number(2104).' deaths'],
                ['href' => $creature, 'title' => 'Saltmarsh Raider', 'meta' => 'Level 23 to 25 humanoid · Saltmarsh Reach', 'value' => Format::number(61090).' kills · '.Format::number(1118).' deaths'],
                ['href' => $creature, 'title' => 'Marshlight Wisp', 'meta' => 'Level 34 elemental · Marshlight Fen', 'value' => Format::number(12733).' kills · '.Format::number(96).' deaths'],
            ],
            'items' => [
                ['href' => Gallery::url('item'), 'title' => 'Marshwalker Greaves', 'quality' => 'q3', 'meta' => 'Item level 28 mail feet', 'value' => 'Drops from 3 creatures'],
                ['href' => Gallery::url('item'), 'title' => 'Saltmarsh Cleaver', 'quality' => 'q2', 'meta' => 'Item level 26 two-handed axe', 'value' => 'Drops from 5 creatures'],
            ],
            'quests' => [
                ['href' => Gallery::url('quest'), 'title' => 'The Marsh Takes Its Due', 'meta' => 'Level 23 · Saltmarsh Reach', 'value' => Format::number(4210).' turn-ins'],
            ],
        ];
    }
}
