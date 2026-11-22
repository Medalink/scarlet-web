<?php

namespace App\Support\ClassRows;

/** The `meter_totals` and `meter_spells` rows of a character (docs/ROWS.md): what the client's damage meter wrote after each fight, summed. */
final class Meters
{
    /**
     * @param  list<\stdClass>  $totals  columns meter, level, place, total, best, fights, fight_ms
     * @param  list<\stdClass>  $spells  columns meter, spell_id, amount, fights
     * @param  array<int, string>  $spellNames
     */
    public function __construct(private readonly array $totals, private readonly array $spells, private readonly array $spellNames) {}

    /** The meter's total over every fight. */
    public function total(Meter $meter): int
    {
        $sum = 0;
        foreach ($this->totals as $row) {
            if ($row->meter === $meter->value) {
                $sum += (int) $row->total;
            }
        }

        return $sum;
    }

    /** The largest single fight of a meter. */
    public function best(Meter $meter): int
    {
        $best = 0;
        foreach ($this->totals as $row) {
            if ($row->meter === $meter->value) {
                $best = max($best, (int) $row->best);
            }
        }

        return $best;
    }

    /**
     * The meter's total at each level.
     *
     * @return array<int, int>
     */
    public function byLevel(Meter $meter): array
    {
        $by = [];
        foreach ($this->totals as $row) {
            if ($row->meter === $meter->value && $row->level !== null) {
                $by[(int) $row->level] = ($by[(int) $row->level] ?? 0) + (int) $row->total;
            }
        }
        ksort($by);

        return $by;
    }

    /**
     * Fights the meter reported and their time in milliseconds, at one level or over all of them.
     *
     * @return array{fights: int, ms: int}
     */
    public function fights(Meter $meter, ?int $level = null): array
    {
        $fights = $ms = 0;
        foreach ($this->totals as $row) {
            if ($row->meter === $meter->value && ($level === null || (int) $row->level === $level)) {
                $fights += (int) $row->fights;
                $ms += (int) $row->fight_ms;
            }
        }

        return ['fights' => $fights, 'ms' => $ms];
    }

    /** @return list<MeterSpell> the spells a meter listed, ranks of one name folded, the largest first */
    public function spells(Meter $meter): array
    {
        $by = [];
        foreach ($this->spells as $row) {
            $name = $this->spellNames[(int) $row->spell_id] ?? null;
            if ($row->meter !== $meter->value || $name === null) {
                continue;
            }
            $by[$name] ??= ['amount' => 0, 'fights' => 0, 'ranks' => []];
            $by[$name]['amount'] += (int) $row->amount;
            $by[$name]['fights'] += (int) $row->fights;
            $by[$name]['ranks'][(int) $row->spell_id] = ($by[$name]['ranks'][(int) $row->spell_id] ?? 0) + (int) $row->amount;
        }
        $out = [];
        foreach ($by as $name => $one) {
            arsort($one['ranks']);
            $out[] = new MeterSpell($name, (int) array_key_first($one['ranks']), $one['amount'], $one['fights']);
        }
        usort($out, fn (MeterSpell $a, MeterSpell $b): int => [$b->amount, $a->name] <=> [$a->amount, $b->name]);

        return $out;
    }

    public function firstLevel(): ?int
    {
        $levels = array_filter(array_map(fn (object $row): ?int => $row->level === null ? null : (int) $row->level, $this->totals), fn (?int $level): bool => $level !== null);

        return $levels === [] ? null : min($levels);
    }
}
