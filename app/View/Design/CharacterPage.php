<?php

namespace App\View\Design;

use App\View\Charts;
use App\View\Format;

/** The data of docs/comps/character.html: the head, the owner's bar and the two modules the web app adds. */
final class CharacterPage
{
    /**
     * @return array<string, mixed>
     */
    public static function data(): array
    {
        $pace = self::pace();
        $r = new Rng(9);
        $m = 20.0;
        $balance = [];
        for ($i = 0; $i < 30; $i++) {
            $m += $r->next() * 4 - 0.6;
            $balance[] = [$i, $m];
        }

        return [
            'map' => Charts::map(),
            'facts' => ['Level 38 Orc Warrior', 'Horde', 'Enhanced, PvE', Format::duration(4 * 86400).' played'],
            'progress' => ['percent' => 38, 'text' => '38% to level 39 · '.Format::number(51274).' XP to go · level 40 in about '.Format::duration(6 * 3600 + 10 * 60).' played'],
            'week' => [
                ['label' => 'Played', 'value' => Format::duration(8 * 3600 + 48 * 60)], ['label' => 'Dungeons', 'value' => '1'], ['label' => 'Quests', 'value' => '26'],
                ['label' => 'Gold', 'value' => Format::moneyText(95200, true)], ['label' => 'Zones', 'value' => '4'],
            ],
            'facts_owner' => ['Economy hidden from visitors', 'Last upload 12 minutes ago, 214 sessions'],
            'pace' => $pace['spec'],
            'paceLine' => $pace['line'],
            'balance' => Charts::line(['w' => 380, 'h' => 130, 'x0' => 0, 'x1' => 29, 'y1' => 80, 'yt' => Charts::ticks([0, 40, 80], 'g'), 'xt' => []], [['cls' => 'g', 'pts' => $balance]]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function pace(): array
    {
        $curve = function (float $k, int $levels, bool $jitter): array {
            $r = new Rng(4);
            $pts = [];
            $t = 0.0;
            for ($l = 1; $l <= $levels; $l++) {
                $t += (0.12 + (($l / 60) ** 2.1) * 5.2) * $k * ($jitter ? 0.8 + $r->next() * 0.4 : 1);
                $pts[] = [$l, $t];
            }

            return $pts;
        };
        $median = $curve(1, 60, false);
        $mine = $curve(0.9, 38, true);
        $say = [];
        foreach ($mine as $i => [$level, $hours]) {
            $diff = ($median[$i][1] - $hours) * 3600;
            $say[] = Format::line('Level '.$level, ' reached in '.Format::hours($hours * 3600, false).' played, '.Format::hours(abs($diff), false).($diff >= 0 ? ' faster' : ' slower').' than the median.');
        }

        return [
            'spec' => Charts::line(
                ['x0' => 1, 'x1' => 60, 'y1' => 200, 'yt' => Charts::ticks([0, 50, 100, 150, 200], 'h'), 'xt' => Charts::ticks([1, 10, 20, 30, 40, 50, 60]), 'lead' => 1],
                [['cls' => 'a thin', 'pts' => $median, 'band' => array_map(fn (array $p): array => [$p[0], $p[1] * 0.78, $p[1] * 1.29], $median)], ['cls' => 'b', 'pts' => $mine, 'mark' => 37]],
                $say, 37, 'pace-out',
            ),
            'line' => $say[37],
        ];
    }
}
