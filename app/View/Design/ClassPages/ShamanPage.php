<?php

namespace App\View\Design\ClassPages;

use App\Support\ClassPage\Build;
use App\Support\ClassPage\Callout;
use App\Support\ClassPage\CharacterLine;
use App\Support\ClassPage\ClassPage;
use App\Support\ClassPage\Columns;
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

/** A Shaman page from the approved mock's invented numbers: four ranks side by side, a link chart and a stack by element. */
final class ShamanPage
{
    private const int HOURS = 340;

    private const int LEVEL = 60;

    private const int WOLF = 61;

    private const int MOUNTED = 47;

    private const int RECALLS = 118;

    private const int REINCARNATIONS = 37;

    private const int DEATHS = 119;

    private const int FIGHTS = 8240;

    private const int FOUR = 5310;

    private const int SOME = 2640;

    /**
     * Element: name, hue, the totems with their spell, name, drops and seconds each stood.
     *
     * @var list<array{0: string, 1: string, 2: list<array{0: int, 1: string, 2: int, 3: int}>}>
     */
    private const array ELEMENTS = [
        ['Fire', '#ef6a2d', [[3599, 'Searing', 4210, 52], [8190, 'Magma', 1140, 17], [8227, 'Flametongue', 1060, 58], [8181, 'Frost Resistance', 96, 100]]],
        ['Earth', '#b9985a', [[8075, 'Strength of Earth', 3120, 66], [5730, 'Stoneclaw', 1980, 13], [2484, 'Earthbind', 760, 38], [8071, 'Stoneskin', 410, 80], [8143, 'Tremor', 310, 60]]],
        ['Water', '#4cc3d9', [[5675, 'Mana Spring', 2840, 61], [5394, 'Healing Stream', 1310, 52], [8166, 'Poison Cleansing', 132, 90], [8184, 'Fire Resistance', 80, 95], [8170, 'Disease Cleansing', 11, 90]]],
        ['Air', '#b7c3d8', [[8512, 'Windfury', 3650, 66], [8835, 'Grace of Air', 1230, 62], [8177, 'Grounding', 870, 19], [10595, 'Nature Resistance', 120, 90]]],
    ];

    private const array ENDINGS = [[0.31, 0.42, 0.17], [0.27, 0.46, 0.19], [0.38, 0.38, 0.14], [0.41, 0.37, 0.12]];

