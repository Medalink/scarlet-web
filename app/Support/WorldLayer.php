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

    /** The ruleset rollup on the world grid that holds this layer. */
    public function rollup(): Rollup
    {
        return match ($this) {
            self::Paths => Rollup::WorldHeatDailyRuleset,
            self::Deaths => Rollup::WorldDeathHeatDailyRuleset,
            self::Creatures => Rollup::WorldSightingHeatDailyRuleset,
        };
    }

    /**
     * The select of one world cell: its counter summed and the characters that contributed.
     *
     * @return literal-string
     */
    public function cellSql(): string
    {
        return match ($this) {
            self::Paths => 'cell_i, cell_j, sum(points) as value, distinct_count(rollup(characters)) as characters',
            self::Deaths => 'cell_i, cell_j, sum(deaths) as value, distinct_count(rollup(characters)) as characters',
            self::Creatures => 'cell_i, cell_j, sum(sightings) as value, distinct_count(rollup(characters)) as characters',
        };
    }
}
