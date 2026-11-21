<?php

namespace App\View\Pages;

use App\Support\HeatLevels;
use App\Support\WorldGrid;
use App\View\Format;

/** What the world map viewer (resources/js/pages/maps.js) takes in its `data-world-map` attribute. */
final class MapSpec
{
    /** The share of cells left out at each end of a column or row range by `around()`. */
    private const float TRIM = 0.02;

    /** The share of a rectangle's size `around()` adds on each side. */
    private const float PADDING = 0.12;

    /** The smallest side of the rectangle `around()` pads, in world widths: a character that never left one spot. */
    private const float MIN_SIDE = 0.02;

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
        return [
            ...$extra,
            'fit' => $fit,
            'zones' => self::zones($zones),
            'layers' => ['heat' => ['cells' => HeatLevels::assign($cells)], 'marks' => $marks],
        ];
    }

    /**
     * The world rectangle to open a map on so that its cells fill it: u0, v0, u1, v1 from the 2nd to the 98th percentile of
     * the cells' columns and rows, which leaves out the odd cell far from the rest, then `PADDING` of its size on each side.
     *
     * @param  list<array<string, mixed>>  $cells  heat rows: cell_i, cell_j
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    public static function around(array $cells): ?array
    {
        if ($cells === []) {
            return null;
        }
        $columns = array_map(fn (array $cell): int => (int) $cell['cell_i'], $cells);
        $rows = array_map(fn (array $cell): int => (int) $cell['cell_j'], $cells);
        sort($columns);
        sort($rows);
        $last = count($cells) - 1;
        $low = (int) floor(self::TRIM * $last);
        $high = (int) ceil((1 - self::TRIM) * $last);
        [$u0, $v0] = WorldGrid::rect($columns[$low], $rows[$low]);
        [, , $u1, $v1] = WorldGrid::rect($columns[$high], $rows[$high]);
        $padU = max(self::MIN_SIDE, $u1 - $u0) * self::PADDING;
        $padV = max(self::MIN_SIDE, $v1 - $v0) * self::PADDING;

        return [max(0.0, $u0 - $padU), max(0.0, $v0 - $padV), min(1.0, $u1 + $padU), min(1.0, $v1 + $padV)];
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