    public static function page(): ClassPage
    {
        $icon = Icon::spell(...);
        $elements = self::elements();
        $drops = array_sum(array_column($elements, 'drops'));
        $stood = array_sum(array_column($elements, 'hours'));
        $shocks = [[8042, 'Earth Shock', 3380], [8050, 'Flame Shock', 2540], [8056, 'Frost Shock', 990]];
        $shockTotal = array_sum(array_column($shocks, 2));
        $fightDrops = Invent::round($drops * 0.93);
        $callouts = [];
        foreach ($elements as $i => $element) {
            $callouts[] = new Callout(strtolower($element['name']), $element['top'][1].' Totem', $element['name'].', dropped '.Unit::Count->say($element['top'][2]).' times', Unit::Count->say($element['topHours']).' hours standing');
            unset($i);
        }

        return new ClassPage(
            PlayerClass::Shaman,
            new CharacterLine('Name-Surname', self::LEVEL, self::HOURS, new Build('Enhancement', [20, 31, 0]), $icon(2645)),
            new Links('#', '#', '#', '#'),
            $callouts,
            [
                new Figure('Totems dropped', $drops, icon: $icon(437009)),
                new Figure('Hours a totem stood', $stood, icon: $icon(16190)),
                new Figure('Shocks cast', $shockTotal, icon: $icon(8042)),
                new Figure('Hours as a Ghost Wolf', self::WOLF, of: 'of '.self::HOURS, icon: $icon(2645)),
                new Figure('Reincarnations', self::REINCARNATIONS, icon: $icon(20608)),
                new Figure('Astral Recalls', self::RECALLS, icon: $icon(556)),
            ],
            self::sheet($elements, $drops, $shocks),
            self::talents(),
            self::party(),
            new Comparison('shamans', 50, 59, 1198, [
                new Standing('Totems dropped a fight', $fightDrops / self::FIGHTS, 72, Unit::Decimal, $icon(437009)),
                new Standing('Fights with all four down', self::FOUR / self::FIGHTS * 100, 81, Unit::Percent, $icon(8075)),
                new Standing('Shocks a fight', $shockTotal / self::FIGHTS, 58, Unit::Decimal, $icon(8042)),
                new Standing('Hours as a Ghost Wolf of played time', self::WOLF / self::HOURS * 100, 77, Unit::Percent, $icon(2645)),
                new Standing('Reincarnations a hundred deaths', Invent::percent(self::REINCARNATIONS, self::DEATHS), 69, icon: $icon(20608)),
                new Standing('Astral Recalls', self::RECALLS, 63, icon: $icon(556)),
            ]),
            Invent::feats([
                [$icon(3599), 'Totemic', 23329, [25, 1500, 7500, 25000, 100000], 'totems dropped'],
                [$icon(2645), 'Pack Runner', 1840, [10, 400, 2000, 6000, 25000], 'casts of Ghost Wolf'],
                [$icon(8042), 'Shock Therapy', 6910, [25, 1500, 7500, 25000, 100000], 'shocks cast'],
                [$icon(20608), 'Twice Born', self::REINCARNATIONS, [1, 10, 40, 120, 400], 'Reincarnations'],
                [$icon(17364), 'Stormstriker', 3104, [25, 1500, 7500, 25000, 100000], 'casts of Stormstrike'],
                [$icon(8232), 'Weapon Imbuer', 3760, [10, 400, 2000, 6000, 25000], 'weapon imbues cast'],
                [$icon(324), 'Charged', 2260, [10, 400, 2000, 6000, 25000], 'casts of Lightning Shield'],
                [$icon(16256), 'Spiritwalker', 300, [2, 40, 150, 400, 1500], 'hours with Enhancement as the main tree'],
            ]),
        );
    }

    /** @return list<array{name: string, hue: string, totems: list<array{0: int, 1: string, 2: int, 3: int}>, drops: int, hours: int, top: array{0: int, 1: string, 2: int, 3: int}, topHours: int}> */
    private static function elements(): array
    {
        return array_values(array_map(fn (array $e): array => [
            'name' => $e[0], 'hue' => $e[1], 'totems' => $e[2],
            'drops' => array_sum(array_column($e[2], 2)),
            'hours' => Invent::round(array_sum(array_map(fn (array $t): float => $t[2] * $t[3] / 3600, $e[2]))),
            'top' => $e[2][0], 'topHours' => Invent::round($e[2][0][2] * $e[2][0][3] / 3600),
        ], self::ELEMENTS));
    }

