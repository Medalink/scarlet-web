<?php

namespace App\View\Pages;

use App\Support\LootView;

/** The Looted here module of a zone page (docs/comps/zone.html) from the answer of App\Actions\Public\ZoneLoot. */
final class ZoneLootModule
{
    /**
     * @param  array{sources: list<array<string, mixed>>, items: list<array<string, mixed>>}  $loot
     * @return array{sources: list<array{cells: list<array<string, mixed>|string>}>, items: list<array{cells: list<array<string, mixed>|string>}>, href: string, label: string}|null null while the zone has no loot
     */
    public static function from(int $zone, string $name, array $loot): ?array
    {
        if ($loot['sources'] === [] && $loot['items'] === []) {
            return null;
        }

        return [
            'sources' => LootRows::sources(LootView::Creatures, $loot['sources'], false),
            'items' => LootRows::items($loot['items'], false),
            'href' => '/loot?zone='.$zone,
            'label' => 'All loot in '.$name,
        ];
    }
}
