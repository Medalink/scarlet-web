<?php

namespace App\View\Design\ClassPages;

use App\Support\ClassPage\Build;
use App\Support\ClassPage\Callout;
use App\Support\ClassPage\CharacterLine;
use App\Support\ClassPage\ClassPage;
use App\Support\ClassPage\Comparison;
use App\Support\ClassPage\Figure;
use App\Support\ClassPage\Hue;
use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Link;
use App\Support\ClassPage\LinkItem;
use App\Support\ClassPage\LinkPair;
use App\Support\ClassPage\Links;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Row;
use App\Support\ClassPage\Series;
use App\Support\ClassPage\Span;
use App\Support\ClassPage\Split;
use App\Support\ClassPage\SplitPart;
use App\Support\ClassPage\Stack;
use App\Support\ClassPage\StackColumn;
use App\Support\ClassPage\Standing;
use App\Support\ClassPage\TalentModule;
use App\Support\ClassPage\Titled;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\PlayerClass;

/** A Rogue page from the approved mock's invented numbers: the link chart from opener to finisher, and a stack by finisher. */
final class RoguePage
{
    private const int HOURS = 312;

    private const int LEVEL = 60;

    private const int STEALTH_HOURS = 96;

    private const int POCKETS = 2104;

    private const int LOCKS = 412;

    private const int KICKS_LANDED = 1094;

    /** Finisher: spell, name, hue. */
    private const array FINISHERS = [[2098, 'Eviscerate', '#fff468'], [5171, 'Slice and Dice', '#e0a33c'], [408, 'Kidney Shot', '#d9694a'], [1943, 'Rupture', '#7ecbd6'], [8647, 'Expose Armor', '#a8a8b8']];

    /** Opener: spell or null, name. */
    private const array OPENERS = [[1833, 'Cheap Shot'], [8676, 'Ambush'], [703, 'Garrote'], [2070, 'Sap'], [1784, 'Stealth lost, no opener'], [null, 'Not stealthed']];

    private const array MATRIX = [
        [420, 380, 610, 120, 12, 200],
        [520, 160, 70, 30, 8, 115],
        [110, 210, 35, 90, 6, 153],
        [180, 150, 20, 40, 3, 220],
        [190, 140, 35, 25, 4, 234],
        [1900, 1700, 380, 330, 33, 2007],
    ];

    public static function page(): ClassPage
    {
        $icon = Icon::spell(...);
        $item = Icon::item(...);
        $poisons = [[6947, 'Instant Poison', 3835], [2892, 'Deadly Poison', 1010], [3775, 'Crippling Poison', 520], [5237, 'Mind-numbing Poison', 275], [10918, 'Wound Poison', 0]];
        $poisonTotal = array_sum(array_column($poisons, 2));
        $opened = array_sum(array_map('array_sum', array_slice(self::MATRIX, 0, 4)));
        $fights = array_sum(array_map('array_sum', self::MATRIX));

        return new ClassPage(
            PlayerClass::Rogue,
            new CharacterLine('Name-Surname', self::LEVEL, self::HOURS, new Build('Combat', [5, 31, 15]), $icon(1784)),
            new Links('#', '#', '#', '#'),
            [
                new Callout('vials', 'Instant Poison', 'The poison applied most', Invent::percent($poisons[0][2], $poisonTotal).'% of applications'),
                new Callout('chest', 'Locks picked', self::LOCKS.' boxes, doors and chests', 'Lockpicking 300'),
                new Callout('sack', 'Pick Pocket', Unit::Count->say(self::POCKETS).' pockets picked', '61 gold lifted'),
            ],
            [
                new Figure('Hours in stealth', self::STEALTH_HOURS, of: 'of '.self::HOURS, icon: $icon(1784)),
                new Figure('Openers from stealth', $opened, icon: $icon(1833)),
                new Figure('Pockets picked', self::POCKETS, icon: $icon(921)),
                new Figure('Locks picked', self::LOCKS, icon: $icon(1804)),
                new Figure('Poisons applied', $poisonTotal, icon: $item(6947)),
                new Figure('Vanishes', 13, icon: $icon(1856)),
            ],
            self::sheet($opened, $poisons),
            self::talents(),
            self::party(),
            new Comparison('rogues', 50, 59, 1087, [
                new Standing('Fights opened from stealth', $opened / $fights * 100, 82, Unit::Percent, $icon(1833)),
                new Standing('Time stealthed, of played time', self::STEALTH_HOURS / self::HOURS * 100, 77, Unit::Percent, $icon(1784)),
                new Standing('Pockets picked a played hour', self::POCKETS / self::HOURS, 72, Unit::Decimal, $icon(921)),
                new Standing('Kicks that landed', self::KICKS_LANDED, 68, icon: $icon(1766)),
                new Standing('Vanishes a played hour', number_format(13 / self::HOURS, 2), 6, icon: $icon(1856)),
                new Standing('Lockpicking skill', 300, 94, icon: $icon(1804)),
            ]),
            Invent::feats([
                [$icon(1784), 'Unseen', 4490, [10, 400, 2000, 6000, 25000], 'casts of Stealth'],
                [$icon(921), 'Pickpocket', self::POCKETS, [10, 400, 2000, 6000, 25000], 'casts of Pick Pocket'],
                [$icon(1804), 'Locksmith', self::LOCKS, [5, 100, 500, 1500, 6000], 'casts of Pick Lock'],
                [$icon(1856), 'Vanishing Act', 13, [1, 30, 125, 400, 1500], 'casts of Vanish'],
                [$icon(1752), 'Cutthroat', 38420, [50, 2500, 12000, 40000, 200000], 'casts of Sinister Strike'],
                [$icon(1776), 'Gouger', 1120, [10, 400, 2000, 6000, 25000], 'casts of Gouge'],
                [$icon(13877), 'Blade Dancer', 5018, [5, 100, 500, 1500, 6000], 'casts of Blade Flurry, Adrenaline Rush, Evasion and Sprint'],
                [$icon(13750), 'Swashbuckler', 280, [2, 40, 150, 400, 1500], 'hours with Combat as the main tree'],
            ]),
        );
    }

