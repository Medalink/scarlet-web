<?php

namespace App\View\Pages;

use App\Support\ItemQuality;
use App\View\Format;
use App\View\ItemTypes;
use Illuminate\Support\Carbon;

/** The Records strip of the character page: one tile for each fact the character holds a record in. */
final class CharacterRecords
{
    /** Tiles shown; the ones listed last drop first. */
    public const int SHOWN = 8;

    /** Tiles a strip needs to be worth a section. */
    private const int LEAST = 3;

    /**
     * @param  array<string, mixed>  $records  the answer of App\Actions\Public\Character\Records
     * @param  array<string, mixed>  $totals  the answer of App\Actions\Public\Character\SessionTotals
     * @param  array<string, mixed>  $items  the answer of App\Actions\Public\Character\ItemsFound
     * @param  list<array{id: int, name: string, kills: int}>  $killed  the answer of App\Actions\Public\Character\MostKilled
     * @return list<array{value: string, label: string, detail: string|null, href: string|null, class: string|null}>
     */
    public static function tiles(array $records, array $totals, array $items, array $killed): array
    {
        $tiles = [];
        if ($records['blow'] !== null) {
            $blow = $records['blow'];
            $tiles[] = self::tile(Format::number($blow['damage']), 'Biggest killing blow taken', ucfirst(implode(', ', array_filter([
                $blow['killer'], $blow['level'] === null ? null : 'level '.$blow['level'], $blow['zone'],
            ]))));
        }
        if ($totals['longest'] !== null) {
            $tiles[] = self::tile(Format::duration($totals['longest']['seconds']), 'Longest session', self::day($totals['longest']['at']));
        }
        if ($totals['busiest'] !== null) {
            $tiles[] = self::tile(Format::number($totals['busiest']['kills']), 'Most kills in one session', self::day($totals['busiest']['at']));
        }
        if ($records['slowest'] !== null) {
            $tiles[] = self::tile(Format::duration($records['slowest']['seconds']), 'Slowest level', 'Level '.$records['slowest']['level']);
        }
        if ($killed !== []) {
            $tiles[] = self::tile(Format::number($killed[0]['kills']), 'Most killed creature', $killed[0]['name'], '/creatures/'.$killed[0]['id']);
        }
        if ($items['gear'] !== null) {
            $best = $items['gear'];
            $quality = ItemQuality::from($best['quality']);
            $tiles[] = self::tile($best['name'], 'Best gear', $quality->label().($best['item_level'] === null ? '' : ', item level '.$best['item_level']), '/items/'.$best['item_id'], ItemTypes::quality($best['quality']), true);
        }
        if ($records['nodes'] > 0) {
            $tiles[] = self::tile(Format::number($records['nodes']), 'Herbs and ore gathered');
        }
        if ($records['fish'] > 0) {
            $tiles[] = self::tile(Format::number($records['fish']), 'Fish caught');
        }
        if ($records['afk'] > 0) {
            $tiles[] = self::tile(Format::duration($records['afk']), 'Away from keyboard');
        }
        $tiles = array_slice($tiles, 0, self::SHOWN);

        return count($tiles) < self::LEAST ? [] : $tiles;
    }

    /** @return array{value: string, label: string, detail: string|null, href: string|null, class: string|null, text: bool} */
    private static function tile(string $value, string $label, ?string $detail = null, ?string $href = null, ?string $class = null, bool $text = false): array
    {
        return ['value' => $value, 'label' => $label, 'detail' => $detail === '' ? null : $detail, 'href' => $href, 'class' => $class, 'text' => $text];
    }

    private static function day(string $at): string
    {
        return Carbon::parse($at)->utc()->format('j M Y');
    }
}
