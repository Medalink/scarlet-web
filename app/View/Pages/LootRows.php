<?php

namespace App\View\Pages;

use App\Support\ItemIcons;
use App\Support\ItemValueSource;
use App\Support\LootSourceKind;
use App\Support\LootView;
use App\Support\RateEstimate;
use App\Support\RateFlag;
use App\View\Format;
use App\View\ItemTypes;
use Illuminate\Support\HtmlString;

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
            $source = ['text' => $kind->title($row['name'], $id), 'href' => $kind->page($id)];
            if (! $full) {
                $common = $top === null ? '–' : self::rated('/items/'.$top['item_id'], $top['item'] ?? 'Item '.$top['item_id'], $top['estimate']);

                return ['cells' => [$source, Format::number($row['windows']), $common]];
            }

            return ['cells' => [
                $source,
                $zone === null ? '–' : self::zone($zone),
                self::levels($row['level_min'], $row['level_max']),
                Format::number($row['windows']),
                Format::number($row['items']),
                $top === null ? '–' : ['text' => $top['item'] ?? 'Item '.$top['item_id'], 'href' => '/items/'.$top['item_id']],
                $top === null ? '–' : ['rate' => $top['estimate'], 'counts' => true],
                Format::number($row['characters']),
            ]];
        }, $rows);
    }

    /**
     * Drops the columns of `$only` whose every cell is a dash.
     *
     * @param  list<array<string, mixed>>  $columns
     * @param  list<array{cells: list<array<string, mixed>|string>}>  $rows
     * @param  list<int>  $only  the column positions that may be dropped
     * @return array{columns: list<array<string, mixed>>, rows: list<array{cells: list<array<string, mixed>|string>}>}
     */
    public static function withoutEmpty(array $columns, array $rows, array $only): array
    {
        $drop = array_filter($only, fn (int $i): bool => $rows !== [] && array_reduce($rows, fn (bool $all, array $row): bool => $all && ($row['cells'][$i] ?? '–') === '–', true));

        return [
            'columns' => array_values(array_diff_key($columns, array_flip($drop))),
            'rows' => array_map(fn (array $row): array => ['cells' => array_values(array_diff_key($row['cells'], array_flip($drop)))], $rows),
        ];
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
            $zone = $row['zone'];
            if (! $full) {
                $source = $best === null || $kind === null ? '–' : self::rated($kind->page($best['source_id']), $kind->title($best['source'], $best['source_id']), $best['estimate']);

                return ['cells' => [$item, Format::number($row['dropped']), $source]];
            }
            $named = $kind === null ? null : ($best['source'] === null && $zone !== null
                ? $kind->kindIn($zone['name'] ?? 'Zone '.$zone['id'])
                : $kind->title($best['source'], $best['source_id']));

            return ['cells' => [
                $item, Format::number($row['dropped']), Format::number($row['sources']),
                $named === null ? '–' : ['text' => $named, 'href' => $kind->page($best['source_id'])],
                $best === null ? '–' : ['rate' => $best['estimate'], 'counts' => true],
                $zone === null ? '–' : self::zone($zone),
            ]];
        }, $rows);
    }

    /**
     * One row for each item a source gave, as the drop tables of the object page and of a container item list them: the item,
     * its rate, the range, the counts and the quantity per drop.
     *
     * @param  list<array<string, mixed>>  $rows  the rows of AppActionsRollupsSourceDrops
     * @return list<array{cells: list<array<string, mixed>|string>}>
     */
    public static function gives(array $rows): array
    {
        return array_map(fn (array $row): array => ['cells' => [
            ['text' => (string) ($row['item'] ?? 'Item '.$row['item_id']), 'href' => '/items/'.$row['item_id'], 'tag' => in_array(RateFlag::QuestItem, $row['estimate']->flags, true) ? RateFlag::QuestItem->label() : null],
            ['rate' => $row['estimate']],
            $row['estimate']->stated() ? $row['estimate']->range() : '–',
            $row['estimate']->counts(),
            Format::number($row['per_drop'], 1),
            self::value($row['value'] ?? null),
        ]], $rows);
    }

    /**
     * The value cell of a drop table row priced by PriceLootTable: one item's value, with `vendor` after a vendor price.
     *
     * @param  array{copper: int, from: string}|null  $value
     * @return array<string, mixed>|string
     */
    public static function value(?array $value): array|string
    {
        if ($value === null) {
            return '–';
        }

        return ['text' => Format::money($value['copper']), 'note' => $value['from'] === ItemValueSource::Vendor->value ? 'vendor' : null];
    }

    /**
     * What a priced drop table's items are worth a window, for x-loot-worth; null while the table states no worth.
     *
     * @param  array{windows: int, value: array{per_window: int|null, unpriced: int, scanned_at: string|null}}  $table
     * @return array{per: HtmlString, windows: int, unpriced: int, scanned_at: string|null}|null
     */
    public static function worth(array $table): ?array
    {
        $value = $table['value'];

        return $value['per_window'] === null ? null : ['per' => Format::money($value['per_window']), 'windows' => $table['windows'], 'unpriced' => $value['unpriced'], 'scanned_at' => $value['scanned_at']];
    }

    /**
     * The figure of a priced drop table's worth a window, `–` while it states none.
     *
     * @param  array{value: array{per_window: int|null}}  $table
     * @return array{label: string, value: HtmlString|string, icon: string}
     */
    public static function worthFigure(array $table, string $noun): array
    {
        $per = $table['value']['per_window'];

        return ['label' => 'Worth '.$noun, 'value' => $per === null ? '–' : Format::money($per), 'icon' => 'gold'];
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
