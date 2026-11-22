<?php

namespace App\View\Pages;

use App\Support\PageScope;
use App\Support\WorldLayer;
use App\View\Format;

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
     * @return array<string, mixed>
     */
    public static function data(array $r, ?int $zone = null): array
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
        $played = array_values(array_filter($zones, fn (array $item): bool => $item['seconds'] !== null));
        usort($played, fn (array $a, array $b): int => $b['seconds'] <=> $a['seconds']);
        $opened = array_values(array_filter($zones, fn (array $item): bool => $item['id'] === $zone))[0] ?? null;
        $listed = array_slice($played, 0, self::ZONES_LISTED);
        if ($opened !== null && $opened['seconds'] !== null && ! in_array($opened, $listed, true)) {
            $listed[] = $opened;
        }

        $query = fn (?string $ruleset, WorldLayer $to, ?int $zone): string => rtrim('/map?'.http_build_query(array_filter(['ruleset' => $ruleset, 'layer' => $to === WorldLayer::Paths ? null : $to->value, 'zone' => $zone])), '?');
        $ruleset = Filters::items($scope, $rulesets, $query(null, $layer, $opened['id'] ?? null), fn (array $item): string => $query($item['slug'], $layer, $opened['id'] ?? null));
        $top = (float) max([0, ...array_column($played, 'seconds')]);

        return [
            'map' => MapSpec::density($layer, $slug, $zones, $opened['id'] ?? null, ['ruleset' => $slug, 'readout' => self::READOUT, 'lookSwitch' => self::LOOK, 'sheetList' => true, 'zonePage' => self::ZONE_PAGE, 'layerLevels' => array_column(array_map(fn (WorldLayer $item): array => [$item->value, $item->levels()], WorldLayer::cases()), 1, 0)]),
            'layers' => array_map(fn (WorldLayer $item): array => ['label' => $item->label(), 'on' => $item === $layer, 'data' => ['map-layer' => $item->value, 'map-for' => self::CANVAS]], WorldLayer::cases()),
            'rulesets' => count($ruleset) > 4 ? null : $ruleset,
            'rulesetPick' => count($ruleset) > 4 ? array_map(fn (array $item): array => empty($item['disabled']) ? [$item['label'], $item['href']] : [$item['label'], null, true], $ruleset) : null,
            'rulesetSelected' => array_values(array_filter($ruleset, fn (array $item): bool => $item['on']))[0]['label'] ?? 'All',
            'empty' => $heat === [],
            'line' => Format::line('World', ', move the pointer over a zone for its totals.'),
            'zonesList' => array_map(fn (array $item, int $i): array => [
                'label' => (string) $item['name'], 'sheet' => $item['id'], 'href' => $query($slug, $layer, $item['id']), 'on' => $item['id'] === ($opened['id'] ?? null),
                'share' => $top > 0 ? (float) $item['seconds'] / $top * 100 : 0,
                'value' => $i === 0 ? Format::count(round((float) $item['seconds'] / 3600), 'hour') : Format::number(round((float) $item['seconds'] / 3600)),
            ], $listed, array_keys($listed)),
            'world' => ['sheet' => self::WORLD_SHEET, 'href' => $query($slug, $layer, null), 'on' => $opened === null],
            'zonePage' => $opened === null || $opened['seconds'] === null ? null : ['href' => '/zones/'.$opened['id'], 'label' => $opened['name'].' zone page'],
            'layerWord' => match ($layer) {
                WorldLayer::Paths => 'paths',
                WorldLayer::Deaths => 'deaths',
                WorldLayer::Creatures => 'creature sightings',
            },
        ];
    }
}
