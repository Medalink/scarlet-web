<?php

namespace App\View\Pages;

use App\Support\ItemIcons;
use App\Support\LootSourceKind;
use App\Support\LootView;
use App\Support\RateEstimate;
use App\View\Format;
use App\View\ItemTypes;

/** The table rows of the loot index and of the Looted here module of a zone page, from the rows of LootSources and LootItems. */
final class LootRows
{
    /**
     * One row for each creature, object or container. `$full` adds the zone, the levels, the items seen and the characters.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{cells: list<array<string, mixed>|string>}>
     */
    public static function sources(LootView $view, array $rows, bool $full): array
    {
        $kind = $view->sourceKind() ?? LootSourceKind::Other;

        return array_map(function (array $row) use ($kind, $full): array {
            $id = (int) $row['source_id'];
            $top = $row['top'];
            /** @var array{id: int, name: string|null}|null $zone */
            $zone = $row['zone'];
            $common = $top === null ? '–' : self::rated('/items/'.$top['item_id'], $top['item'] ?? 'Item '.$top['item_id'], $top['estimate']);
            $source = ['text' => $kind->title($row['name'], $id), 'href' => $kind->page($id)];
            if (! $full) {
                return ['cells' => [$source, Format::number($row['windows']), $common]];
            }

            return ['cells' => [
                $source,
                $zone === null ? '–' : self::zone($zone),
                self::levels($row['level_min'], $row['level_max']),
                Format::number($row['windows']),
                Format::number($row['items']),
                $common,
                Format::number($row['characters']),
            ]];
        }, $rows);
    }

    /**
     * One row for each item. `$full` adds the number of sources and the zone.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{cells: list<array<string, mixed>|string>}>
     */
    public static function items(array $rows, bool $full): array
    {
        $icons = array_filter(ItemIcons::icons(array_map(fn (array $row): int => (int) $row['item_id'], $rows)));

        return array_map(function (array $row) use ($icons, $full): array {
            $id = (int) $row['item_id'];
            $best = $row['best'];
            $kind = $best === null ? null : LootSourceKind::tryFrom($best['source_kind']);
            $item = [...($icons === [] ? [] : ['icon' => $icons[$id] ?? null]), 'text' => $row['name'] ?? 'Item '.$id, 'href' => '/items/'.$id, 'class' => ItemTypes::quality($row['quality'])];
            $source = $best === null || $kind === null ? '–' : self::rated($kind->page($best['source_id']), $kind->title($best['source'], $best['source_id']), $best['estimate']);
            if (! $full) {
                return ['cells' => [$item, Format::number($row['dropped']), $source]];
            }
            $zone = $row['zone'];

            return ['cells' => [$item, Format::number($row['dropped']), Format::number($row['sources']), $source, $zone === null ? '–' : self::zone($zone)]];
        }, $rows);
    }

    /**
     * A link with its rate and 95% range after it, or its counts while the sample is under 10.
     *
     * @return array<string, mixed>
     */
    public static function rated(?string $href, string $text, RateEstimate $estimate): array
    {
        return ['text' => $text, 'href' => $href, 'note' => $estimate->stated() ? $estimate->percent().', '.$estimate->range() : $estimate->counts()];
    }

    /**
     * @param  array{id: int, name: string|null}  $zone
     * @return array{text: string, href: string}
     */
    private static function zone(array $zone): array
    {
        return ['text' => $zone['name'] ?? 'Zone '.$zone['id'], 'href' => '/zones/'.$zone['id']];
    }

    private static function levels(?int $low, ?int $high): string
    {
        if ($low === null || $high === null) {
            return '–';
        }
        if ($low < 0 || $high < 0) {
            return 'Boss';
        }

        return $low === $high ? (string) $low : $low.' to '.$high;
    }
}
