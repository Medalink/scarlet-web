<?php

namespace App\Support;

use App\Models\MapRect;

/**
 * Puts a recorded point on the stitched world: a position on a zone map and that map's rectangle give `u, v`.
 * The rectangles are read once. A map with no rectangle (a dungeon) has no world position.
 */
class WorldPlacement
{
    /** A creature sighting's cell is 0.015 of its map across (docs/ROWS.md). */
    private const float SIGHTING_CELL = 0.015;

    /** Positions are the map position times 10000 (docs/ROWS.md). */
    private const float POSITION_SCALE = 10000.0;

    /** @var array<int, array{0: float, 1: float, 2: float, 3: float}>|null */
    private ?array $rects = null;

    /**
     * The world position of a row of one of the three placed tables, or null.
     *
     * @param  array<string, mixed>  $row  a decoder row
     * @return array{u: float, v: float}|null
     */
    public function forRow(FactTable $table, array $row): ?array
    {
        return match ($table) {
            FactTable::PathPoints => $this->place($row['map'] ?? null, $row['x'] ?? null, $row['y'] ?? null, self::POSITION_SCALE),
            FactTable::Deaths => $this->place($row['map_id'] ?? null, $row['x'] ?? null, $row['y'] ?? null, self::POSITION_SCALE),
            FactTable::CreatureSightings => $this->sighting($row),
            default => null,
        };
    }

    /** Whether the table has `u, v` columns. */
    public static function places(FactTable $table): bool
    {
        return in_array($table, [FactTable::PathPoints, FactTable::Deaths, FactTable::CreatureSightings], strict: true);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{u: float, v: float}|null
     */
    private function sighting(array $row): ?array
    {
        if (! isset($row['cell_x'], $row['cell_y'])) {
            return null;
        }
        $half = 0.5;

        return $this->place($row['map'] ?? null, ((float) $row['cell_x'] + $half) * self::SIGHTING_CELL, ((float) $row['cell_y'] + $half) * self::SIGHTING_CELL, 1.0);
    }

    /** @return array{u: float, v: float}|null */
    private function place(mixed $map, mixed $x, mixed $y, float $scale): ?array
    {
        if (! is_int($map) || ! is_numeric($x) || ! is_numeric($y)) {
            return null;
        }
        $rect = $this->rects()[$map] ?? null;
        if ($rect === null) {
            return null;
        }

        return [
            'u' => $rect[0] + (float) $x / $scale * ($rect[2] - $rect[0]),
            'v' => $rect[1] + (float) $y / $scale * ($rect[3] - $rect[1]),
        ];
    }

    /** @return array<int, array{0: float, 1: float, 2: float, 3: float}> */
    private function rects(): array
    {
        if ($this->rects === null) {
            $this->rects = [];
            foreach (MapRect::query()->get(['ui_map_id', 'u0', 'v0', 'u1', 'v1']) as $rect) {
                $this->rects[$rect->ui_map_id] = [(float) $rect->u0, (float) $rect->v0, (float) $rect->u1, (float) $rect->v1];
            }
        }

        return $this->rects;
    }
}
