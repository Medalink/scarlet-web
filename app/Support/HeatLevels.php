<?php

namespace App\Support;

/** Heat levels 1 to 4 for the cells of a layer: by rank, so the map reads the same whatever the totals. */
final class HeatLevels
{
    /** The share of cells at or below each of the first three levels. */
    private const array CUTS = [0.5, 0.8, 0.95];

    /**
     * Cells as [cell_i, cell_j, level] from rows that carry a `value`.
     *
     * @param  list<array<string, mixed>>  $rows  cell_i, cell_j, value
     * @return list<array{0: int, 1: int, 2: int}>
     */
    public static function assign(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $values = array_map(fn (array $row): float => (float) $row['value'], $rows);
        sort($values);
        $last = count($values) - 1;
        $limits = array_map(fn (float $cut): float => $values[(int) floor($cut * $last)], self::CUTS);

        return array_map(function (array $row) use ($limits): array {
            $level = 1;
            foreach ($limits as $limit) {
                $level += (float) $row['value'] > $limit ? 1 : 0;
            }

            return [(int) $row['cell_i'], (int) $row['cell_j'], $level];
        }, $rows);
    }
}
