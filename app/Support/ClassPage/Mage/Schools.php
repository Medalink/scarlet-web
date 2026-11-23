<?php

namespace App\Support\ClassPage\Mage;

use App\Support\ClassPage\Hue;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Series;
use App\Support\ClassPage\Span;
use App\Support\ClassPage\Stack;
use App\Support\ClassPage\StackColumn;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\Meter;

/** The damage meter's listed spells put under their school, as the share each school took at each level. */
final readonly class Schools
{
    public const string FIRE_HUE = '#f2813a';

    public const string ARCANE_HUE = '#a77bf0';

    private const string NOTE = 'The meter lists the five largest spells of each fight, so the shares count the Frost, Fire and Arcane spells it listed.';

    /**
     * @param  array<string, array<int, int>>  $schools  damage by level, by the school's name
     * @param  list<int>  $levels
     */
    private function __construct(private array $schools, private array $levels) {}

    public static function of(ClassRows $rows): self
    {
        $meter = $rows->meterSpellLevels();

        return new self([
            'Frost' => $meter->byLevel(Meter::Damage, ...Spells::FROST),
            'Fire' => $meter->byLevel(Meter::Damage, ...Spells::FIRE),
            'Arcane' => $meter->byLevel(Meter::Damage, ...Spells::ARCANE),
        ], $rows->levels());
    }

    public function module(Span $span = Span::W7): ?Module
    {
        $columns = [];
        $lead = [];
        foreach ($this->levels as $level) {
            $amounts = array_map(fn (array $by): int => $by[$level] ?? 0, array_values($this->schools));
            $whole = array_sum($amounts);
            if ($whole === 0) {
                continue;
            }
            $columns[] = new StackColumn($level, array_map(fn (int $amount): float => $amount / $whole, $amounts));
            $lead[$level] = (int) array_search(max([0, ...$amounts]), $amounts, true);
        }
        if ($columns === []) {
            return null;
        }
        $names = array_keys($this->schools);
        $first = $columns[0]->label;
        $last = $columns[count($columns) - 1]->label;

        return new Module('Damage by school, by level', [new Stack(
            $columns,
            [new Series($names[0], Hue::ofClass()), new Series($names[1], new Hue(self::FIRE_HUE)), new Series($names[2], new Hue(self::ARCANE_HUE))],
            "Share of the damage each school did at each level from {$first} to {$last}",
        )], $span, $this->lead($lead, $names).self::NOTE);
    }

    /**
     * @param  array<int, int>  $lead  the index of the school that did the most damage at each level
     * @param  list<string>  $names
     */
    private function lead(array $lead, array $names): string
    {
        $first = array_key_first($lead);
        $last = array_key_last($lead);
        if ($first === null || $first === $last || count(array_unique($lead)) !== 1) {
            return '';
        }

        return $names[$lead[$first]]." did the most damage at every level from {$first} to {$last}. ";
    }
}
