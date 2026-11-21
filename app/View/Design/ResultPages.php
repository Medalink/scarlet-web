<?php

namespace App\View\Design;

use App\View\Charts;
use App\View\Format;

/** The data of docs/comps/creature.html, item.html, zone.html and quest.html. */
final class ResultPages
{
    /**
     * @return array<string, mixed>
     */
    public static function data(string $page): array
    {
        return match ($page) {
            'creature' => self::creature(),
            'item' => self::item(),
            'zone' => self::zone(),
            default => self::quest(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function creature(): array
    {
        $item = Gallery::url('item');
        $drops = [['Stalker Hide', null, '5.2rem', 0.412, 38904, 16028], ['Cracked Tooth', null, '3.6rem', 0.285, 38904, 11088], ['Saltmarsh Cleaver', 'q2', '.2rem', 0.014, 38904, 545], ['Marshwalker Greaves', 'q3', '2px', 0.0021, 38904, 82]];
        $bars = [];
        for ($i = 0; $i < 19; $i++) {
            $bars[] = [15 + $i, 380 * exp(-((($i - 7) / 3.4) ** 2)) + 6];
        }

        return [
            'figures' => [
                ['label' => 'Kills', 'value' => Format::number(88412)], ['label' => 'Characters killed', 'value' => Format::number(2104)], ['label' => 'Deaths per 1,000 kills', 'value' => Format::number(23.8, 1)],
                ['label' => 'Median killer level', 'value' => '23'], ['label' => 'XP a kill at level 23', 'value' => '214'], ['label' => 'Corpses looted', 'value' => Format::number(61377)],
            ],
            'drops' => array_map(fn (array $r): array => ['cells' => [
                ['text' => $r[0], 'href' => $item, 'class' => $r[1]],
                ['text' => Format::percent($r[3], $r[3] < 0.01 ? 2 : 1), 'bar' => $r[2]], Format::number($r[4]), Format::number($r[5]),
            ]], $drops),
            'map' => Heat::map(3, [[3600, 4800, 800, 260], [5300, 4300, 500, 120]]),
            'deaths' => Charts::bars(['x0' => 14, 'x1' => 34, 'y1' => 400, 'yt' => Charts::ticks([0, 200, 400]), 'xt' => Charts::ticks([15, 20, 25, 30])], $bars),
            'how' => [['label' => 'Melee', 'value' => '71% of killing blows', 'share' => 71], ['label' => 'Rending Bite', 'value' => '24%', 'share' => 24], ['label' => 'Thrash', 'value' => '5%', 'share' => 5]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function item(): array
    {
        $creature = Gallery::url('creature');
        $sources = [['Hollow Warden', 'Solo', '3.4rem', 0.0034, 22110, 75], ['Marsh Stalker', 'Solo', '2.1rem', 0.0021, 38904, 82], ['Marsh Stalker', 'Group loot, uncommon and better', '2.3rem', 0.0023, 19640, 45], ['Saltmarsh Raider', 'Solo', '.5rem', 0.0005, 24018, 12]];

        return [
            'figures' => [
                ['label' => 'Dropped', 'value' => '214'], ['label' => 'Sources', 'value' => '3'], ['label' => 'Best drop rate', 'value' => Format::percent(0.0034, 2)],
                ['label' => 'Won by need', 'value' => Format::percent(0.61)], ['label' => 'Median looter level', 'value' => '24'], ['label' => 'First recorded', 'value' => '15 Nov'],
            ],
            'sources' => array_map(fn (array $r): array => ['cells' => [
                ['text' => $r[0], 'href' => $creature], $r[1], ['text' => Format::percent($r[3], 2), 'bar' => $r[2]], Format::number($r[4]), Format::number($r[5]),
            ]], $sources),
            'rolls' => [['label' => 'Need', 'value' => '61% of wins', 'share' => 61], ['label' => 'Greed', 'value' => '33%', 'share' => 33], ['label' => 'Everyone passed', 'value' => '6%', 'share' => 6]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function zone(): array
    {
        $creature = Gallery::url('creature');
        $quest = Gallery::url('quest');
        $quests = [['The Marsh Takes Its Due', 4210, 23, 2450, 0.03], ['Reeds for the Weaver', 3980, 20, 1700, 0.01], ['What the Tide Left', 2644, 25, 2900, 0.11]];

        return [
            'map' => Heat::map(5, [[3600, 4800, 900, 620], [5600, 4200, 700, 420], [7200, 3300, 650, 340], [4700, 2600, 500, 200], [6400, 5300, 520, 180]], [[3900, 4500], [5200, 4400], [5750, 3900], [7100, 3000], [6900, 3600], [4500, 2700], [3300, 5100], [6100, 5200]]),
            'facts' => [Format::range(18, 27), Format::number(3912).' hours played here', Format::number(6480).' deaths'],
            'figures' => [
                ['label' => 'Characters', 'value' => '811'], ['label' => 'Median stay', 'value' => Format::duration(4 * 3600 + 50 * 60)], ['label' => 'Arrive at level', 'value' => '19'],
                ['label' => 'Leave at level', 'value' => '26'], ['label' => 'Deaths an hour', 'value' => Format::number(1.66, 2)], ['label' => 'Quests', 'value' => '62'],
            ],
            'killed' => array_map(fn (array $r, float $s): array => ['label' => $r[0], 'value' => Format::number($r[1]), 'share' => $s, 'href' => $creature], [['Marsh Stalker', 88412], ['Saltmarsh Raider', 61090], ['Reed Snapper', 40255]], Gallery::shares([88412, 61090, 40255])),
            'deadliest' => [
                ['label' => 'Marsh Stalker', 'value' => '2,104 deaths', 'share' => 100, 'href' => $creature], ['label' => 'Saltmarsh Raider', 'value' => '1,118', 'share' => 53, 'href' => $creature], ['label' => 'Drowning', 'value' => '402', 'share' => 19],
            ],
            'xp' => [['label' => 'Quests', 'value' => '49%', 'share' => 49, 'class' => 'c-quest'], ['label' => 'Kills', 'value' => '44%', 'share' => 44], ['label' => 'Exploring', 'value' => '7%', 'share' => 7, 'class' => 'c-explore']],
            'quests' => array_map(fn (array $r): array => ['cells' => [['text' => $r[0], 'href' => $quest], Format::number($r[1]), (string) $r[2], Format::number($r[3]), Format::percent($r[4])]], $quests),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function quest(): array
    {
        $bars = [];
        for ($i = 0; $i < 14; $i++) {
            $bars[] = [18 + $i, 1150 * exp(-((($i - 5) / 2.3) ** 2)) + 10];
        }

        return [
            'figures' => [
                ['label' => 'Turn-ins', 'value' => Format::number(4210)], ['label' => 'Accepted', 'value' => Format::number(4366)], ['label' => 'Abandoned', 'value' => Format::percent(0.03)],
                ['label' => 'Median level', 'value' => '23'], ['label' => 'Median time to finish', 'value' => Format::duration(26 * 60)], ['label' => 'Deaths while on it', 'value' => Format::number(0.4, 1)],
            ],
            'levels' => Charts::bars(['x0' => 17, 'x1' => 32, 'y1' => 1200, 'yt' => Charts::ticks([0, 600, 1200]), 'xt' => Charts::ticks([18, 22, 26, 30])], $bars, 't'),
            'rewards' => [
                ['label' => 'Reedbound Bracers', 'value' => '58%', 'share' => 58, 'href' => Gallery::url('item'), 'quality' => 'q2'],
                ['label' => 'Tidewatcher Cord', 'value' => '42%', 'share' => 42, 'href' => Gallery::url('item'), 'quality' => 'q2'],
            ],
        ];
    }
}
