<?php

namespace App\Support\ClassPage\Hunter;

use App\Support\ClassPage\Common\Leader;
use App\Support\ClassPage\Hue;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Series;
use App\Support\ClassPage\Span;
use App\Support\ClassPage\Stack;
use App\Support\ClassPage\StackColumn;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\SpellTotals;
use App\Support\StateKind;

/** The aspect that was up, by level, the shots cast and how long Auto Shot ran. */
final readonly class Shooting
{
    private const array HUES = [
        'Aspect of the Hawk' => '#9fc164',
        'Aspect of the Monkey' => '#d0a05f',
        'Aspect of the Cheetah' => '#e0b34f',
        'Aspect of the Pack' => '#8fa3c8',
        'Aspect of the Wild' => '#d07050',
        'Aspect of the Beast' => '#6f8f5f',
    ];

    private const string NONE = '#3d3d46';

    public function __construct(private ClassRows $rows) {}

    /** Hours of Auto Shot, from each `AR` line that starts it to the line that ends it. */
    public function autoShotHours(): float
    {
        return $this->autoShot()['ms'] / 3_600_000;
    }

    /** The share of fight time that Auto Shot ran, 0 to 100. */
    public function autoShotShare(): float
    {
        $fights = $this->rows->states()->fights()->ms;

        return $fights === 0 ? 0.0 : min(100.0, $this->autoShot()['fightMs'] / $fights * 100);
    }

    /**
     * The `r` states carry the spell id and no name, so Auto Shot is found by the name of that id.
     *
     * @return array{ms: int, fightMs: int}
     */
    private function autoShot(): array
    {
        $states = $this->rows->states()->of(StateKind::AutoRepeat);
        $names = $this->rows->spellNames(array_values(array_unique(array_map(fn ($state): int => $state->refId, $states))));
        $ms = $fightMs = 0;
        foreach ($states as $state) {
            if (($names[$state->refId] ?? '') === 'Auto Shot') {
                $ms += $state->ms;
                $fightMs += $state->fightMs;
            }
        }

        return ['ms' => $ms, 'fightMs' => $fightMs];
    }

    public function aspects(): ?Module
    {
        $states = [];
        foreach (array_keys(self::HUES) as $name) {
            $sum = $this->rows->states()->sum(StateKind::OwnBuff, $name);
            if ($sum->ms > 0) {
                $states[str_replace('Aspect of the ', '', $name)] = [$name, $sum];
            }
        }
        if ($states === []) {
            return null;
        }
        $series = array_map(fn (array $one): Series => new Series(str_replace('Aspect of the ', '', $one[0]), new Hue(self::HUES[$one[0]])), array_values($states));
        $series[] = new Series('No aspect', new Hue(self::NONE));
        $columns = [];
        $leading = [];
        foreach ($this->rows->playedByLevel() as $level => $whole) {
            if ($level === 0 || $whole === 0) {
                continue;
            }
            $shares = array_map(fn (array $one): int => min($whole, $one[1]->byLevel[$level]['ms'] ?? 0), array_values($states));
            $in = min($whole, array_sum($shares));
            $columns[] = new StackColumn($level, [...array_map(fn (int $ms): float => $ms / $whole, $shares), ($whole - $in) / $whole]);
            $leading[$level] = Leader::of(array_map(fn (string $name): string => 'Aspect of the '.$name, array_keys($states)), $shares, $whole);
        }
        if ($columns === []) {
            return null;
        }
        $label = 'Share of the time played that each aspect was up at each level from '.$columns[0]->label.' to '.$columns[count($columns) - 1]->label;

        return new Module('The aspect that was up, by level', [new Stack($columns, $series, $label)], Span::W8, Leader::note($leading, '%s was up for most of every level from %d to %d.'));
    }

    public function shots(): ?Module
    {
        $cast = array_values(array_filter($this->rows->spells()->named(...Spells::SHOTS), fn (SpellTotals $spell): bool => $spell->casts > 0));
        if ($cast === []) {
            return null;
        }
        $rows = array_map(fn (SpellTotals $spell): RankRow => new RankRow($spell->name, $spell->casts, Spells::icon($spell), Tone::Own), $cast);
        $share = $this->autoShotShare();

        return new Module('Shots', [new Rank($rows)], note: $share > 0 ? 'Auto Shot ran for '.Unit::Count->say($share).'% of fight time.' : null);
    }
}
