<?php

namespace App\Support\ClassPage\Hunter;

use App\Support\ClassPage\Callout;
use App\Support\ClassPage\Common\Leader;
use App\Support\ClassPage\Hue;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Series;
use App\Support\ClassPage\Span;
use App\Support\ClassPage\Stack;
use App\Support\ClassPage\StackColumn;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\StateTotals;
use App\Support\StateKind;

/** The pet that was out: the `p` spans of `state_times` by the pet's family, by level, over the time played. */
final readonly class Pets
{
    private const int HAPPY = 3;

    /** @var array<string, string> the families the head's scene has a model for, with the colour of their series */
    private const array FAMILIES = ['Cat' => '#aad372', 'Wolf' => '#7d9c5a', 'Boar' => '#c98a4b', 'Owl' => '#d9c36a'];

    private const array LEFT = ['Owl'];

    private const string OTHER = '#8b8b95';

    private const string NONE = '#3d3d46';

    /** @var array<string, StateTotals> the families of the head by name, each its pets merged */
    private array $families;

    /** @var array<string, string> the pet each family kept longest */
    private array $named;

    private int $otherMs;

    /** @var list<StateTotals> every pet that was out */
    private array $out;

    public function __construct(private ClassRows $rows, private Spells $spells)
    {
        $this->out = array_values(array_filter($rows->states(StateKind::Pet)->of(StateKind::Pet), fn (StateTotals $pet): bool => $pet->name !== '' || $pet->extra !== ''));
        $by = $named = [];
        $longest = [];
        $other = 0;
        foreach ($this->out as $pet) {
            if (! isset(self::FAMILIES[$pet->extra])) {
                $other += $pet->ms;

                continue;
            }
            $by[$pet->extra][] = $pet;
            if ($pet->ms >= ($longest[$pet->extra] ?? -1)) {
                $longest[$pet->extra] = $pet->ms;
                $named[$pet->extra] = $pet->name;
            }
        }
        $families = [];
        foreach (array_keys(self::FAMILIES) as $family) {
            if (isset($by[$family])) {
                $families[$family] = self::merge($by[$family]);
            }
        }
        $this->otherMs = $other;
        $this->families = $families;
        $this->named = $named;
    }

    /** Whether the character shares any pet rows at all; without them every pet figure is withheld, not 0. */
    public function shared(): bool
    {
        return $this->rows->states(StateKind::Pet)->of(StateKind::Pet) !== [];
    }

    public function ms(): int
    {
        return array_sum(array_map(fn (StateTotals $state): int => $state->ms, $this->out));
    }

    public function hours(): float
    {
        return $this->ms() / 3_600_000;
    }

    /** The pet kept longest, as its name, or null when none was out. */
    public function top(): ?StateTotals
    {
        return $this->out[0] ?? null;
    }

    /** The share of the time played that a pet was out, 0 to 100. */
    public function share(): float
    {
        $played = $this->rows->playedMs(StateKind::Pet);

        return $played === 0 ? 0.0 : min(100.0, $this->ms() / $played * 100);
    }

    /** The share of the time a pet was out that it was happy, 0 to 100. */
    public function happy(): float
    {
        $ms = $this->ms();
        $happy = 0;
        foreach ($this->rows->states(StateKind::PetHappiness)->of(StateKind::PetHappiness) as $state) {
            $happy += $state->refId === self::HAPPY ? $state->ms : 0;
        }

        return $ms === 0 ? 0.0 : min(100.0, $happy / $ms * 100);
    }

    /** @return list<Callout> */
    public function callouts(): array
    {
        $callouts = [];
        $total = $this->ms();
        foreach (array_keys(self::FAMILIES) as $family) {
            $state = $this->families[$family] ?? null;
            if ($state === null || $state->ms === 0) {
                continue;
            }
            $pet = $this->named[$family] ?? '';
            $out = 'out '.Unit::Hours->say($state->hours()).' hours';
            $callouts[] = new Callout(strtolower($family), $pet === '' ? $family : $pet, $family.', '.$out, Unit::Count->say($state->ms / $total * 100).'% of pet time', in_array($family, self::LEFT, true));
        }

        return $callouts;
    }

    public function stack(): ?Module
    {
        if ($this->ms() === 0) {
            return null;
        }
        $played = $this->rows->playedByLevel(StateKind::Pet);
        $series = [];
        foreach (self::FAMILIES as $family => $hue) {
            if (isset($this->families[$family])) {
                $series[] = new Series($family, new Hue($hue));
            }
        }
        if ($this->otherMs > 0) {
            $series[] = new Series('Another pet', new Hue(self::OTHER));
        }
        $series[] = new Series('No pet out', new Hue(self::NONE));
        $columns = [];
        $leading = [];
        foreach ($this->rows->levels(StateKind::Pet) as $level) {
            $time = $played[$level] ?? 0;
            $shares = [];
            foreach (array_keys($this->families) as $family) {
                $shares[] = $this->families[$family]->byLevel[$level]['ms'] ?? 0;
            }
            if ($this->otherMs > 0) {
                $shares[] = $this->otherAt($level);
            }
            $out = array_sum($shares);
            $whole = max($time, $out);
            if ($whole === 0) {
                continue;
            }
            $columns[] = new StackColumn($level, [...array_map(fn (int $ms): float => $ms / $whole, $shares), ($whole - $out) / $whole]);
            $leading[$level] = Leader::of(array_keys($this->families), $shares, $whole);
        }
        if ($columns === []) {
            return null;
        }

        return new Module('The pet that was out, by level', [new Stack($columns, $series, 'Share of the time played that each pet was out at each level from '.$columns[0]->label.' to '.$columns[count($columns) - 1]->label)], Span::W7, Leader::note($leading, 'The %s was out for most of every level from %d to %d.'));
    }

    public function list(): ?Module
    {
        if ($this->ms() === 0) {
            return null;
        }
        $rows = [];
        foreach ($this->out as $pet) {
            $rows[] = new RankRow($pet->name === '' ? $pet->extra : $pet->name, $pet->hours(), null, isset(self::FAMILIES[$pet->extra]) ? new Hue(self::FAMILIES[$pet->extra]) : new Hue(self::OTHER), $pet->name === '' ? null : $pet->extra);
        }

        return new Module('Pets', [
            new Rank($rows, Unit::Hours),
            new Pair([
                new PairRow('Fed', $this->spells->feed->casts),
                new PairRow('Revived', $this->spells->revive->casts),
                new PairRow('Happy while out', $this->happy(), Unit::Percent),
            ]),
        ], Span::W5);
    }

    private function otherAt(int $level): int
    {
        $ms = 0;
        foreach ($this->out as $pet) {
            if (! isset(self::FAMILIES[$pet->extra])) {
                $ms += $pet->byLevel[$level]['ms'] ?? 0;
            }
        }

        return $ms;
    }

    /** @param  list<StateTotals>  $list */
    private static function merge(array $list): StateTotals
    {
        $ms = $starts = $fight = 0;
        $levels = [];
        foreach ($list as $state) {
            $ms += $state->ms;
            $starts += $state->starts;
            $fight += $state->fightMs;
            foreach ($state->byLevel as $level => $row) {
                $at = $levels[$level] ?? ['starts' => 0, 'ms' => 0, 'fightMs' => 0];
                $levels[$level] = ['starts' => $at['starts'] + $row['starts'], 'ms' => $at['ms'] + $row['ms'], 'fightMs' => $at['fightMs'] + $row['fightMs']];
            }
        }
        ksort($levels);

        return new StateTotals(0, '', '', $starts, $ms, $fight, $levels);
    }
}
