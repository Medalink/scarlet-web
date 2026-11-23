<?php

namespace App\View\Pages;

use App\Support\PageScope;
use App\Support\WorldLayer;
use App\View\Format;
use Closure;

/** The data of docs/comps/world.html from the answer of App\Actions\Public\WorldMapPage. */
final class MapPage
{
    public const string CANVAS = 'map';

    public const string READOUT = 'map-out';

    public const string LOOK = 'map-look';

    public const string ZONE_PAGE = 'map-zone-page';

    /** The uiMapID of the whole world, which the World row of the zone list carries. */
    public const int WORLD_SHEET = 947;

    private const int ZONES_LISTED = 12;

    /**
     * @param  array<string, mixed>  $r
     * @param  int|null  $zone  the uiMapID of the zone the map opens on
     * @param  list<string>|null  $own  the character names of a signed-in viewer, for the switch to their own trails
     * @return array<string, mixed>
     */
    public static function data(array $r, ?int $zone = null, ?array $own = null): array
    {
        /** @var PageScope $scope */
        $scope = $r['scope'];
        /** @var WorldLayer $layer */
        $layer = $r['layer'];
        /** @var list<array{id: int, slug: string, label: string, kind: string|null}> $rulesets */
        $rulesets = $r['rulesets'];
        /** @var list<array<string, mixed>> $heat */
        $heat = $r['heat'];
        /** @var list<array<string, mixed>> $zones */
        $zones = $r['zones'];
        $slug = $scope->ruleset?->slug();
        $query = fn (?string $ruleset, WorldLayer $to, ?int $zone): string => rtrim('/map?'.http_build_query(array_filter(['ruleset' => $ruleset, 'layer' => $to === WorldLayer::Paths ? null : $to->value, 'zone' => $zone])), '?');
        $list = self::zoneList($zones, $zone, fn (?int $id): string => $query($slug, $layer, $id));
        $opened = $list['opened'];
        $ruleset = Filters::items($scope, $rulesets, $query(null, $layer, $opened), fn (array $item): string => $query($item['slug'], $layer, $opened));

        return [
            'map' => MapSpec::density($layer, $slug, $zones, $opened, ['ruleset' => $slug, 'readout' => self::READOUT, 'lookSwitch' => self::LOOK, 'sheetList' => true, 'zonePage' => self::ZONE_PAGE, 'layerLevels' => array_column(array_map(fn (WorldLayer $item): array => [$item->value, $item->levels()], WorldLayer::cases()), 1, 0)]),
            'title' => 'World map',
            'layers' => array_map(fn (WorldLayer $item): array => ['label' => $item->label(), 'on' => $item === $layer, 'data' => ['map-layer' => $item->value, 'map-for' => self::CANVAS]], WorldLayer::cases()),
            'rulesets' => count($ruleset) > 4 ? null : $ruleset,
            'rulesetPick' => count($ruleset) > 4 ? array_map(fn (array $item): array => empty($item['disabled']) ? [$item['label'], $item['href']] : [$item['label'], null, true], $ruleset) : null,
            'rulesetSelected' => array_values(array_filter($ruleset, fn (array $item): bool => $item['on']))[0]['label'] ?? 'All',
            'empty' => $heat === [] ? 'No '.self::layerWord($layer).' are recorded on the world map yet.' : null,
            'line' => Format::line('World', ', move the pointer over a zone for its totals.'),
            'zonesList' => $list['rows'],
            'whose' => MapWhose::of($own ?? [], null, false, $opened),
            'personal' => null,
            'world' => $list['world'],
            'zonePage' => $list['zonePage'],
            'layerWord' => self::layerWord($layer),
        ];
    }

    /**
     * The list of the zones with the most recorded play, busiest first, with the opened zone added when it is not among
     * them, the World row above it and the link to the opened zone's page.
     *
     * @param  list<array<string, mixed>>  $zones  the answer of App\Actions\Public\MapZones
     * @param  Closure(int|null): string  $href  the address of the map on a zone, or on the world for null
     * @return array{opened: int|null, rows: list<array<string, mixed>>, world: array<string, mixed>, zonePage: array{href: string, label: string}|null}
     */
    public static function zoneList(array $zones, ?int $zone, Closure $href): array
    {
        $played = array_values(array_filter($zones, fn (array $item): bool => $item['seconds'] !== null));
        usort($played, fn (array $a, array $b): int => $b['seconds'] <=> $a['seconds']);
        $opened = array_values(array_filter($zones, fn (array $item): bool => $item['id'] === $zone))[0] ?? null;
        $listed = array_slice($played, 0, self::ZONES_LISTED);
        if ($opened !== null && $opened['seconds'] !== null && ! in_array($opened, $listed, true)) {
            $listed[] = $opened;
        }
        $top = (float) max([0, ...array_column($played, 'seconds')]);

        return [
            'opened' => $opened === null ? null : (int) $opened['id'],
            'rows' => array_map(fn (array $item, int $i): array => [
                'label' => (string) $item['name'], 'sheet' => $item['id'], 'href' => $href((int) $item['id']), 'on' => $item['id'] === ($opened['id'] ?? null),
                'share' => $top > 0 ? (float) $item['seconds'] / $top * 100 : 0,
                'value' => $i === 0 ? Format::count(round((float) $item['seconds'] / 3600), 'hour') : Format::number(round((float) $item['seconds'] / 3600)),
            ], $listed, array_keys($listed)),
            'world' => ['sheet' => self::WORLD_SHEET, 'href' => $href(null), 'on' => $opened === null],
            'zonePage' => $opened === null || $opened['seconds'] === null ? null : ['href' => '/zones/'.$opened['id'], 'label' => $opened['name'].' zone page'],
        ];
    }

    private static function layerWord(WorldLayer $layer): string
    {
        return match ($layer) {
            WorldLayer::Paths => 'paths',
            WorldLayer::Deaths => 'deaths',
            WorldLayer::Creatures => 'creature sightings',
        };
    }
}
