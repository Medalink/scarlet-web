<?php

namespace App\Support\ClassRows;

/**
 * What went into and out of the bags for a set of items: the rows summed by direction, place and the spell tied to the
 * change. A destroyed item (`FlowPlace::Destroyed`) counts as out.
 *
 * A count of things made (soul shards, conjured water and food, stones) is `made()`: what came in at place `o`, where
 * a spell put it. A withdrawal from the bank, a trade, mail, a vendor, loot or the auction house is a change in the
 * bags and not a making, so it is in `in()` and `held()` and never in `made()`. What a spell used is `spent()`, the same
 * rule on the way out; a deposit, a trade, a sale or mail are not spent.
 */
final readonly class ItemFlow
{
    /** @param  list<array{dir: FlowDirection, place: FlowPlace, spell: int|null, quantity: int}>  $rows */
    public function __construct(public array $rows) {}

    /** Units a spell made: into the bags at place `o` only. */
    public function made(): int
    {
        return $this->sum(FlowDirection::In, FlowPlace::Other);
    }

    /** Units a spell used: out of the bags at place `o` only, destroyed units not included. */
    public function spent(): int
    {
        return $this->sum(FlowDirection::Out, FlowPlace::Other);
    }

    /** Units into the bags in any way, a withdrawal or a trade included. */
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
