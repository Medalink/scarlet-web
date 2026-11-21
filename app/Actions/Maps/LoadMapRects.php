<?php

namespace App\Actions\Maps;

use App\Models\MapRect;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Loads docs/map-rects.tsv into `map_rects`: one row per zone map, the table's rows replacing what was there. */
class LoadMapRects
{
    /**
     * @return int the number of rows in the table afterwards; a missing file or one with no rows loads nothing
     *             and leaves the table as it is
     */
    public function handle(string $path): int
    {
        if (! is_file($path)) {
            return MapRect::query()->count();
        }

        $rows = $this->rows($path);
        if ($rows === []) {
            return MapRect::query()->count();
        }

        DB::transaction(function () use ($rows): void {
            MapRect::query()->upsert($rows, ['ui_map_id'], ['name', 'u0', 'v0', 'u1', 'v1']);
            MapRect::query()->whereNotIn('ui_map_id', array_column($rows, 'ui_map_id'))->delete();
        });

        return count($rows);
    }

    /** @return list<array{ui_map_id: int, name: string, u0: float, v0: float, u1: float, v1: float}> */
    private function rows(string $path): array
    {
        $rows = [];
        foreach (array_slice(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], 1) as $number => $line) {
            $cells = explode("\t", $line);
            if (count($cells) !== 6 || ! ctype_digit($cells[0]) || ! is_numeric($cells[2]) || ! is_numeric($cells[3]) || ! is_numeric($cells[4]) || ! is_numeric($cells[5])) {
                throw new InvalidArgumentException('Line '.($number + 2)." of {$path} is not ui_map_id, name, u0, v0, u1, v1.");
            }

            $row = ['ui_map_id' => (int) $cells[0], 'name' => $cells[1], 'u0' => (float) $cells[2], 'v0' => (float) $cells[3], 'u1' => (float) $cells[4], 'v1' => (float) $cells[5]];
            if ($row['u1'] <= $row['u0'] || $row['v1'] <= $row['v0']) {
                throw new InvalidArgumentException('Line '.($number + 2)." of {$path} has a rectangle with no area.");
            }

            $rows[$row['ui_map_id']] = $row;
        }

        return array_values($rows);
    }
}
