<?php

namespace App\Support\ClassRows;

/** The `item_flows` rows of a character, summed by item, direction, place and spell (docs/ROWS.md, `item_flows`). */
final class ItemFlows
{
    /**
     * @param  list<\stdClass>  $rows  columns item_id, dir, place, spell_id, quantity
     * @param  array<int, string>  $itemNames
     */
    public function __construct(private readonly array $rows, private readonly array $itemNames) {}

    /** The flow of the items with these ids. */
    public function ofItems(int ...$ids): ItemFlow
    {
        return $this->flow(fn (int $id): bool => in_array($id, $ids, true));
    }

    /** The flow of every item whose name matches a pattern (`*` is any text). */
    public function named(string ...$patterns): ItemFlow
    {
        return $this->flow(fn (int $id): bool => isset($this->itemNames[$id]) && SpellBook::matches($patterns, $this->itemNames[$id]));
    }

    /** @return list<int> the ids of the items whose name matches a pattern and that moved */
    public function idsNamed(string ...$patterns): array
    {
        $ids = [];
        foreach ($this->rows as $row) {
            $id = (int) $row->item_id;
            if (isset($this->itemNames[$id]) && SpellBook::matches($patterns, $this->itemNames[$id])) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /** @param  callable(int): bool  $wanted */
    private function flow(callable $wanted): ItemFlow
    {
        $sums = [];
        foreach ($this->rows as $row) {
            if (! $wanted((int) $row->item_id)) {
                continue;
            }
            $place = FlowPlace::tryFrom((string) $row->place) ?? FlowPlace::Other;
            $dir = FlowDirection::from((int) $row->dir);
            $spell = $row->spell_id === null ? null : (int) $row->spell_id;
            $key = $dir->value.$place->value.($spell ?? '-');
            $sums[$key] ??= ['dir' => $dir, 'place' => $place, 'spell' => $spell, 'quantity' => 0];
            $sums[$key]['quantity'] += (int) $row->quantity;
        }

        return new ItemFlow(array_values($sums));
    }
}
