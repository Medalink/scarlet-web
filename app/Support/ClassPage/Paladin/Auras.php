<?php

namespace App\Support\ClassPage\Paladin;

use App\Support\ClassPage\Common\Leader;
use App\Support\ClassPage\Hue;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Series;
use App\Support\ClassPage\Span;
use App\Support\ClassPage\Stack;
use App\Support\ClassPage\StackColumn;
use App\Support\ClassRows\ClassRows;
use App\Support\StateKind;

/** The aura that was up, by level: its share of the time played at each level. */
final readonly class Auras
{
    /** @var array<string, array{0: list<string>, 1: string}> the aura's label => [name patterns, colour] */
    private const array GROUPS = [
        'Devotion Aura' => [['Devotion Aura'], '#8fb0d9'],
        'Retribution Aura' => [['Retribution Aura'], 'var(--class)'],
        'Concentration Aura' => [['Concentration Aura'], '#f5d98a'],
        'Sanctity Aura' => [['Sanctity Aura'], '#7fc8a9'],
        'The resistance auras' => [['*Resistance Aura'], '#b98ad9'],
    ];

    private const string NONE = '#3d3d46';

    public function __construct(private ClassRows $rows) {}

    public function module(): ?Module
    {
        $states = [];
        foreach (self::GROUPS as $label => [$patterns]) {
            $sum = $this->rows->states(StateKind::OwnBuff)->sum(StateKind::OwnBuff, ...$patterns);
            if ($sum->ms > 0) {
                $states[$label] = $sum;
            }
        }
        $played = $this->rows->playedByLevel(StateKind::OwnBuff);
        $series = [];
        foreach (array_keys($states) as $label) {
            $series[] = new Series($label, new Hue(self::GROUPS[$label][1]));
        }
        $series[] = new Series('No aura', new Hue(self::NONE));
        $columns = [];
        $leading = [];
        foreach ($this->rows->levels(StateKind::OwnBuff) as $level) {
            $whole = $played[$level] ?? 0;
            if ($whole === 0) {
                continue;
            }
            $shares = array_map(fn ($state): int => min($whole, $state->byLevel[$level]['ms'] ?? 0), array_values($states));
            $in = min($whole, array_sum($shares));
            $columns[] = new StackColumn($level, [...array_map(fn (int $ms): float => $ms / $whole, $shares), ($whole - $in) / $whole]);
            $leading[$level] = Leader::of(array_keys($states), $shares, $whole);
        }
        if ($columns === [] || $states === []) {
            return null;
        }
        $label = 'Share of the time played that each aura was up at each level from '.$columns[0]->label.' to '.$columns[count($columns) - 1]->label;

        return new Module('The aura that was up, by level', [new Stack($columns, $series, $label)], Span::W7, Leader::note($leading, '%s was up for most of every level from %d to %d.'));
    }
}