    /**
     * @param  list<array{name: string, hue: string, totems: list<array{0: int, 1: string, 2: int, 3: int}>, drops: int, hours: int, top: array{0: int, 1: string, 2: int, 3: int}, topHours: int}>  $elements
     * @param  list<array{0: int, 1: string, 2: int}>  $shocks
     * @return list<Row>
     */
    private static function sheet(array $elements, int $drops, array $shocks): array
    {
        $icon = Icon::spell(...);
        $maxDrop = max([0, ...array_map(fn (array $e): int => $e['top'][2], $elements)]);
        $links = [];
        $ended = [0, 0, 0, 0];
        foreach ($elements as $i => $element) {
            [$a, $b, $c] = array_map(fn (float $share): int => Invent::round($element['drops'] * $share), self::ENDINGS[$i]);
            foreach ([$a, $b, $c, $element['drops'] - $a - $b - $c] as $j => $count) {
                $links[] = new LinkPair($i, $j, $count);
                $ended[$j] += $count;
            }
        }
        $none = self::FIGHTS - self::FOUR - self::SOME;
        $columns = array_map(fn (array $e): Titled => new Titled($e['name'], [
            new Rank(array_map(fn (array $t): RankRow => new RankRow($t[1], $t[2], $icon($t[0]), new Hue($e['hue'])), $e['totems']), of: $maxDrop),
            new Pair([new PairRow('Dropped', $e['drops']), new PairRow('Stood', $e['hours'].' h')]),
        ], $icon($e['top'][0])), $elements);

        return [
            new Row([new Module('Totems, by element', [new Columns($columns)], Span::W12, 'Searing Totem went down '.Unit::Count->say($maxDrop).' times, more than any other totem. Disease Cleansing Totem went down 11 times.')]),
            new Row([
                new Module('How a totem ended', [new Link(
                    array_map(fn (array $e): LinkItem => new LinkItem($e['top'][1].' Totem', $icon($e['top'][0])), $elements),
                    [new LinkItem('Full time'), new LinkItem('Replaced'), new LinkItem('Gone early', null, Tone::Lost), new LinkItem('Still standing', null, Tone::Held)],
                    $links, 'totems', 'The element of each totem dropped, on the left, and how it ended, on the right',
                )], Span::W7, Unit::Count->say($ended[2]).' of '.Unit::Count->say($drops).' totems were gone early: destroyed, recalled or out of range.'),
                new Module('Totems in a fight', [
                    new Pair([new PairRow('Fights with all four down', self::FOUR), new PairRow('Median totems dropped a fight', 2), new PairRow('Most dropped in one fight', 31)]),
                    new Split([
                        new SplitPart('All four down, '.Invent::percent(self::FOUR, self::FIGHTS).'% of '.Unit::Count->say(self::FIGHTS).' fights', self::FOUR, Tone::Own),
                        new SplitPart('Some down, '.Invent::percent(self::SOME, self::FIGHTS).'%', self::SOME, new Hue('#7f93b5')),
                        new SplitPart('None, '.Invent::percent($none, self::FIGHTS).'%', $none, Tone::Lost),
                    ]),
                ], Span::W5),
            ]),
            new Row([
                new Module('Elements with a totem down, by level', [new Stack(
                    array_map(fn (array $shares, int $i): StackColumn => new StackColumn($i + 1, $shares), self::elementShares(), array_keys(self::elementShares())),
                    array_map(fn (array $e): Series => new Series($e['name'], new Hue($e['hue'])), $elements),
                    'Share of totem time each element had a totem down at each level from 1 to '.self::LEVEL,
                )], Span::W8, 'Earth was the only element down from level 4 to 9. From level 30 all four held nearly equal shares of totem time.'),
                new Module('Shocks', [
                    new Rank(array_map(fn (array $s): RankRow => new RankRow($s[1], $s[2], $icon($s[0]), Tone::Own), $shocks)),
                    new Pair([new PairRow('Interrupts landed', 412), new PairRow('Of Earth Shocks', Invent::percent(412, $shocks[0][2]), Unit::Percent)]),
                ]),
            ]),
            new Row([
                new Module('Weapon imbues', [new Rank(array_map(fn (array $s): RankRow => new RankRow($s[1], $s[2], $icon($s[0]), Tone::Own), [[8232, 'Windfury Weapon', 1980], [8033, 'Frostbrand Weapon', 760], [8024, 'Flametongue Weapon', 640], [8017, 'Rockbiter Weapon', 380]]))]),
                new Module('Back from the dead', [
                    new Pair([new PairRow('Reincarnations', self::REINCARNATIONS), new PairRow('Ankhs bought', 60), new PairRow('Deaths walked off', Invent::percent(self::REINCARNATIONS, self::DEATHS), Unit::Percent)]),
                    new Split([
                        new SplitPart('Walked off, '.self::REINCARNATIONS.' of '.self::DEATHS.' deaths', Invent::percent(self::REINCARNATIONS, self::DEATHS), Tone::Own),
                        new SplitPart('Released', 100 - Invent::percent(self::REINCARNATIONS, self::DEATHS), Tone::Lost),
                    ]),
                ], icon: $icon(20608)),
                new Module('Ghost Wolf and Astral Recall', [
                    new Pair([new PairRow('Hours as a wolf', self::WOLF), new PairRow('Of travel time', Invent::percent(self::WOLF, self::WOLF + self::MOUNTED), Unit::Percent), new PairRow('Astral Recalls', self::RECALLS)]),
                    new Titled('Hours travelling', [new Rank([
                        new RankRow('Ghost Wolf', self::WOLF, $icon(2645), Tone::Own), new RankRow('Mounted', self::MOUNTED, Icon::item(1132), Tone::Lost),
                    ], Unit::Hours, of: self::WOLF)]),
                ], icon: $icon(2645)),
            ]),
        ];
    }

