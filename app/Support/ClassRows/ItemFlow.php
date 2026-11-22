<?php

namespace App\Support\ClassRows;

/**
 * What went into and out of the bags for a set of items: the rows summed by direction, place and the spell tied to the
 * change. A destroyed item (`FlowPlace::Destroyed`) counts as out.
 */
final readonly class ItemFlow
{
    /** @param  list<array{dir: FlowDirection, place: FlowPlace, spell: int|null, quantity: int}>  $rows */
    public function __construct(public array $rows) {}

    public function in(): int
    {
        return $this->sum(FlowDirection::In);
    }

    /** Units out of the bags in any way, destroyed included. */
    public function out(): int
    {
        return $this->sum(FlowDirection::Out);
    }

    public function destroyed(): int
    {
        return $this->sum(FlowDirection::Out, FlowPlace::Destroyed);
    }

    /** What the bags hold now: every change added up, never below 0. */
    public function held(): int
    {
        return max(0, $this->in() - $this->out());
    }

    /** Units that changed by `dir`, at one place or anywhere. */
    public function sum(FlowDirection $dir, ?FlowPlace $place = null): int
    {
        $units = 0;
        foreach ($this->rows as $row) {
            if ($row['dir'] === $dir && ($place === null || $row['place'] === $place)) {
                $units += $row['quantity'];
            }
        }

        return $units;
    }

    /**
     * Units that changed in the rows the client tied to one of these spell ids.
     *
     * @param  list<int>  $spells
     */
    public function bySpells(FlowDirection $dir, array $spells): int
    {
        $units = 0;
        foreach ($this->rows as $row) {
            if ($row['dir'] === $dir && $row['place'] === FlowPlace::Other && $row['spell'] !== null && in_array($row['spell'], $spells, true)) {
                $units += $row['quantity'];
            }
        }

        return $units;
    }

    /** @return array<int, int> units by spell id, for the rows tied to a spell */
    public function spellUnits(FlowDirection $dir): array
    {
        $by = [];
        foreach ($this->rows as $row) {
            if ($row['dir'] === $dir && $row['place'] === FlowPlace::Other && $row['spell'] !== null) {
                $by[$row['spell']] = ($by[$row['spell']] ?? 0) + $row['quantity'];
            }
        }
        arsort($by);

        return $by;
    }
}
