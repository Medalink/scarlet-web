<?php

namespace App\Support\ClassPage\Warrior;

use App\Support\ClassPage\Icon;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Rank;
use App\Support\ClassPage\RankRow;
use App\Support\ClassPage\Span;
use App\Support\ClassPage\Tone;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\SpellBook;
use App\Support\ClassRows\Tally;
use App\Support\ClassRows\TallyRow;

/** How a fight opened: the first spell cast from a second and a half before the fight to three seconds after it (`fight_first`). */
final readonly class Opens
{
    private const array GROUPS = [
        'charge' => ['Charge', ['Charge']],
        'intercept' => ['Intercept', ['Intercept']],
        'bloodrage' => ['Bloodrage', ['Bloodrage']],
        'ranged' => ['A ranged pull', ['Throw', 'Shoot*']],
    ];

    public function __construct(private ClassRows $rows) {}

    public function module(): ?Module
    {
        $tally = $this->rows->tallies()->of(Tally::FightFirst);
        $names = $this->rows->spellNames(array_values(array_filter(array_map(fn (TallyRow $row): int => $row->a, $tally), fn (int $id): bool => $id !== 0)));
        $count = $best = $icon = [];
        $fights = 0;
        foreach ($tally as $row) {
            $fights += $row->n;
            $key = $row->a === 0 ? 'none' : $this->groupOf($names[$row->a] ?? '');
            $count[$key] = ($count[$key] ?? 0) + $row->n;
            if ($row->a !== 0 && $row->n > ($best[$key] ?? 0)) {
                $best[$key] = $row->n;
                $icon[$key] = $row->a;
            }
        }
        if ($fights === 0) {
            return null;
        }
        $rows = [];
        foreach ([...array_keys(self::GROUPS), 'other', 'none'] as $key) {
            if (($count[$key] ?? 0) === 0) {
                continue;
            }
            $label = match ($key) {
                'other' => 'Another spell',
                'none' => 'Nothing in the first 3 seconds',
                default => self::GROUPS[$key][0],
            };
            $rows[] = new RankRow($label, $count[$key] / $fights * 100, isset($icon[$key]) ? Icon::spell($icon[$key]) : null, $key === 'none' ? Tone::Lost : Tone::Own);
        }

        return new Module('How a fight opens', [new Rank($rows, Unit::Percent)], Span::W5);
    }

    private function groupOf(string $name): string
    {
        foreach (self::GROUPS as $key => [, $patterns]) {
            if (SpellBook::matches($patterns, $name)) {
                return $key;
            }
        }

        return 'other';
    }
}
