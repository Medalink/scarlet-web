<?php

namespace App\View\Design\ClassPages;

use App\Support\ClassPage\Bag;
use App\Support\ClassPage\BagItem;
use App\Support\ClassPage\Build;
use App\Support\ClassPage\Callout;
use App\Support\ClassPage\CharacterLine;
use App\Support\ClassPage\ClassPage;
use App\Support\ClassPage\Comparison;
use App\Support\ClassPage\Figure;
use App\Support\ClassPage\Flow;
use App\Support\ClassPage\FlowItem;
use App\Support\ClassPage\Hue;
use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Links;
use App\Support\ClassPage\Mirror;
use App\Support\ClassPage\MirrorPoint;
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
use App\View\Design\Rng;

/** The Warlock page of docs/comps/class.html, from the comp's own invented numbers. */
final class WarlockPage
{
    private const int HOURS = 212;

    private const array RANKS = [[6, 30], [16, 75], [26, 140], [36, 220], [46, 310]];

    /**
     * Demon: spell, name, pet, hue.
     *
     * @var list<array{0: int, 1: string, 2: string, 3: string}>
     */
    private const array DEMONS = [[688, 'Imp', 'Piptik', '#d9a336'], [697, 'Voidwalker', 'Zharmok', 'var(--class)'], [712, 'Succubus', 'Nethira', 'var(--soft)'], [691, 'Felhunter', 'Shaadrun', '#2fb3b3']];

    private const array KEYS = ['imp', 'voidwalker', 'succubus', 'felhunter'];

    public static function page(): ClassPage
    {
        $taps = self::taps();
        $tapCount = array_sum(array_column($taps, 'taps'));
        $health = array_sum(array_column($taps, 'health'));
        $demons = self::demons();
        $hours = array_map(fn (int $i): float => array_sum(array_column($demons, $i)) / count($demons) * self::HOURS, range(0, 4));
        $with = self::HOURS - $hours[4];
        $topDemon = 0;
        foreach (array_slice($hours, 0, 4, true) as $i => $demonHours) {
            $topDemon = $demonHours > $hours[$topDemon] ? $i : $topDemon;
        }
        $shards = 4314 + 498;
        $icon = Icon::spell(...);

        return new ClassPage(
            PlayerClass::Warlock,
            new CharacterLine('Name-Surname', 52, self::HOURS, new Build('Affliction', [30, 0, 12]), $icon(1454)),
            new Links('#', '#', '#', '#'),
            self::callouts($hours, $with),
            [
                new Figure('Soul shards made', $shards, icon: Icon::item(6265)),
                new Figure('Life Taps', $tapCount, icon: $icon(1454)),
                new Figure('Hours with a demon', $with, of: 'of '.self::HOURS, icon: $icon(self::DEMONS[$topDemon][0])),
                new Figure('Healthstones made', 312, icon: Icon::item(5512)),
                new Figure('Soulstones made', 268, icon: Icon::item(5232)),
                new Figure('Players summoned', 186, icon: $icon(698)),
            ],
            self::sheet($shards, $taps, $tapCount, $health, $demons, $hours),
            self::talents(),
            self::party(),
            new Comparison('warlocks', 50, 59, 1204, [
                new Standing('Soul shards a played hour', $shards / self::HOURS, 71, Unit::Decimal, Icon::item(6265)),
                new Standing('Life Taps an hour in combat', 142, 88, icon: $icon(1454)),
                new Standing('Time with a demon out', $with / self::HOURS * 100, 34, Unit::Percent, $icon(self::DEMONS[$topDemon][0])),
                new Standing('Time spent drinking', 1.1, 79, Unit::PercentTenth, $icon(430)),
                new Standing('Healthstones handed out', 41, 62, icon: Icon::item(5512)),
                new Standing('Shards destroyed', 18, 22, Unit::Percent, Icon::item(6265)),
            ]),
            Invent::feats([
                [$icon(1120), 'Soul Harvester', 5640, [10, 400, 2000, 6000, 25000], 'casts of Drain Soul'],
                [$icon(1454), 'Life Tapper', $tapCount, [25, 1500, 7500, 25000, 100000], 'casts of Life Tap'],
                [$icon(5782), 'Fearmonger', 2607, [10, 400, 2000, 6000, 25000], 'casts of Fear, Howl of Terror and Death Coil'],
                [$icon(698), 'Summoner', 186, [1, 30, 125, 400, 1500], 'casts of Ritual of Summoning'],
                [$icon(172), 'Corruptor', 9480, [25, 1500, 7500, 25000, 100000], 'casts of Corruption'],
                [$icon(980), 'Agonizer', 6120, [25, 1500, 7500, 25000, 100000], 'casts of Bane of Agony'],
                [$icon(689), 'Leech', 2940, [10, 400, 2000, 6000, 25000], 'casts of Drain Life and Siphon Life'],
                [$icon(18265), 'Hexweaver', 188, [2, 40, 150, 400, 1500], 'hours with Affliction as the main tree'],
            ]),
        );
    }

