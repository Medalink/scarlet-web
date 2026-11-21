<?php

namespace App\View\Pages;

use App\Support\HeatLevels;
use App\View\Format;

/** What the world map viewer (resources/js/pages/maps.js) takes in its `data-world-map` attribute. */
final class MapSpec
{
    /**
     * @param  list<array<string, mixed>>  $cells  heat rows: cell_i, cell_j, value
     * @param  array{0: float, 1: float, 2: float, 3: float}|int|null  $fit  a zone id, or the world rectangle to open on: u0, v0, u1, v1
     * @param  list<array<string, mixed>>  $zones  the answer of App\Actions\Public\MapZones
     * @param  array<string, mixed>  $extra  readout, ruleset, lookSwitch, layer: ids and names the page controls use
     * @param  list<array{kind: string, u: float, v: float}>  $marks  crosses and dots on the map, in world coordinates
     * @return array<string, mixed>
     */
    public static function build(array $cells, array $zones, int|array|null $fit = null, array $extra = [], array $marks = []): array
    {
        return self::levelled(HeatLevels::assign($cells), $zones, $fit, $extra, $marks);
    }

    /**
     * The same spec from cells that have their heat level: cell_i, cell_j, level.
     *
     * @param  list<array{0: int, 1: int, 2: int}>  $cells
     * @param  list<array<string, mixed>>  $zones
     * @param  array{0: float, 1: float, 2: float, 3: float}|int|null  $fit
     * @param  array<string, mixed>  $extra
     * @param  list<array{kind: string, u: float, v: float}>  $marks
     * @return array<string, mixed>
     */
    public static function levelled(array $cells, array $zones, int|array|null $fit = null, array $extra = [], array $marks = []): array
    {
        return [
            ...$extra,
            'fit' => $fit,
            'zones' => self::zones($zones),
            'layers' => ['heat' => ['cells' => $cells], 'marks' => $marks],
        ];
    }

    /**
     * The zone rectangles with the sentence the pointer readout shows for each.
     *
     * @param  list<array<string, mixed>>  $zones
     * @return list<array<string, mixed>>
     */
    public static function zones(array $zones): array
    {
        return array_map(fn (array $zone): array => [
            'id' => $zone['id'], 'name' => $zone['name'],
            'u0' => round((float) $zone['u0'], 5), 'v0' => round((float) $zone['v0'], 5), 'u1' => round((float) $zone['u1'], 5), 'v1' => round((float) $zone['v1'], 5),
            'href' => $zone['seconds'] === null ? null : '/zones/'.$zone['id'],
            'say' => self::say($zone),
        ], $zones);
    }

    /** @param  array<string, mixed>  $zone */
    private static function say(array $zone): string
    {
        if ($zone['seconds'] === null) {
            return ', under the pointer: no play recorded.';
        }
        $characters = (int) $zone['characters'];

        return ', under the pointer: '.Format::count($characters, 'character').', '
            .Format::count(round((float) $zone['seconds'] / 3600), 'hour').', '.Format::count((int) $zone['deaths'], 'death').'.';
    }
}
