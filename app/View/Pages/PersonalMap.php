<?php

namespace App\View\Pages;

use App\View\Format;

/** The data of the world map page for one character or a viewer's own characters, from App\Actions\Public\PersonalMapPage. */
final class PersonalMap
{
    public const string TRAVEL_ALL = 'all';

    public const string TRAVEL_FOOT = 'foot';

    public const string TRAVEL_MOUNTED = 'mounted';

    /**
     * @param  array{name: string|null, fit: array{0: float, 1: float, 2: float, 3: float}|null, empty: bool, zones: list<array<string, mixed>>, own: list<string>}  $r
     * @param  int|null  $zone  the uiMapID of the zone the map opens on
     * @return array<string, mixed>
     */
    public static function data(array $r, ?int $zone = null): array
    {
        $name = $r['name'];
        $href = fn (?int $id): string => '/map?'.http_build_query(array_filter(['character' => $name, 'scope' => $name === null ? 'mine' : null, 'zone' => $id]));
        $list = MapPage::zoneList($r['zones'], $zone, $href);
        $trails = $name === null ? '/account/map/trails' : '/c/'.rawurlencode($name).'/trails';
        $whose = $name === null ? 'your characters' : Format::characterName($name);

        return [
            'map' => MapSpec::trails($trails, $r['zones'], $list['opened'] ?? $r['fit'], ['readout' => MapPage::READOUT, 'lookSwitch' => MapPage::LOOK, 'sheetList' => true, 'zonePage' => MapPage::ZONE_PAGE]),
            'title' => $name === null ? 'Your characters' : Format::characterName($name),
            'layers' => null,
            'rulesets' => null,
            'rulesetPick' => null,
            'rulesetSelected' => null,
            'empty' => $r['empty'] ? 'No paths or flights are recorded for '.$whose.' yet.' : null,
            'line' => Format::line('World', ', move the pointer over a zone for its totals.'),
            'zonesList' => $list['rows'],
            'whose' => MapWhose::of($r['own'], $name, $name === null, $list['opened']),
            'personal' => [
                'travel' => array_map(fn (array $item): array => ['label' => $item[1], 'on' => $item[0] === self::TRAVEL_ALL, 'data' => ['map-travel' => $item[0], 'map-for' => MapPage::CANVAS]], [
                    [self::TRAVEL_ALL, 'All travel'], [self::TRAVEL_FOOT, 'On foot'], [self::TRAVEL_MOUNTED, 'Mounted'],
                ]),
                'flights' => [['label' => 'Flight paths', 'on' => true, 'data' => ['map-flights' => 'on', 'map-for' => MapPage::CANVAS]]],
            ],
            'world' => $list['world'],
            'zonePage' => $list['zonePage'],
            'layerWord' => 'paths',
        ];
    }
}