    /** @return list<array{level: int, taps: int, health: int, mana: int}> */
    private static function taps(): array
    {
        $rng = new Rng(11);
        $rows = [];
        for ($level = 6; $level <= 52; $level++) {
            $base = 30;
            foreach (self::RANKS as [$at, $amount]) {
                $base = $at <= $level ? $amount : $base;
            }
            $count = Invent::round((70 + $level * 5.4) * (0.72 + $rng->next() * 0.6));
            $health = $count * Invent::round($base * (1 + $level * 0.012));
            $rows[] = ['level' => $level, 'taps' => $count, 'health' => $health, 'mana' => Invent::round($health * ($level >= 14 ? 1.2 : 1))];
        }

        return $rows;
    }

    /** @return list<list<float>> each level's share of demon time: the Imp, Voidwalker, Succubus, Felhunter and no demon */
    private static function demons(): array
    {
        return Invent::shares(5, 52, fn (int $level): array => [
            $level < 10 ? 1 : 0.5 - min(0.32, ($level - 10) * 0.02),
            $level < 10 ? 0 : ($level < 20 ? 1.1 : 0.95 - min(0.4, ($level - 20) * 0.014)),
            $level < 20 ? 0 : 0.22 + ($level > 34 ? 0.2 : 0),
            $level < 30 ? 0 : 0.16 + ($level > 44 ? 0.16 : 0),
            0.35 + ($level < 4 ? 0.5 : 0),
        ], 0.75, 0.5);
    }

    /**
     * @param  list<float>  $hours
     * @return list<Callout>
     */
    private static function callouts(array $hours, float $with): array
    {
        $callout = fn (int $i, bool $left = false): Callout => new Callout(
            self::KEYS[$i], self::DEMONS[$i][2], self::DEMONS[$i][1].', out '.Unit::Count->say($hours[$i]).' hours', Invent::percent($hours[$i], $with).'% of demon time', $left,
        );

        return [$callout(1), $callout(2), $callout(0, true), $callout(3)];
    }