    /** @return list<list<float>> */
    private static function elementShares(): array
    {
        return Invent::shares(23, self::LEVEL, fn (int $level): array => [
            $level < 10 ? 0 : 0.35 + min(0.2, ($level - 10) * 0.006),
            $level < 4 ? 0 : 1 - min(0.4, ($level - 4) * 0.009),
            $level < 20 ? 0 : 0.25 + min(0.35, ($level - 20) * 0.009),
            $level < 30 ? 0 : 0.3 + min(0.4, ($level - 30) * 0.013),
        ]);
    }

    /** @return list<TalentModule> */
    private static function talents(): array
    {
        $split = fn (int $used, string $a, string $b): Split => new Split([new SplitPart($a, $used, Tone::Own), new SplitPart($b, 100 - $used, Tone::Lost)]);

        return [
            new TalentModule('Stormstrike', 1, 1, [
                new Pair([new PairRow('Casts', 3104), new PairRow('Of your damage', 29, Unit::Percent), new PairRow('Fights with it', 81, Unit::Percent)]),
                $split(81, 'Fights it was cast in', 'Fights without'),
            ], Icon::spell(17364)),
            new TalentModule('Elemental Focus', 1, 1, [
                new Pair([new PairRow('Clearcasting procs', 2486), new PairRow('Used', 77, Unit::Percent), new PairRow('Ran out', 23, Unit::Percent)]),
                $split(77, 'Spent on a spell', 'Ran out'),
            ], Icon::spell(16164)),
            new TalentModule('Flurry', 5, 5, [
                new Pair([new PairRow('Times it came up', 9840), new PairRow('Of fight time it was up', 41, Unit::Percent), new PairRow('Fights with it', 88, Unit::Percent)]),
                $split(41, 'Fight time with Flurry up', 'Fight time without'),
            ], Icon::spell(16256)),
        ];
    }

    /** @return list<Row> */
    private static function party(): array
    {
        $icon = Icon::spell(...);
        $own = fn (array $rows, Unit $unit = Unit::Count): Rank => new Rank(array_values(array_map(fn (array $r): RankRow => new RankRow($r[1], $r[2], $icon($r[0]), Tone::Own), $rows)), $unit);

        return [new Row([
            new Module('Totems for the group', [$own([[8512, 'Windfury', 1730], [8075, 'Strength of Earth', 1212], [5675, 'Mana Spring', 1180], [8835, 'Grace of Air', 521], [8143, 'Tremor', 38]], Unit::Hours)]),
            new Module('Heals and raises', [$own([[331, 'Healing Wave', 1840], [1064, 'Chain Heal', 920], [8004, 'Lesser Healing Wave', 610], [2008, 'Ancestral Spirit', 142]])]),
            new Module('Purges and cures', [
                $own([[370, 'Purge', 1120], [526, 'Cure Poison', 480], [2870, 'Cure Disease', 210]]),
                new Pair([new PairRow('Casts', 1810), new PairRow('Dispels landed', 1311), new PairRow('Landed', Invent::percent(1311, 1810), Unit::Percent)]),
            ]),
        ])];
    }
}
