<?php

namespace App\View\Pages;

use App\Support\WorldGrid;
use App\Support\WorldLayer;
use App\View\Format;

/** What the world map viewer (resources/js/pages/maps.js) takes in its `data-world-map` attribute. */
final class MapSpec
{
    /** The dark map art, which a page pins with `look` to ignore the look a viewer last chose on the world map. */
    public const string SURVEY = 'survey';

    /**
     * A map whose heat the viewer reads from `/public/map/density`, a level at a time as the zoom asks.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}|int|null  $fit  a zone id, or the world rectangle to open on: u0, v0, u1, v1
     * @param  list<array<string, mixed>>  $zones  the answer of App\Actions\Public\MapZones
     * @param  array<string, mixed>  $extra  readout, ruleset, lookSwitch, layer: ids and names the page controls use
     * @param  list<array{0: float, 1: float}>|null  $dots  precise positions to mark instead of heat
     * @return array<string, mixed>
     */
    public static function density(WorldLayer $layer, ?string $ruleset, array $zones, int|array|null $fit = null, array $extra = [], ?int $creature = null, ?array $dots = null): array
    {
        return [
            ...$extra,
            'fit' => $fit ?? ($dots === null ? null : WorldGrid::aroundPoints($dots)),
            'zones' => self::zones($zones),
            'layers' => [
                'heat' => $dots === null ? ['source' => ['layer' => $layer->value, 'levels' => $layer->levels(), 'ruleset' => $ruleset, 'creature' => $creature]] : null,
                'dots' => $dots ?? [],
                'marks' => [],
            ],
        ];
    }

    /**
     * A map of cells the page already has, on the finest grid, as one flat list: cell_i, cell_j and the counter of each.
     *
     * @param  list<array{0: int, 1: int, 2: int}>  $cells
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $fit
     * @param  array<string, mixed>  $extra
     * @param  list<array{kind: string, u: float, v: float}>  $marks  crosses and dots on the map, in world coordinates
     * @return array<string, mixed>
     */
    public static function cells(array $cells, ?array $fit = null, array $extra = [], array $marks = []): array
    {
        return [
            ...$extra,
            'fit' => $fit,
            'zones' => [],
            'layers' => ['heat' => ['grid' => [WorldLayer::Paths->columns()], 'cells' => $cells === [] ? [] : array_merge(...$cells)], 'marks' => $marks],
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
