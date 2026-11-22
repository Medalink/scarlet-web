<?php

namespace App\View\Pages;

use App\Support\RateEstimate;
use App\Support\WorldLayer;
use App\View\Format;

/** The By zone, Where it was looted and What it holds modules of an item page (docs/comps/item.html, container.html) from the answer of App\Actions\Public\ItemLoot. */
final class ItemLootModules
{
    /**
     * @param  array<string, mixed>  $r  the answer of ItemLoot
     * @return array{zones: list<array{cells: list<array<string, mixed>|string>}>, hasMap: bool, map: array<string, mixed>, holds: list<array{cells: list<array<string, mixed>|string>}>}
     */
    public static function from(array $r): array
    {
        /** @var list<array{id: int, name: string|null, dropped: int, estimate: RateEstimate}> $zones */
        $zones = $r['zones'];
        /** @var array{rows: list<array<string, mixed>>}|null $holds */
        $holds = $r['holds'];

        return [
            'zones' => array_map(fn (array $zone): array => ['cells' => [
                ['text' => $zone['name'] ?? 'Zone '.$zone['id'], 'href' => '/zones/'.$zone['id']],
                Format::number($zone['dropped']),
                $zone['estimate']->stated() ? ['text' => number_format($zone['estimate']->rate * 100, 1), 'note' => $zone['estimate']->range()] : $zone['estimate']->counts(),
            ]], $zones),
            'hasMap' => $r['points'] !== null,
            'map' => MapSpec::density(WorldLayer::Creatures, null, $r['map_zones'], null, dots: $r['points']),
            'holds' => $holds === null ? [] : LootRows::gives($holds['rows']),
        ];
    }
}
