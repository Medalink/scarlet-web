<?php

namespace App\View\Pages;

use App\Support\HeatLevels;
use App\View\Format;

/** What the world map viewer (resources/js/pages/maps.js) takes in its `data-world-map` attribute. */
final class MapSpec
{
    /**
     * @param  list<array<string, mixed>>  $cells  heat rows: cell_i, cell_j, value
     * @param  list<array<string, mixed>>  $zones  the answer of App\Actions\Public\MapZones
     * @param  array<string, mixed>  $extra  readout, ruleset, lookSwitch, layer: ids and names the page controls use
     * @return array<string, mixed>
     */
    public static function build(array $cells, array $zones, ?int $fit = null, array $extra = []): array
    {
        return [
            ...$extra,
            'fit' => $fit,
            'zones' => self::zones($zones),
            'layers' => ['heat' => ['cells' => HeatLevels::assign($cells)]],
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

        return ', under the pointer: '.Format::number($characters).($characters === 1 ? ' character, ' : ' characters, ')
            .Format::number(round((float) $zone['seconds'] / 3600)).' hours, '.Format::number((int) $zone['deaths']).((int) $zone['deaths'] === 1 ? ' death.' : ' deaths.');
    }
}
