<?php

namespace App\View\Pages;

use App\Actions\Public\LootIndex;
use App\Support\Cursor;
use App\Support\ItemIcons;
use App\Support\ItemQuality;
use App\Support\LootFilter;
use App\Support\LootRule;
use App\Support\LootSort;
use App\Support\LootSourceKind;
use App\Support\LootView;
use App\Support\RateEstimate;
use App\View\Format;
use App\View\ItemTypes;

/** The data of the loot index (docs/comps/loot.html) from the answer of App\Actions\Public\LootIndex. */
final class LootPage
{
    /** The zones the Zones module ranks. */
    private const int ZONES = 8;

    /**
     * @param  array<string, mixed>  $r  the answer of LootIndex
     * @return array<string, mixed>
     */
    public static function from(array $r): array
    {
        /** @var LootFilter $filter */
        $filter = $r['filter'];
        /** @var array{corpses: int, skinned: int, sources: int, items: int, uncommon: int, characters: int, first_at: string|null, last_at: string|null, fishing: int} $figures */
        $figures = $r['figures'];
        /** @var list<array{id: int, name: string|null, windows: int}> $zones */
        $zones = $r['zones'];
        /** @var array{rows: list<array<string, mixed>>, more: bool} $table */
        $table = $r['table'];
        /** @var list<array{rule: string, size: int|null, windows: int}> $rules */
        $rules = $r['rules'];
        $offset = (int) $r['offset'];
        $items = $filter->view === LootView::Items;
        $top = $zones === [] ? 1 : max(1, $zones[0]['windows']);
        $mostRule = $rules === [] ? 1 : max(1, $rules[0]['windows']);

        return [
            'sub' => self::sub($figures, $filter, $zones),
            'figures' => [
                ['label' => 'Corpses looted', 'value' => Format::number($figures['corpses'])],
                ['label' => 'Skinned', 'value' => Format::number($figures['skinned'])],
                ['label' => 'Sources', 'value' => Format::number($figures['sources'])],
                ['label' => 'Items', 'value' => Format::number($figures['items'])],
                ['label' => 'Uncommon or better', 'value' => Format::number($figures['uncommon'])],
                ['label' => 'Characters', 'value' => Format::number($figures['characters'])],
            ],
            'heading' => $items ? 'Items' : 'Sources',
            'views' => array_map(fn (LootView $view): array => [
                'label' => $view->label(), 'href' => self::url(['view' => $view === LootView::Creatures ? null : $view->value, ...array_intersect_key($filter->params(), array_flip(['zone', 'q', 'quality']))]), 'on' => $view === $filter->view,
            ], LootView::cases()),
            'form' => self::form($filter, $zones),
            'columns' => $items
                ? [['label' => 'Item'], ['label' => 'Dropped', 'numeric' => true], ['label' => 'Sources', 'numeric' => true], ['label' => 'Best source'], ['label' => 'Zone']]
                : [['label' => 'Source'], ['label' => 'Zone'], ['label' => $filter->view === LootView::Creatures ? 'Level' : 'Looter level', 'numeric' => true], ['label' => $filter->view->opened(), 'numeric' => true], ['label' => 'Items seen', 'numeric' => true], ['label' => 'Most common item'], ['label' => 'Characters', 'numeric' => true]],
            'rows' => $items ? self::itemRows($table['rows']) : self::sourceRows($filter->view, $table['rows']),
            'prev' => $offset > 0 ? self::url([...$filter->params(), 'cursor' => $offset - LootIndex::PAGE > 0 ? Cursor::encode($offset - LootIndex::PAGE) : null]) : null,
            'next' => $table['more'] ? self::url([...$filter->params(), 'cursor' => Cursor::encode($offset + LootIndex::PAGE)]) : null,
            'step' => LootIndex::PAGE,
            'note' => self::note($filter->view),
            'empty' => $figures['sources'] === 0 && $filter->params() === [] ? 'No loot is recorded yet.' : 'Nothing matches these filters.',
            'fishing' => $figures['fishing'] === 0 ? null : Format::count($figures['fishing'], 'fishing catch', 'fishing catches'),
            'zones' => array_map(fn (array $zone): array => [
                'label' => $zone['name'] ?? 'Zone '.$zone['id'], 'href' => self::url(['zone' => $zone['id']]),
                'value' => Format::number($zone['windows']), 'share' => $zone['windows'] / $top * 100,
            ], array_slice($zones, 0, self::ZONES)),
            'rules' => array_map(fn (array $rule): array => [
                'label' => self::ruleWords($rule['rule'], $rule['size']), 'value' => Format::number($rule['windows']), 'share' => $rule['windows'] / $mostRule * 100,
            ], $rules),
        ];
    }

    private static function ruleWords(string $rule, ?int $size): string
    {
        $words = LootRule::words($rule);

        return $size === null || $size < 2 || $rule === LootRule::Solo->value ? $words : $words.', '.Format::count($size, 'player');
    }

    /**
     * `1,284 corpses looted and 412 skinned by 17 characters, 5 Nov to 22 Nov 2026.`
     *
     * @param  array{corpses: int, skinned: int, sources: int, items: int, uncommon: int, characters: int, first_at: string|null, last_at: string|null, fishing: int}  $figures
     * @param  list<array{id: int, name: string|null, windows: int}>  $zones
     */
    private static function sub(array $figures, LootFilter $filter, array $zones): string
    {
        if ($figures['sources'] === 0 || $figures['first_at'] === null || $figures['last_at'] === null) {
            return 'No loot is recorded'.($filter->zone === null ? ' yet.' : ' in this zone yet.');
        }
        $zone = $filter->zone === null ? null : (array_column($zones, 'name', 'id')[$filter->zone] ?? 'zone '.$filter->zone);

        return Format::count($figures['corpses'], 'corpse').' looted'.($figures['skinned'] > 0 ? ' and '.Format::number($figures['skinned']).' skinned' : '')
            .($zone === null ? '' : ' in '.$zone).' by '.Format::count($figures['characters'], 'character').', '
            .date('j M', (int) strtotime($figures['first_at'])).' to '.date('j M Y', (int) strtotime($figures['last_at'])).'.';
    }

