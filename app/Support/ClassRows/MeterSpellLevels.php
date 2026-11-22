<?php

namespace App\Support\ClassRows;

/** The `meter_spells` rows of a character kept apart by level (docs/ROWS.md): what a damage or healing meter listed among a fight's five largest, by spell name and level. */
final class MeterSpellLevels
{
    /**
     * @param  list<\stdClass>  $rows  columns meter, spell_id, level, amount
     * @param  array<int, string>  $spellNames
     */
    public function __construct(private readonly array $rows, private readonly array $spellNames) {}

    /**
     * What the meter listed for the spells whose name matches a pattern (`*` is any text), by level.
     *
     * @return array<int, int>
     */
    public function byLevel(Meter $meter, string ...$patterns): array
    {
        $by = [];
        foreach ($this->rows as $row) {
            $name = $this->spellNames[(int) $row->spell_id] ?? null;
            if ($row->meter !== $meter->value || $row->level === null || $name === null || ! SpellBook::matches($patterns, $name)) {
                continue;
            }
            $by[(int) $row->level] = ($by[(int) $row->level] ?? 0) + (int) $row->amount;
        }
        ksort($by);

        return $by;
    }
}
