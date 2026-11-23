<?php

namespace App\Support\ClassPage\Shaman;

use App\Support\ClassPage\Callout;
use App\Support\ClassPage\Casts;
use App\Support\ClassPage\Columns;
use App\Support\ClassPage\Hue;
use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Link;
use App\Support\ClassPage\LinkItem;
use App\Support\ClassPage\LinkPair;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Series;
use App\Support\ClassPage\Span;
use App\Support\ClassPage\Split;
use App\Support\ClassPage\SplitPart;
use App\Support\ClassPage\Stack;
use App\Support\ClassPage\StackColumn;
use App\Support\ClassPage\Titled;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\Tally;
use App\Support\StateKind;

/**
 * The totems: the `t` rows of `state_times` (a drop each start, the time each stood, the part of it inside fights), how
 * each drop ended from the `totem_fate` tally and how many elements stood in each fight from `fight_totems`.
 *
 * @phpstan-type Totem array{name: string, ref: int, drops: int, ms: int, levels: array<int, int>}
 */
final readonly class Totems
{
    private const array FATES = [1 => 0, 2 => 1, 3 => 2, 5 => 3];

    private const int TOTEM_SLOTS = 4;

    /** @param  array<int, list<Totem>>  $bySlot  each slot's totems, the most dropped first */
    private function __construct(private array $bySlot, private ClassRows $rows) {}

    public static function of(ClassRows $rows): self
    {
        $by = [];
        foreach ($rows->states()->of(StateKind::Totem) as $state) {
            $slot = (int) $state->extra;
            if (! isset(Spells::ELEMENTS[$slot]) || $state->name === '') {
                continue;
            }
            $one = $by[$slot][$state->name] ?? ['name' => $state->name, 'ref' => $state->refId, 'drops' => 0, 'ms' => 0, 'levels' => [], 'best' => -1];
            if ($state->starts > $one['best']) {
                $one['ref'] = $state->refId;
                $one['best'] = $state->starts;
            }
            $one['drops'] += $state->starts;
            $one['ms'] += $state->ms;
            foreach ($state->byLevel as $level => $row) {
                $one['levels'][$level] = ($one['levels'][$level] ?? 0) + $row['fightMs'];
            }
            $by[$slot][$state->name] = $one;
        }
        $sorted = [];
        foreach ($by as $slot => $list) {
            $totems = array_values(array_map(fn (array $one): array => ['name' => $one['name'], 'ref' => $one['ref'], 'drops' => $one['drops'], 'ms' => $one['ms'], 'levels' => $one['levels']], $list));
            usort($totems, fn (array $a, array $b): int => [$b['drops'], $b['ms'], $a['name']] <=> [$a['drops'], $a['ms'], $b['name']]);
            $sorted[$slot] = $totems;
        }
        ksort($sorted);

        return new self($sorted, $rows);
    }

    public function drops(): int
    {
        return array_sum(array_map(fn (array $list): int => array_sum(array_column($list, 'drops')), $this->bySlot));
    }

    public function stoodHours(): float
    {
        return array_sum(array_map(fn (array $list): int => array_sum(array_column($list, 'ms')), $this->bySlot)) / 3_600_000;
    }

    /** @return Totem|null the totem dropped most in any element */
    public function top(): ?array
    {
        $top = null;
        foreach ($this->bySlot as $list) {
            foreach ($list as $totem) {
                if ($top === null || [$totem['drops'], $totem['ms']] > [$top['drops'], $top['ms']]) {
                    $top = $totem;
                }
            }
        }

        return $top;
    }

    /** @return list<Callout> one for each element with a totem, on that element's totem of the scene */
    public function callouts(): array
    {
        $callouts = [];
        foreach ($this->bySlot as $slot => $list) {
            $top = $list[0];
            $callouts[] = new Callout(
                strtolower(Spells::ELEMENTS[$slot][0]),
                $top['name'],
                Spells::ELEMENTS[$slot][0].', dropped '.Unit::Count->say($top['drops']).' times',
                Unit::Hours->say($top['ms'] / 3_600_000).' hours standing',
            );
        }

        return $callouts;
    }

    public function columns(): ?Module
    {
        if ($this->bySlot === []) {
            return null;
        }
        $most = max(array_map(fn (array $list): int => $list[0]['drops'], $this->bySlot));
        $columns = [];
        foreach ($this->bySlot as $slot => $list) {
            [$element, $hue] = Spells::ELEMENTS[$slot];
            $rank = new Rank(array_map(fn (array $totem): RankRow => new RankRow(self::short($totem['name']), $totem['drops'], Icon::spell($totem['ref']), new Hue($hue)), $list), of: $most);
            $pair = new Pair([
                new PairRow('Dropped', array_sum(array_column($list, 'drops'))),
                new PairRow('Stood', Unit::Hours->say(array_sum(array_column($list, 'ms')) / 3_600_000).' h'),
            ]);
            $columns[] = new Titled($element, [$rank, $pair], Icon::spell($list[0]['ref']));
        }
        $top = $this->top();
        $note = $top === null ? null : $top['name'].' went down '.Unit::Count->say($top['drops']).' times'.($this->aloneAtTop($top) ? ', more than any other totem.' : '.');

        return new Module('Totems, by element', count($columns) >= 2 ? [new Columns($columns)] : $columns, Span::W12, $note);
    }

    public function ended(): ?Module
    {
        $pairs = [];
        $left = [];
        $totals = [0, 0, 0, 0];
        $slots = array_keys($this->bySlot);
        foreach ($slots as $at => $slot) {
            $left[] = new LinkItem($this->bySlot[$slot][0]['name'], Icon::spell($this->bySlot[$slot][0]['ref']));
            $counts = [0, 0, 0, 0];
            foreach ($this->rows->tallies()->of(Tally::TotemFate) as $row) {
                if ($row->a === $slot && isset(self::FATES[$row->b])) {
                    $counts[self::FATES[$row->b]] += $row->n;
                }
            }
            foreach ($counts as $to => $count) {
                $pairs[] = new LinkPair($at, $to, $count);
                $totals[$to] += $count;
            }
        }
        $all = array_sum($totals);
        if ($all === 0) {
            return null;
        }
        $link = new Link(
            $left,
            [new LinkItem('Full time'), new LinkItem('Replaced'), new LinkItem('Gone early', null, Tone::Lost), new LinkItem('Still standing', null, Tone::Held)],
            $pairs,
            'totems',
            'The element of each totem dropped, on the left, and how it ended, on the right',
        );

        return new Module('How a totem ended', [$link], Span::W7, $totals[2] === 0 ? null : Unit::Count->say($totals[2]).' of '.Unit::Count->say($all).' totems were gone before their time.');
    }

    /** @return array<int, int> fights by how many of the four elements had a totem down */
    private function fightsByElements(): array
    {
        $by = [];
        foreach ($this->rows->tallies()->of(Tally::FightTotems) as $row) {
            $by[$row->a] = ($by[$row->a] ?? 0) + $row->n;
        }

        return $by;
    }

    public function fourDownShare(): float
    {
        $by = $this->fightsByElements();

        return Casts::share($by[self::TOTEM_SLOTS] ?? 0, array_sum($by));
    }

    public function inFight(): ?Module
    {
        $by = $this->fightsByElements();
        $fights = array_sum($by);
        if ($fights === 0) {
            return null;
        }
        $four = $by[self::TOTEM_SLOTS] ?? 0;
        $none = $by[0] ?? 0;
        $some = $fights - $four - $none;
        $parts = array_values(array_filter([
            $four > 0 ? new SplitPart('All four down, '.Unit::Count->say(Casts::share($four, $fights)).'% of '.Unit::Count->say($fights).' fights', $four, Tone::Own) : null,
            $some > 0 ? new SplitPart('Some down, '.Unit::Count->say(Casts::share($some, $fights)).'%', $some, new Hue('#7f93b5')) : null,
            $none > 0 ? new SplitPart('None, '.Unit::Count->say(Casts::share($none, $fights)).'%', $none, Tone::Lost) : null,
        ]));

        return new Module('Totems in a fight', [
            new Pair([
                new PairRow('Fights with all four down', $four),
                new PairRow('Median elements down a fight', $this->median($by)),
                new PairRow('Fights with a totem down', $fights - $none),
            ]),
            new Split($parts),
        ], Span::W5);
    }

    /** @param  array<int, int>  $by */
    private function median(array $by): int|float
    {
        $sorted = [];
        foreach ($by as $count => $fights) {
            array_push($sorted, ...array_fill(0, $fights, $count));
        }
        sort($sorted);
        $n = count($sorted);

        return $n % 2 === 1 ? $sorted[intdiv($n, 2)] : ($sorted[$n / 2 - 1] + $sorted[$n / 2]) / 2;
    }

    public function byLevel(): ?Module
    {
        $slots = array_keys($this->bySlot);
        $columns = [];
        foreach ($this->rows->levels() as $level) {
            $time = array_map(fn (int $slot): int => array_sum(array_map(fn (array $totem): int => $totem['levels'][$level] ?? 0, $this->bySlot[$slot])), $slots);
            $all = array_sum($time);
            if ($all > 0) {
                $columns[] = new StackColumn($level, array_map(fn (int $ms): float => $ms / $all, $time));
            }
        }
        if ($columns === []) {
            return null;
        }
        $stack = new Stack(
            $columns,
            array_map(fn (int $slot): Series => new Series(Spells::ELEMENTS[$slot][0], new Hue(Spells::ELEMENTS[$slot][1])), $slots),
            'Share of the time in fights that each element had a totem down at each level from '.$columns[0]->label.' to '.$columns[count($columns) - 1]->label,
        );

        return new Module('Elements with a totem down, by level', [$stack], Span::W8);
    }

    /** A rank of the hours the group totems stood. */
    public function hours(): ?Rank
    {
        $found = [];
        foreach ($this->bySlot as $list) {
            foreach ($list as $totem) {
                if (in_array($totem['name'], Spells::GROUP_TOTEMS, true)) {
                    $found[] = $totem;
                }
            }
        }
        usort($found, fn (array $a, array $b): int => [$b['ms'], $a['name']] <=> [$a['ms'], $b['name']]);
        if ($found === []) {
            return null;
        }

        return new Rank(array_map(fn (array $totem): RankRow => new RankRow(self::short($totem['name']), $totem['ms'] / 3_600_000, Icon::spell($totem['ref']), Tone::Own), $found), Unit::Hours);
    }

    public static function short(string $name): string
    {
        return preg_replace('/ Totem$/', '', $name) ?? $name;
    }

    /** @param  Totem  $top */
    private function aloneAtTop(array $top): bool
    {
        foreach ($this->bySlot as $list) {
            foreach ($list as $totem) {
                if ($totem['name'] !== $top['name'] && $totem['drops'] >= $top['drops']) {
                    return false;
                }
            }
        }

        return true;
    }
}