    /**
     * @param  list<array{0: int, 1: string, 2: int}>  $poisons
     * @return list<Row>
     */
    private static function sheet(int $opened, array $poisons): array
    {
        $icon = Icon::spell(...);
        $item = Icon::item(...);
        $links = [];
        foreach (self::MATRIX as $i => $row) {
            foreach ($row as $j => $count) {
                $links[] = new LinkPair($i, $j, $count);
            }
        }
        $lostStealth = array_sum(self::MATRIX[4]);
        $noFinisher = array_sum(array_column(self::MATRIX, 5));
        $attacks = [[1752, 'Sinister Strike', 38420], [53, 'Backstab', 5210], [14278, 'Ghostly Strike', 2160], [1776, 'Gouge', 1120], [1966, 'Feint', 480]];
        $loot = [[2589, 'Linen Cloth', 412], [2592, 'Wool Cloth', 188], [4634, 'Iron Lockbox', 87], [1418, 'Worn Leather Belt', 77], [118, 'Minor Healing Potion', 64]];
        $escapes = [[2983, 'Sprint', 1486], [1776, 'Gouge', 1120], [5277, 'Evasion', 842], [2094, 'Blind', 168], [1856, 'Vanish', 13]];
        $own = fn (array $rows, bool $items = false): Rank => new Rank(array_values(array_map(fn (array $r): RankRow => new RankRow($r[1], $r[2], $items ? $item($r[0]) : $icon($r[0]), Tone::Own), $rows)));

        return [
            new Row([
                new Module('From the opener to the finisher', [new Link(
                    array_map(fn (array $o): LinkItem => new LinkItem($o[1], $o[0] === null ? null : $icon($o[0])), self::OPENERS),
                    [...array_map(fn (array $f): LinkItem => new LinkItem($f[1], $icon($f[0])), self::FINISHERS), new LinkItem('No finisher', null, Tone::Lost)],
                    $links, 'fights', 'Fights by how they opened, on the left, and by the first finisher used in them, on the right',
                )], Span::W8, 'Cheap Shot opened '.Unit::Count->say(array_sum(self::MATRIX[0])).' fights and led into Kidney Shot in '.self::MATRIX[0][2].' of them. '.Unit::Count->say($noFinisher).' fights ended without a finisher, and Expose Armor was the first finisher of '.array_sum(array_column(self::MATRIX, 4)).'.'),
                new Module('Stealth', [
                    new Pair([new PairRow('Hours in stealth', self::STEALTH_HOURS.' h'), new PairRow('Of played time', self::STEALTH_HOURS / self::HOURS * 100, Unit::Percent), new PairRow('Seconds stealthed before the opener', 14, small: 'median')]),
                    new Split([new SplitPart('Opened from stealth', Invent::percent($opened, $opened + $lostStealth), Tone::Own), new SplitPart('Stealth lost with no opener', 100 - Invent::percent($opened, $opened + $lostStealth), Tone::Lost)]),
                ], icon: $icon(1784)),
            ]),
            new Row([
                new Module('The finisher used, by level', [new Stack(
                    array_map(fn (array $shares, int $i): StackColumn => new StackColumn($i + 1, $shares), self::finisherShares(), array_keys(self::finisherShares())),
                    array_map(fn (array $f): Series => new Series($f[1], new Hue($f[2])), self::FINISHERS),
                    'Share of finishers used that was each finisher at each level from 1 to '.self::LEVEL,
                )], Span::W7, 'Eviscerate was the only finisher before level 10, and Kidney Shot came in at level 30.'),
                new Module('Pick Pocket', [
                    new Pair([new PairRow('Pockets picked', self::POCKETS), new PairRow('Gold lifted', '61 g'), new PairRow('Items taken', 1046)]),
                    new Titled('What came out most', [$own($loot, true)]),
                ], Span::W5, icon: $icon(921)),
            ]),
            new Row([
                new Module('Attacks used', [$own($attacks)], Span::W8, 'Sinister Strike was '.Invent::percent($attacks[0][2], array_sum(array_column($attacks, 2))).'% of these casts.'),
                new Module('Locks', [
                    new Pair([new PairRow('Locks picked', self::LOCKS), new PairRow('Lockboxes opened', 268), new PairRow('Lockpicking skill', 300)]),
                    new Rank([new RankRow('Lockboxes', 268, $item(4634), Tone::Own), new RankRow('Doors', 104, $item(13704), Tone::Own), new RankRow('Chests', 40, $item(6353), Tone::Own)]),
                ], icon: $icon(1804)),
            ]),
            new Row([
                new Module('Poisons applied', [$own($poisons, true)], note: '312 poisons were crafted. Wound Poison was never applied.'),
                new Module('Escapes', [$own($escapes)], note: '9 deaths came within 10 seconds of a Vanish.'),
                new Module('Kick', [new Pair([new PairRow('Kicks cast', 3412), new PairRow('Interrupts landed', self::KICKS_LANDED), new PairRow('Landed', Invent::percent(self::KICKS_LANDED, 3412), Unit::Percent)])], icon: $icon(1766)),
            ]),
        ];
    }

