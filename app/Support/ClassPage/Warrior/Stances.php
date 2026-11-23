<?php

namespace App\Support\ClassPage\Warrior;

use App\Support\ClassPage\Common\Leader;
use App\Support\ClassPage\Hue;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Pair;
use App\Support\ClassPage\PairRow;
use App\Support\ClassPage\Series;
use App\Support\ClassPage\Span;
use App\Support\ClassPage\Split;
use App\Support\ClassPage\SplitPart;
use App\Support\ClassPage\Stack;
use App\Support\ClassPage\StackColumn;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\StateTotals;
use App\Support\StateKind;

/** The stance held: its share of fight time overall and by level, and how often the warrior changed it. */
final readonly class Stances
{
    private const array HUES = ['Battle Stance' => 'var(--class)', 'Defensive Stance' => '#6d89b0', 'Berserker Stance' => '#c8452f'];

    private const string NONE = '#3d3d46';

    /** @param  array<string, StateTotals>  $held  by the stance's name */
    private function __construct(private ClassRows $rows, private Spells $spells, private array $held, private int $fightMs) {}

    public static function of(ClassRows $rows, Spells $spells): self
    {
        $held = [];
        foreach (Spells::STANCES as $name) {
            $held[$name] = $rows->states()->sum(StateKind::OwnBuff, $name);
        }

        return new self($rows, $spells, $held, $rows->states()->fights()->ms);
    }

    /** The stance held longest, as the word before "Stance", or null when none was held. */
    public function heldMost(): ?string
    {
        $top = null;
        foreach ($this->held as $name => $state) {
            if ($state->ms > 0 && ($top === null || $state->ms > $this->held[$top]->ms)) {
                $top = $name;
            }
        }

        return $top === null ? null : str_replace(' Stance', '', $top);
    }

    /** The share of fight time spent in one stance, 0 to 100. */
    public function fightShare(string $name): float
    {
        return $this->fightMs === 0 ? 0.0 : min(100.0, $this->held[$name]->fightMs / $this->fightMs * 100);
    }

    public function hours(string $name): float
    {
        return $this->held[$name]->hours();
    }

    public function split(): ?Module
    {
        $changes = $this->spells->stances;
        if ($this->fightMs === 0 || array_sum(array_map(fn (StateTotals $state): int => $state->fightMs, $this->held)) === 0) {
            return null;
        }
        $parts = [];
        $shown = 0;
        foreach ($this->held as $name => $state) {
            $share = $this->fightShare($name);
            $shown += $share;
            $parts[] = new SplitPart($name.' '.Unit::Count->say($share).'%', $share, new Hue(self::HUES[$name]));
        }
        if ($shown < 99.5) {
            $parts[] = new SplitPart('No stance '.Unit::Count->say(100 - $shown).'%', 100 - $shown, new Hue(self::NONE));
        }
        $fights = $this->rows->states()->fights()->starts;

        return new Module('Stance', [
            new Split($parts),
            new Pair([
                new PairRow('Stance changes', $changes->casts),
                new PairRow('Changes a fight', $fights === 0 ? 0 : $changes->casts / $fights, Unit::Decimal),
                new PairRow('Changed in a fight', $changes->casts === 0 ? 0 : $changes->fightCasts / $changes->casts * 100, Unit::Percent),
            ]),
        ], note: 'Shares of fight time. A stance is held from the cast that took it to the next one.');
    }

    public function stack(): ?Module
    {
        $fights = $this->rows->states()->fights();
        $series = [];
        foreach (self::HUES as $name => $hue) {
            $series[] = new Series($name, new Hue($hue));
        }
        $series[] = new Series('No stance', new Hue(self::NONE));
        $columns = [];
        $leading = [];
        foreach ($this->rows->levels() as $level) {
            $whole = $fights->byLevel[$level]['ms'] ?? 0;
            if ($whole === 0) {
                continue;
            }
            $shares = array_map(fn (StateTotals $state): int => $state->byLevel[$level]['fightMs'] ?? 0, array_values($this->held));
            $in = min($whole, array_sum($shares));
            $columns[] = new StackColumn($level, [...array_map(fn (int $ms): float => min(1.0, $ms / $whole), $shares), ($whole - $in) / $whole]);
            $leading[$level] = Leader::of(array_keys($this->held), $shares, $whole);
        }
        if ($columns === [] || array_sum(array_map(fn (StateTotals $state): int => $state->fightMs, $this->held)) === 0) {
            return null;
        }
        $label = 'Share of fight time each stance was held at each level from '.$columns[0]->label.' to '.$columns[count($columns) - 1]->label;

        return new Module('The stance held, by level', [new Stack($columns, $series, $label)], Span::W7, Leader::note($leading, '%s was held for most of every level from %d to %d.'));
    }
}
