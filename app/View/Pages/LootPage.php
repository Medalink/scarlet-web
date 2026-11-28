<?php

namespace App\View\Pages;

use App\Actions\Public\LootIndex;
use App\Support\Cursor;
use App\Support\ItemQuality;
use App\Support\LootFilter;
use App\Support\LootRule;
use App\Support\LootSort;
use App\Support\LootView;
use App\View\Dates;
use App\View\Format;
use Illuminate\Support\HtmlString;

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
        /** @var list<array{rule: string, size: int|null, windows: int}> $byRule */
        $byRule = $r['rules'];
        $rules = self::byWords($byRule);
        $offset = (int) $r['offset'];
        $items = $filter->view === LootView::Items;
        $top = $zones === [] ? 1 : max(1, $zones[0]['windows']);
        $mostRule = $rules === [] ? 1 : max(1, $rules[0]['windows']);
        $known = array_filter($byRule, fn (array $rule): bool => $rule['rule'] !== LootRule::Unknown->value);
        $columns = $items
            ? [['label' => 'Item'], ['label' => 'Dropped', 'numeric' => true], ['label' => 'Sources', 'numeric' => true], ['label' => 'Best source'], ['label' => 'Drop rate', 'numeric' => true], ['label' => 'Zone']]
            : [['label' => 'Source'], ['label' => 'Zone'], ['label' => $filter->view === LootView::Creatures ? 'Level' : 'Looter level', 'numeric' => true], ['label' => $filter->view->opened(), 'numeric' => true], ['label' => 'Items seen', 'numeric' => true], ['label' => 'Most common item'], ['label' => 'Drop rate', 'numeric' => true], ['label' => 'Characters', 'numeric' => true]];
        $rows = $items ? LootRows::items($table['rows'], true) : LootRows::sources($filter->view, $table['rows'], true);
        $shown = $items ? ['columns' => $columns, 'rows' => $rows] : LootRows::withoutEmpty($columns, $rows, [2]);
        $figureRows = [
            ['label' => 'Corpses looted', 'value' => $figures['corpses']],
            ['label' => 'Skinned', 'value' => $figures['skinned']],
            ['label' => 'Sources', 'value' => $figures['sources']],
            ['label' => 'Items', 'value' => $figures['items']],
            ['label' => 'Uncommon or better', 'value' => $figures['uncommon']],
            ['label' => 'Characters', 'value' => $figures['characters']],
        ];

        return [
            'sub' => self::sub($figures, $filter, $zones),
            'figures' => array_map(fn (array $row): array => ['label' => $row['label'], 'value' => Format::number($row['value'])], array_values(array_filter($figureRows, fn (array $row): bool => $row['value'] > 0))),
            'heading' => $items ? 'Items' : 'Sources',
            'views' => self::views($filter),
            'form' => self::form($filter, $zones),
            'columns' => $shown['columns'],
            'rows' => $shown['rows'],
            'prev' => $offset > 0 ? self::url([...$filter->params(), 'cursor' => $offset - LootIndex::PAGE > 0 ? Cursor::encode($offset - LootIndex::PAGE) : null]) : null,
            'next' => $table['more'] ? self::url([...$filter->params(), 'cursor' => Cursor::encode($offset + LootIndex::PAGE)]) : null,
            'step' => LootIndex::PAGE,
            'range' => ($offset + 1).' to '.($offset + count($table['rows'])).($table['more'] ? '' : ' of '.($offset + count($table['rows']))),
            'empty' => $figures['sources'] === 0 && $filter->params() === [] ? 'No loot is recorded yet.' : 'Nothing matches these filters.',
            'fishing' => $figures['fishing'] === 0 ? null : Format::count($figures['fishing'], 'fishing catch', 'fishing catches'),
            'zones' => array_map(fn (array $zone): array => [
                'label' => $zone['name'] ?? 'Zone '.$zone['id'], 'href' => self::url(['zone' => $zone['id']]),
                'value' => Format::number($zone['windows']), 'share' => $zone['windows'] / $top * 100,
            ], array_slice($zones, 0, self::ZONES)),
            'rules' => $known === [] ? [] : array_map(fn (array $rule): array => [
                'label' => $rule['label'], 'href' => '/loot?view=groups', 'value' => Format::number($rule['windows']), 'share' => $rule['windows'] / $mostRule * 100,
            ], $rules),
        ];
    }

    /**
     * The tabs of the loot views, with the group loot page as the last.
     *
     * @return list<array{label: string, href: string, on: bool}>
     */
    public static function views(?LootFilter $filter): array
    {
        $carry = $filter === null ? [] : array_intersect_key($filter->params(), array_flip(['zone', 'q', 'quality']));

        return [...array_map(fn (LootView $view): array => [
            'label' => $view->label(), 'href' => self::url(['view' => $view === LootView::Creatures ? null : $view->value, ...$carry]), 'on' => $filter !== null && $view === $filter->view,
        ], LootView::cases()), ['label' => 'Group loot', 'href' => '/loot?view=groups', 'on' => $filter === null]];
    }

    /**
     * The rule rows merged by the words they show: a size of none, 0 and 1 all read as the rule alone.
     *
     * @param  list<array{rule: string, size: int|null, windows: int}>  $rules
     * @return list<array{label: string, windows: int}>
     */
    private static function byWords(array $rules): array
    {
        $merged = [];
        foreach ($rules as $rule) {
            $label = self::ruleWords($rule['rule'], $rule['size']);
            $merged[$label] = ($merged[$label] ?? 0) + $rule['windows'];
        }
        arsort($merged);

        return array_map(fn (string $label, int $windows): array => ['label' => $label, 'windows' => $windows], array_keys($merged), array_values($merged));
    }

    private static function ruleWords(string $rule, ?int $size): string
    {
        $words = $rule === LootRule::Unknown->value ? 'Not recorded' : LootRule::words($rule);

        return $size === null || $size < 2 || $rule === LootRule::Solo->value ? $words : $words.', '.Format::count($size, 'player');
    }

    /**
     * `1,284 corpses looted and 412 skinned by 17 characters, Nov 5 to Nov 22.`
     *
     * @param  array{corpses: int, skinned: int, sources: int, items: int, uncommon: int, characters: int, first_at: string|null, last_at: string|null, fishing: int}  $figures
     * @param  list<array{id: int, name: string|null, windows: int}>  $zones
     */
    private static function sub(array $figures, LootFilter $filter, array $zones): string|HtmlString
    {
        if ($figures['sources'] === 0 || $figures['first_at'] === null || $figures['last_at'] === null) {
            return 'No loot is recorded'.($filter->zone === null ? ' yet.' : ' in this zone yet.');
        }
        $zone = $filter->zone === null ? null : (array_column($zones, 'name', 'id')[$filter->zone] ?? 'zone '.$filter->zone);

        return Dates::join(Format::count($figures['corpses'], 'corpse').' looted'.($figures['skinned'] > 0 ? ' and '.Format::number($figures['skinned']).' skinned' : '')
            .($zone === null ? '' : ' in '.$zone).' by '.Format::count($figures['characters'], 'character').', ',
            Dates::range($figures['first_at'], $figures['last_at']), '.');
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

    /** @param  array<string, int|string|null>  $params */
    private static function url(array $params): string
    {
        $params = array_filter($params, fn (mixed $value): bool => $value !== null);

        return '/loot'.($params === [] ? '' : '?'.http_build_query($params));
    }
}