    /**
     * @param  list<array{level: int, taps: int, health: int, mana: int}>  $taps
     * @param  list<list<float>>  $demons
     * @param  list<float>  $hours
     * @return list<Row>
     */
    private static function sheet(int $shards, array $taps, int $tapCount, int $health, array $demons, array $hours): array
    {
        $icon = Icon::spell(...);
        $peak = $taps[0];
        foreach ($taps as $row) {
            $peak = $row['mana'] > $peak['mana'] ? $row : $peak;
        }
        $max = max([0.0, ...$hours]);
        $demonRows = [];
        foreach (self::DEMONS as $i => [$spell, $name, $pet, $hue]) {
            $demonRows[] = new RankRow($name, $hours[$i], $icon($spell), new Hue($hue), $pet);
        }
        $demonRows[] = new RankRow('No demon out', $hours[4], tone: new Hue('#5d5d68'));
        $curses = [[980, 'Bane of Agony', 6120], [11723, 'Curse of the Elements', 1380], [704, 'Curse of Recklessness', 890], [1714, 'Curse of Tongues', 610], [702, 'Curse of Weakness', 480], [18223, 'Curse of Exhaustion', 300], [603, 'Bane of Doom', 90]];
        $damage = [[172, 'Corruption', 24], [686, 'Shadow Bolt', 22], [980, 'Bane of Agony', 19], [18265, 'Siphon Life', 11], [689, 'Drain Life', 9], [17877, 'Shadowburn', 8]];

        return [
            new Row([
                new Module('Where soul shards came from and what they bought', [new Flow(
                    [new FlowItem('Drain Soul', 4314, $icon(1120)), new FlowItem('Shadowburn', 498, $icon(17877))],
                    [
                        new FlowItem('Summoning a demon', 1480, $icon(697)), new FlowItem('Shadowburn', 1190, $icon(17877)),
                        new FlowItem('Destroyed to make room', 870, Icon::item(6265), Tone::Lost), new FlowItem('Soul Fire', 402, $icon(6353)),
                        new FlowItem('Healthstones', 312, $icon(6201)), new FlowItem('Soulstones', 268, $icon(693)),
                        new FlowItem('Ritual of Summoning', 186, $icon(698)), new FlowItem('Other spells', 82),
                        new FlowItem('In the bags now', 22, Icon::item(6265), Tone::Held),
                    ],
                    'shards', 'Soul shards by the spell that made them, on the left, and by what they were spent on, on the right',
                )], Span::W8, '870 shards were destroyed to make room in the bags, more than went into Healthstones and Soulstones together.'),
                new Module('Shards in the bags', [
                    new Bag(32, [new BagItem(Icon::item(6265), 22)], 'soul shard'),
                    new Pair([new PairRow('Most held at once', 32), new PairRow('Held at a typical pull', 9), new PairRow('Pulls with none', 4, Unit::Percent)]),
                ]),
            ]),
            new Row([
                new Module('Health traded for mana, by level', [new Mirror(
                    array_map(fn (array $row): MirrorPoint => new MirrorPoint($row['level'], $row['mana'], $row['health']), $taps),
                    new Series('Mana gained', new Hue('#4f8cf0')), new Series('Health spent', new Hue('var(--accent)')),
                    'Mana gained from Life Tap, above the line, and the health it cost, below, at each level from 6 to 52',
                )], Span::W7, 'Level '.$peak['level'].' had the most: '.Unit::Count->say($peak['taps']).' taps, '.Unit::Short->say($peak['health']).' health for '.Unit::Short->say($peak['mana']).' mana.'),
                new Module('Life Tap', [
                    new Pair([new PairRow('Health traded', $health, Unit::Short), new PairRow('Taps in combat', 71, Unit::Percent), new PairRow('Most taps in a row', 7)]),
                    new Titled('Time spent drinking, of played time', [new Rank([
                        new RankRow('Name-Surname', 1.1, tone: Tone::Own), new RankRow('Warlocks', 1.6), new RankRow('Every class', 5.9),
                    ], Unit::PercentTenth, true)], $icon(430)),
                ], Span::W5, '6 deaths came within 5 seconds of a Life Tap.', $icon(1454)),
            ]),
            new Row([
                new Module('The demon that was out, by level', [new Stack(
                    array_map(fn (array $shares, int $i): StackColumn => new StackColumn($i + 1, $shares), $demons, array_keys($demons)),
                    [
                        new Series('Imp', new Hue('#d9a336')), new Series('Voidwalker', new Hue('var(--class)')), new Series('Succubus', new Hue('var(--soft)')),
                        new Series('Felhunter', new Hue('#2fb3b3')), new Series('No demon out', new Hue('#3d3d46')),
                    ],
                    'Share of played time each demon was out at each level from 1 to 52',
                )], Span::W8, 'The Voidwalker was out for most of every level from 10 to 24.'),
                new Module('Demons', [
                    new Rank($demonRows, Unit::Hours, of: $max),
                    new Pair([new PairRow('Summons', 1480), new PairRow('Health Funnel', '3.2 h'), new PairRow('Sacrificed', 0)]),
                ]),
            ]),
            new Row([
                new Module('Curses and banes cast', [new Rank(array_map(fn (array $c): RankRow => new RankRow($c[1], $c[2], $icon($c[0]), Tone::Own), $curses))]),
                new Module('Damage by spell', [new Rank([
                    ...array_map(fn (array $d): RankRow => new RankRow($d[1], $d[2], $icon($d[0])), $damage),
                    new RankRow('Every other spell', 7),
                ], Unit::Percent)]),
                new Module('What finished the kill', [new Rank([
                    new RankRow('Your killing blow', 58), new RankRow('A curse, a bane or the demon, after you looked away', 31), new RankRow('A party member', 11),
                ], Unit::Percent, true)], note: '8,912 creatures died to a curse, a bane or the demon with no killing blow of yours.'),
            ]),
        ];
    }

