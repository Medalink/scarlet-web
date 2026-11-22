<?php

namespace App\Support\ClassPage\Warlock;

use App\Support\ClassPage\ClassFacts;
use App\Support\ClassPage\Drinking;
use App\Support\ClassPage\Hue;
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
use App\Support\ClassPage\Titled;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\SpellTotals;
use App\Support\ClassRows\Tally;
use App\View\Format;

/**
 * Life Tap: the health traded for mana by level, and the tap's own numbers. The client does not report the amounts, so
 * they are each rank's numbers from its description times the taps of that rank, and the module says "estimated". When
 * a rank's description gives none, the chart shows the taps in and out of combat by level and says so.
 */
final readonly class Taps
{
    private const string ESTIMATE = 'Estimated: the client does not report the amounts, so they are each rank\'s own numbers from its description times the taps.';

    private const string COUNTS = 'The client does not report the amounts and a rank\'s description does not give them, so the chart shows taps by level.';

    private const int DEATH_SECONDS = 5;

    /** @param  array<int, array{health: int, mana: int}>|null  $traded  by level; null when a rank has no numbers in its description */
    private function __construct(private SpellTotals $tap, private ClassRows $rows, private ?array $traded) {}

    public static function of(ClassRows $rows, Spells $spells): self
    {
        $tap = $spells->lifeTap;
        $descriptions = $rows->descriptions($tap->ids());
        $per = [];
        foreach ($tap->ids() as $id) {
            $health = self::number($descriptions[$id] ?? '', 'health');
            $mana = self::number($descriptions[$id] ?? '', 'mana');
            if ($health === null || $mana === null) {
                $per = null;
                break;
            }
            $per[$id] = [$health, $mana];
        }
        if ($tap->casts === 0 || $per === null) {
            return new self($tap, $rows, null);
        }
        $traded = [];
        foreach ($tap->rankLevels as $id => $levels) {
            foreach ($levels as $level => $casts) {
                $traded[$level]['health'] = ($traded[$level]['health'] ?? 0) + $casts * $per[$id][0];
                $traded[$level]['mana'] = ($traded[$level]['mana'] ?? 0) + $casts * $per[$id][1];
            }
        }

        return new self($tap, $rows, $traded);
    }

    public function health(): ?int
    {
        return $this->traded === null ? null : array_sum(array_column($this->traded, 'health'));
    }

    public function row(ClassFacts $facts): ?Row
    {
        if ($this->tap->casts === 0) {
            return null;
        }
        $levels = $this->rows->levels();

        return new Row([$this->chart($levels), $this->life($facts)]);
    }

    /** @param  list<int>  $levels */
    private function chart(array $levels): Module
    {
        $first = $levels === [] ? 0 : $levels[0];
        $last = $levels === [] ? 0 : $levels[count($levels) - 1];
        $span = 'at each level from '.$first.' to '.$last;
        if ($this->traded === null) {
            $points = array_map(fn (int $level): MirrorPoint => new MirrorPoint($level, $this->tap->byLevel[$level]['fight'] ?? 0, ($this->tap->byLevel[$level]['casts'] ?? 0) - ($this->tap->byLevel[$level]['fight'] ?? 0)), $levels);

            return new Module('Life Taps in and out of combat, by level', [new Mirror(
                $points, new Series('Taps in combat', Hue::ofClass()), new Series('Taps out of combat', new Hue('#5d5d68')),
                'Life Taps in combat, above the line, and out of combat, below, '.$span, Unit::Count,
            )], Span::W7, self::COUNTS, Spells::icon($this->tap));
        }
        $points = array_map(fn (int $level): MirrorPoint => new MirrorPoint($level, $this->traded[$level]['mana'] ?? 0, $this->traded[$level]['health'] ?? 0), $levels);
        $peak = $levels === [] ? null : array_reduce($levels, fn (?int $best, int $level): int => $best === null || ($this->traded[$level]['mana'] ?? 0) > ($this->traded[$best]['mana'] ?? 0) ? $level : $best);
        $most = $peak === null ? '' : 'Level '.$peak.' had the most: '.Unit::Count->say($this->tap->at($peak)).' taps, '.Unit::Short->say($this->traded[$peak]['health'] ?? 0).' health for '.Unit::Short->say($this->traded[$peak]['mana'] ?? 0).' mana. ';

        return new Module('Health traded for mana, by level', [new Mirror(
            $points, new Series('Mana gained', new Hue('#4f8cf0')), new Series('Health spent', new Hue('var(--accent)')),
            'Mana gained from Life Tap, above the line, and the health it cost, below, '.$span,
        )], Span::W7, $most.self::ESTIMATE);
    }

    private function life(ClassFacts $facts): Module
    {
        $tap = $this->tap;
        $cells = [];
        if ($this->health() !== null) {
            $cells[] = new PairRow('Health traded', $this->health(), Unit::Short);
        }
        $cells[] = new PairRow('Taps in combat', $tap->casts === 0 ? 0 : $tap->fightCasts / $tap->casts * 100, Unit::Percent);
        $cells[] = new PairRow('Most taps in a row', $tap->streak);
        $blocks = [new Pair($cells)];
        $drinks = $this->drinking($facts);
        if ($drinks !== null) {
            $blocks[] = $drinks;
        }
        $deaths = $this->rows->tallies()->count(Tally::DeathAfter, $tap->ids(), range(0, self::DEATH_SECONDS - 1));

        return new Module('Life Tap', $blocks, Span::W5, Format::count($deaths, 'death').' came within '.self::DEATH_SECONDS.' seconds of a Life Tap.', Spells::icon($tap));
    }

    private function drinking(ClassFacts $facts): ?Titled
    {
        $warlocks = $facts->peers->mean(Drinking::FIGURE);
        $every = $facts->peers->mean(Drinking::FIGURE, true);
        if ($warlocks === null || $every === null) {
            return null;
        }

        return new Titled('Time spent drinking, of played time', [new Rank([
            new RankRow(Format::characterName($facts->name), Drinking::share($this->rows), tone: Tone::Own),
            new RankRow('Warlocks', $warlocks),
            new RankRow('Every class', $every),
        ], Unit::PercentTenth, true)], Drinking::icon($this->rows));
    }

    private static function number(string $text, string $word): ?int
    {
        return preg_match('/(\d[\d,]*)\s+'.$word.'\b/i', $text, $found) === 1 ? (int) str_replace(',', '', $found[1]) : null;
    }
}