    /** @return list<list<float>> */
    private static function finisherShares(): array
    {
        return Invent::shares(23, self::LEVEL, fn (int $level): array => [
            $level < 10 ? 1 : 0.46, $level < 10 ? 0 : 0.5, $level < 30 ? 0 : 0.13, $level < 20 ? 0 : 0.1, $level < 14 ? 0 : 0.012,
        ]);
    }

    /** @return list<TalentModule> */
    private static function talents(): array
    {
        $split = fn (int $used, string $a, string $b): Split => new Split([new SplitPart($a, $used, Tone::Own), new SplitPart($b, 100 - $used, Tone::Lost)]);

        return [
            new TalentModule('Blade Flurry', 1, 1, [
                new Pair([new PairRow('Casts', 1946), new PairRow('Fights with 2 or more kills', 34, Unit::Percent), new PairRow('Most kills in one', 9)]),
                $split(34, 'Fights with 2 or more kills while up', 'Fights with fewer'),
            ], Icon::spell(13877)),
            new TalentModule('Adrenaline Rush', 1, 1, [
                new Pair([new PairRow('Casts', 744), new PairRow('Of boss fights with it', 71, Unit::Percent), new PairRow('Longest streak', '9 fights')]),
                $split(71, 'Boss fights it was cast in', 'Boss fights without'),
            ], Icon::spell(13750)),
            new TalentModule('Riposte', 1, 1, [
                new Pair([new PairRow('Casts', 1312), new PairRow('Fights with one', 29, Unit::Percent), new PairRow('Most in one fight', 6)]),
                $split(29, 'Fights it was cast in', 'Fights without'),
            ], Icon::spell(14251)),
        ];
    }

    /** @return list<Row> */
    private static function party(): array
    {
        $icon = Icon::spell(...);

        return [new Row([
            new Module('Sap', [new Pair([new PairRow('Casts', 1180), new PairRow('In dungeons', 812), new PairRow('Dungeon pulls opened by one', 38, Unit::Percent)])], icon: $icon(2070)),
            new Module('Kicks on bosses', [new Pair([new PairRow('Casts in encounters', 188), new PairRow('Landed', 121), new PairRow('Share landed', Invent::percent(121, 188), Unit::Percent)])], icon: $icon(1766)),
            new Module('Scouting', [
                new Rank([new RankRow('Distract', 612, $icon(1725), Tone::Own), new RankRow('Detect Traps', 241, $icon(2836), Tone::Own), new RankRow('Disarm Trap', 88, $icon(1842), Tone::Own)]),
                new Pair([new PairRow('Hours in stealth inside dungeons', '22 h')]),
            ]),
        ])];
    }
}