    /** @return list<TalentModule> */
    private static function talents(): array
    {
        $split = fn (int $used, string $a, string $b): Split => new Split([new SplitPart($a, $used, Tone::Own), new SplitPart($b, 100 - $used, Tone::Lost)]);

        return [
            new TalentModule('Nightfall', 2, 2, [
                new Pair([new PairRow('Shadow Trances', 1204), new PairRow('Used', 81, Unit::Percent), new PairRow('Seconds to use one', 2.1, Unit::Decimal)]),
                $split(81, 'Spent on an instant Shadow Bolt', 'Ran out'),
            ], Icon::spell(18094)),
            new TalentModule('Shadowburn', 1, 1, [
                new Pair([new PairRow('Casts', 1190), new PairRow('Paid the shard back', 42, Unit::Percent), new PairRow('Shards lost to it', 692)]),
                $split(42, 'The target died in 5 seconds', 'It lived'),
            ], Icon::spell(17877)),
            new TalentModule('Siphon Life', 1, 1, [
                new Pair([new PairRow('Casts', 3412), new PairRow('Of your healing', 38, Unit::Percent), new PairRow('Fights with it', 64, Unit::Percent)]),
                $split(64, 'Fights it was cast in', 'Fights without'),
            ], Icon::spell(18265)),
        ];
    }

    /** @return list<Row> */
    private static function party(): array
    {
        $icon = Icon::spell(...);
        $control = [[5782, 'Fear', 2210], [6789, 'Death Coil', 301], [5484, 'Howl of Terror', 96], [710, 'Banish', 44], [1098, 'Subjugate Demon', 38]];

        return [new Row([
            new Module('Stones', [new Rank([
                new RankRow('Healthstones made', 312, $icon(6201), Tone::Own), new RankRow('Eaten', 204, Icon::item(5512)), new RankRow('Handed to another player', 41, Icon::item(5512)),
                new RankRow('Soulstones made', 268, $icon(693), Tone::Own), new RankRow('Deaths undone by your own', 9, Icon::item(5232)),
            ], of: 312)]),
            new Module('Summons', [
                new Pair([new PairRow('Players summoned', 186), new PairRow('At a dungeon door', 71, Unit::Percent), new PairRow('Most in a day', 14)]),
                new Rank([new RankRow('Greywatch Ford', 48), new RankRow('Saltmarsh Reach', 31), new RankRow('Pale Rest', 22), new RankRow('Hollowmere', 17)], plain: true),
            ], icon: $icon(698)),
            new Module('Fear and control', [new Rank(array_map(fn (array $c): RankRow => new RankRow($c[1], $c[2], $icon($c[0]), Tone::Own), $control))], note: '3% of Fear casts were cut short.'),
        ])];
    }
}