    /**
     * @param  list<array{id: int, name: string|null, windows: int}>  $zones
     * @return array<string, mixed>
     */
    private static function form(LootFilter $filter, array $zones): array
    {
        $items = $filter->view === LootView::Items;
        $known = array_column($zones, 'id');
        $options = array_map(fn (array $zone): array => ['value' => $zone['id'], 'label' => $zone['name'] ?? 'Zone '.$zone['id']], $zones);
        if ($filter->zone !== null && ! in_array($filter->zone, $known, true)) {
            $options[] = ['value' => $filter->zone, 'label' => 'Zone '.$filter->zone];
        }

        return [
            'view' => $filter->view === LootView::Creatures ? null : $filter->view->value,
            'text' => $filter->text,
            'placeholder' => $items ? 'Item' : 'Creature, object or item',
            'zone' => $filter->zone,
            'zones' => $options,
            'levels' => ! $items,
            'min' => $filter->levelMin,
            'max' => $filter->levelMax,
            'quality' => $filter->quality,
            'qualities' => array_map(fn (ItemQuality $quality): array => ['value' => $quality->value, 'label' => $quality->label().' or better'], array_filter(ItemQuality::cases(), fn (ItemQuality $quality): bool => $quality !== ItemQuality::Poor)),
            'sort' => $filter->sort->value,
            'sorts' => $items
                ? [['value' => LootSort::Windows->value, 'label' => 'Dropped'], ['value' => LootSort::Items->value, 'label' => 'Sources']]
                : [['value' => LootSort::Windows->value, 'label' => $filter->view->opened()], ['value' => LootSort::Items->value, 'label' => 'Items seen'], ['value' => LootSort::Level->value, 'label' => 'Level']],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{cells: list<array<string, mixed>|string>}>
     */
    private static function sourceRows(LootView $view, array $rows): array
    {
        $kind = $view->sourceKind() ?? LootSourceKind::Other;

        return array_map(function (array $row) use ($kind): array {
            $id = (int) $row['source_id'];
            $top = $row['top'];
            /** @var array{id: int, name: string|null}|null $zone */
            $zone = $row['zone'];

            return ['cells' => [
                ['text' => $kind->title($row['name'], $id), 'href' => $kind->page($id)],
                $zone === null ? '–' : ['text' => $zone['name'] ?? 'Zone '.$zone['id'], 'href' => '/zones/'.$zone['id']],
                self::levels($row['level_min'], $row['level_max']),
                Format::number($row['windows']),
                Format::number($row['items']),
                $top === null ? '–' : self::rated('/items/'.$top['item_id'], $top['item'] ?? 'Item '.$top['item_id'], $top['estimate']),
                Format::number($row['characters']),
            ]];
        }, $rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{cells: list<array<string, mixed>|string>}>
     */
    private static function itemRows(array $rows): array
    {
        $icons = array_filter(ItemIcons::icons(array_map(fn (array $row): int => (int) $row['item_id'], $rows)));

        return array_map(function (array $row) use ($icons): array {
            $id = (int) $row['item_id'];
            $best = $row['best'];
            $zone = $row['zone'];
            $kind = $best === null ? null : LootSourceKind::tryFrom($best['source_kind']);

            return ['cells' => [
                [...($icons === [] ? [] : ['icon' => $icons[$id] ?? null]), 'text' => $row['name'] ?? 'Item '.$id, 'href' => '/items/'.$id, 'class' => ItemTypes::quality($row['quality'])],
                Format::number($row['dropped']),
                Format::number($row['sources']),
                $best === null || $kind === null ? '–' : self::rated($kind->page($best['source_id']), $kind->title($best['source'], $best['source_id']), $best['estimate']),
                $zone === null ? '–' : ['text' => $zone['name'] ?? 'Zone '.$zone['id'], 'href' => '/zones/'.$zone['id']],
            ]];
        }, $rows);
    }

    /**
     * A link with its rate and 95% range after it, or its counts while the sample is under 10.
     *
     * @return array<string, mixed>
     */
    private static function rated(?string $href, string $text, RateEstimate $estimate): array
    {
        return ['text' => $text, 'href' => $href, 'note' => $estimate->stated() ? $estimate->percent().', '.$estimate->range() : $estimate->counts()];
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

    private static function note(LootView $view): string
    {
        return match ($view) {
            LootView::Creatures => 'Corpses looted counts each corpse once, the first time a character read it. Most common item is the item that was in the most of them, with its 95% range; under 10 corpses it shows its counts only. Skinning is counted apart. Every ruleset and every loot rule but personal loot.',
            LootView::Objects, LootView::Containers => 'Opened counts each opening once, the first time a character read it. Most common item is the item that was in the most of them, with its 95% range; under 10 openings it shows its counts only. Every ruleset and every loot rule but personal loot.',
            LootView::Items => 'Dropped counts the corpses and openings that held the item, once each, from loot, skinning, herbs, ore and containers. Best source is the source where the item has the highest low end of its 95% range, shown with its rate; under 10 corpses it shows its counts only.',
        };
    }

    /** @param  array<string, int|string|null>  $params */
    private static function url(array $params): string
    {
        $params = array_filter($params, fn (mixed $value): bool => $value !== null);

        return '/loot'.($params === [] ? '' : '?'.http_build_query($params));
    }
}
