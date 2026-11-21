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

    /**
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    public static function data(array $r): array
    {
        /** @var PageScope $scope */
        $scope = $r['scope'];
        /** @var WorldLayer $layer */
        $layer = $r['layer'];
        /** @var list<array{id: int, slug: string, label: string}> $rulesets */
        $rulesets = $r['rulesets'];
        /** @var list<array<string, mixed>> $heat */
        $heat = $r['heat'];
        /** @var list<array<string, mixed>> $zones */
        $zones = $r['zones'];
        /** @var list<array<string, mixed>> $busiest */
        $busiest = $r['busiest'];
        $slug = $scope->ruleset?->slug();

        $query = fn (?string $ruleset, WorldLayer $to): string => '/map?'.http_build_query(array_filter(['ruleset' => $ruleset, 'layer' => $to === WorldLayer::Paths ? null : $to->value]));
        $ruleset = [['label' => 'All', 'href' => $query(null, $layer), 'on' => $slug === null]];
        foreach ($rulesets as $item) {
            $ruleset[] = ['label' => $item['label'], 'href' => $query($item['slug'], $layer), 'on' => $slug === $item['slug']];
        }
        $top = (float) max([0, ...array_column($busiest, 'seconds')]);

        return [
            'map' => MapSpec::build($heat, $zones, extra: ['ruleset' => $slug, 'readout' => self::READOUT, 'lookSwitch' => self::LOOK]),
            'layers' => array_map(fn (WorldLayer $item): array => ['label' => $item->label(), 'on' => $item === $layer, 'data' => ['map-layer' => $item->value, 'map-for' => self::CANVAS]], WorldLayer::cases()),
            'rulesets' => count($ruleset) > 4 ? null : $ruleset,
            'rulesetPick' => count($ruleset) > 4 ? array_map(fn (array $item): array => [$item['label'], $item['href']], $ruleset) : null,
            'rulesetSelected' => $scope->ruleset?->label() ?? 'All',
            'empty' => $heat === [],
            'line' => Format::line('World', ', move the pointer over a zone for its totals.'),
            'zonesList' => array_map(fn (array $zone, int $i): array => [
                'label' => (string) ($zone['zone'] ?? 'Zone '.$zone['zone_id']), 'href' => '/zones/'.$zone['zone_id'],
                'share' => $top > 0 ? (float) $zone['seconds'] / $top * 100 : 0,
                'value' => Format::number(round((float) $zone['seconds'] / 3600)).($i === 0 ? ' hours' : ''),
            ], $busiest, array_keys($busiest)),
            'layerWord' => strtolower($layer->label()),
        ];
    }
}
