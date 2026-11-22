<?php

namespace App\Support;

/** The layers of the world map: what is counted on the world grid. The value is the name in URLs and in the API. */
enum WorldLayer: string
{
    case Paths = 'paths';
    case Deaths = 'deaths';
    case Creatures = 'creatures';

    public function label(): string
    {
        return match ($this) {
            self::Paths => 'Paths',
            self::Deaths => 'Deaths',
            self::Creatures => 'Creatures met',
        };
    }

    /** The ruleset rollup on the layer's finest grid that holds this layer. */
    public function rollup(): Rollup
    {
        return match ($this) {
            self::Paths => Rollup::WorldHeatFineDailyRuleset,
            self::Deaths => Rollup::WorldDeathHeatDailyRuleset,
            self::Creatures => Rollup::WorldSightingHeatDailyRuleset,
        };
    }

    /** The columns of the layer's finest grid: its rollup's `cell_i` runs from 0 to one less. */
    public function columns(): int
    {
        return match ($this) {
            self::Paths => HeatGrid::columns(HeatGrid::LEVELS - 1),
            default => HeatGrid::columns(HeatGrid::LEVELS - 2),
        };
    }

    /** The levels of `HeatGrid` the layer has: 0 up to the one that is its finest grid. */
    public function levels(): int
    {
        return HeatGrid::level($this->columns()) + 1;
    }

    /**
     * The select of one cell of a level: its counter summed and the characters that contributed. A level coarser
     * than the rollup's grid divides the rollup's cell numbers.
     *
     * @return literal-string
     */
    public function cellSql(int $level): string
    {
        $cell = match ($this->columns() / HeatGrid::columns($level)) {
            1 => 'cell_i, cell_j',
            2 => '(cell_i / 2)::smallint as cell_i, (cell_j / 2)::smallint as cell_j',
            4 => '(cell_i / 4)::smallint as cell_i, (cell_j / 4)::smallint as cell_j',
            default => '(cell_i / 8)::smallint as cell_i, (cell_j / 8)::smallint as cell_j',
        };

        return match ($this) {
            self::Paths => $cell.', sum(points) as value, distinct_count(rollup(characters)) as characters',
            self::Deaths => $cell.', sum(deaths) as value, distinct_count(rollup(characters)) as characters',
            self::Creatures => $cell.', sum(sightings) as value, distinct_count(rollup(characters)) as characters',
        };
    }

    /**
     * The `groupBy` of the select of `cellSql()`.
     *
     * @return literal-string
     */
    public function cellGroup(int $level): string
    {
        return match ($this->columns() / HeatGrid::columns($level)) {
            1 => 'cell_i, cell_j',
            2 => 'cell_i / 2, cell_j / 2',
            4 => 'cell_i / 4, cell_j / 4',
            default => 'cell_i / 8, cell_j / 8',
        };
    }
}
