<?php

namespace App\View\Pages;

use App\Models\MapRect;
use App\Support\Board;
use App\Support\GatherKind;
use App\Support\SceneArt;
use App\View\Charts;
use App\View\Format;

/** The data of docs/comps/fishing.html from the answer of App\Actions\Public\GatheringDashboard. */
final class GatheringPage
{
    /** The width of the longest bar in the catches table, in rem. */
    private const float BAR_REM = 5.6;

    /** The modules that are one chart each. */
    private const array CHARTS = ['waits', 'points', 'levels'];

    /** The width a chart is drawn at when it is the only one and takes the whole sheet. */
    private const int SHEET_PLOT = 1140;

    /** The modules of a row in the page's order, the spans a row of two takes and the span a row of one takes. */
    private const array ROWS = [
        [['items', 'ends'], ['w7', 'w5'], 'w12'],
        [self::CHARTS, ['w7', 'w5'], 'w12'],
        [['when', 'trip'], ['w8', null], 'w12'],
        [['where', 'zones'], ['w7', 'w5'], 'w12'],
        [['contents', 'poles', 'creatures'], ['w7', 'w5'], 'w12'],
        [['board'], [], 'w12'],
    ];

    /**
     * @param  array<string, mixed>  $r  the answer of GatheringDashboard
     * @return array<string, mixed>
     */
    public static function data(GatherKind $kind, array $r, ?MapRect $rect): array
    {
        $w = GatheringWords::of($kind);
        $t = StatsPage::totals($r['totals']);
        $few = $t['characters'] === null;
        $hours = StatsPage::rows($r['hours']);
        $heat = is_array($r['heat'] ?? null) ? $r['heat'] : null;
        $modules = $few ? [] : [
            'items' => self::items($kind, StatsPage::rows($r['items']), $t),
            'ends' => self::ends($kind, $t, StatsPage::rows($r['lures']), (int) ($r['lured'] ?? 0), $w),
            'waits' => GatheringCharts::waits(StatsPage::rows($r['waits']), $w),
            'points' => GatheringCharts::perPoint(StatsPage::rows($r['per_point']), $w),
            'levels' => ($levels = GatheringCharts::byLevel(StatsPage::rows($r['by_level']))) === null ? null : ['spec' => $levels],
            'when' => $hours === [] ? null : self::when($hours, $w),
            'trip' => self::trip($t, $w),
            'where' => CampsPage::dots($heat, $rect) === null ? null : ['name' => is_string($heat['name'] ?? null) ? $heat['name'] : null],
            'zones' => self::zones(StatsPage::rows($r['zones']), $w),
            'contents' => self::contents(StatsPage::rows($r['contents'])),
            'poles' => self::poles(StatsPage::rows($r['poles']), (int) $t['attempts']),
            'creatures' => self::creatures(StatsPage::rows($r['creatures']), (int) $t['attempts']),
            'board' => self::board($kind, StatsPage::rows($r['board']), $w),
        ];
        $modules = array_filter($modules, fn (mixed $module): bool => $module !== null && $module !== []);
        $charts = array_values(array_intersect(self::CHARTS, array_keys($modules)));
        if (count($charts) === 1) {
            $modules[$charts[0]]['spec']['w'] = self::SHEET_PLOT;
        }

        return [
            'words' => $w,
            'facts' => self::facts($t, $few, $w),
            'callouts' => GatheringCallouts::for($kind, $r),
            'figures' => self::figures($t, $few, $w),
            'modules' => $modules,
            'heads' => self::heads($w),
            'layout' => self::layout(array_keys($modules)),
            'dots' => CampsPage::dots($heat, $rect),
            'hoursSummary' => StatsPage::hoursSummary(self::asSeconds($hours)),
            'note' => $few ? 'The numbers show once more characters have recorded '.$w['attempts'].'.' : null,
        ];
    }

    /**
     * Rows of module keys with the span each takes: a row of three is thirds, of two its own pair, of one the full sheet.
     *
     * @param  list<string>  $present
     * @return list<list<array{key: string, span: string|null}>>
     */
    private static function layout(array $present): array
    {
        $rows = [];
        foreach (self::ROWS as [$keys, $pair, $single]) {
            $keys = array_values(array_intersect($keys, $present));
            $spans = match (count($keys)) {
                0 => [],
                1 => [$single],
                2 => $pair,
                default => [null, null, null],
            };
            if ($keys !== []) {
                $rows[] = array_map(fn (string $key, ?string $span): array => ['key' => $key, 'span' => $span], $keys, $spans);
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $w
     * @return array<string, string>
     */
    private static function heads(array $w): array
    {
        return [
            'items' => 'What comes up',
            'ends' => 'How a '.$w['attempt'].' ends',
            'waits' => 'How long a '.$w['wait'].' takes',
            'points' => ucfirst($w['results']).' for a skill point, by skill',
            'levels' => $w['title'].' skill, by character level',
            'when' => 'When players '.$w['doing'],
            'trip' => $w['trip'],
            'where' => 'Where players '.$w['doing'],
            'zones' => 'Zones with the most '.$w['results'],
            'contents' => 'What the clams, crates and trunks hold',
            'poles' => 'Poles in hand',
            'creatures' => 'What gets skinned',
            'board' => 'Most '.$w['results'],
        ];
    }

    /**
     * @param  array<string, int|float|null>  $t
     * @param  array<string, string>  $w
     * @return list<string>
     */
    private static function facts(array $t, bool $few, array $w): array
    {
        if ($few) {
            return [Format::TOO_FEW];
        }
        $facts = [Format::count((int) $t['attempts'], $w['attempt']), Format::count((int) $t['characters'], 'character')];
        if ($w['at'] !== '') {
            $facts[] = Format::number(round((int) $t['seconds'] / 3600)).' '.$w['at'];
        }

        return $facts;
    }

    /**
     * @param  array<string, int|float|null>  $t
     * @param  array<string, string>  $w
     * @return list<array{label: string, value: string, icon: string}>
     */
    private static function figures(array $t, bool $few, array $w): array
    {
        $dash = '–';
        $played = (int) $t['played_seconds'];
        $row = fn (string $label, ?string $value, string $icon): array => ['label' => $label, 'value' => $few ? $dash : ($value ?? $dash), 'icon' => $icon];
        $of = fn (mixed $part, mixed $whole): ?string => CampsPage::share((int) $part, (int) $whole);

        return [
            $row($w['lands'], $of($t['looted'], $t['attempts']), 'shield'),
            $row($w['median'], $t['median_seconds'] === null ? null : Format::number((float) $t['median_seconds'], 1).'s', 'clock'),
            $row(ucfirst($w['results']).' a played hour', $played > 0 ? Format::number((int) $t['looted'] / ($played / 3600), 1) : null, 'level'),
            $row($w['who'], $of($t['characters'], $t['scope_characters']), 'characters'),
            $row('Median skill', $t['median_skill'] === null ? null : Format::number((float) $t['median_skill']), 'skills'),
            $row('Reached '.GatherKind::SKILL_CAP, $t['at_cap'] === null ? null : $of($t['at_cap'], $t['characters']), 'crown'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, int|float|null>  $t
     * @return array{rows: list<array<string, mixed>>, icons: bool, note: string}|null
     */
    private static function items(GatherKind $kind, array $items, array $t): ?array
    {
        $looted = (int) $t['looted'];
        if ($items === [] || $looted === 0) {
            return null;
        }
        $top = max(array_map(fn (array $item): float => min(1.0, (int) $item['gathers'] / $looted), $items));
        $icons = array_map(fn (array $item): ?string => SceneArt::icon($kind->scene(), (int) $item['item_id']), $items);

        return [
            'icons' => array_filter($icons) !== [],
            'rows' => array_map(fn (array $item, ?string $icon): array => [
                'icon' => $icon,
                'label' => (string) ($item['name'] ?? 'Item '.$item['item_id']),
                'href' => '/items/'.$item['item_id'],
                'share' => CampsPage::share(min((int) $item['gathers'], $looted), $looted) ?? '–',
                'bar' => round(min(1.0, (int) $item['gathers'] / $looted) / $top * self::BAR_REM, 2).'rem',
                'skill' => $item['median_skill'] === null ? '–' : Format::number((float) $item['median_skill']),
                'zone' => is_array($item['zone']) ? (string) ($item['zone']['name'] ?? 'Zone '.$item['zone']['zone_id']) : null,
                'zoneHref' => is_array($item['zone']) ? '/zones/'.$item['zone']['zone_id'] : null,
            ], $items, $icons),
            'note' => Format::count((int) $t['items'], 'different item').' came up from '.GatheringWords::of($kind)['attempts'].'.',
        ];
    }

    /**
     * @param  array<string, int|float|null>  $t
     * @param  list<array<string, mixed>>  $lures
     * @param  array<string, string>  $w
     * @return array{ends: list<array<string, mixed>>, lures: list<array<string, mixed>>}|null
     */
    private static function ends(GatherKind $kind, array $t, array $lures, int $lured, array $w): ?array
    {
        $attempts = (int) $t['attempts'];
        if ($attempts === 0) {
            return null;
        }
        $row = fn (string $label, int $part, bool $first = false, ?string $href = null): array => [
            'label' => $label,
            'value' => (CampsPage::share($part, $attempts) ?? '–').($first ? ' of '.$w['attempts'] : ''),
            'share' => $part / $attempts * 100,
            'href' => $href,
        ];
        $ends = [$row($w['gave'], (int) $t['looted'], true), $row($w['none'], $attempts - (int) $t['looted']), $row('In combat', (int) $t['in_combat'])];
        if ($kind === GatherKind::Ore && (int) $t['first_strikes'] > 0) {
            $ends[] = $row('First strike on a vein', (int) $t['first_strikes']);
            $ends[] = $row('Later strike on the same vein', $attempts - (int) $t['first_strikes']);
        }
        $poled = $lures === [] ? [] : [
            $row('A lure', $lured, true),
            ...array_map(fn (array $lure): array => $row((string) ($lure['name'] ?? 'Spell '.$lure['spell_id']), (int) $lure['casts'], false, '/spells/'.$lure['spell_id']), $lures),
        ];

        return ['ends' => $ends, 'lures' => $poled];
    }

    /**
     * @param  list<array<string, mixed>>  $hours
     * @param  array<string, string>  $w
     * @return array<string, mixed>
     */
    private static function when(array $hours, array $w): array
    {
        $cells = array_fill(0, 168, 0.0);
        foreach ($hours as $row) {
            $cells[((int) $row['weekday'] + 6) % 7 * 24 + (int) $row['hour']] = (float) $row['gathers'];
        }
        $peak = StatsPage::busiest(self::asSeconds($hours));

        return ['spec' => Charts::weekCells($cells, $peak === null ? null : $peak['day'] * 24 + $peak['hour'], 'hours-out', $w['attempt'], ucfirst($w['attempts']))];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function asSeconds(array $rows): array
    {
        return array_map(fn (array $row): array => ['weekday' => $row['weekday'], 'hour' => $row['hour'], 'seconds' => $row['gathers']], $rows);
    }

    /**
     * @param  array<string, int|float|null>  $t
     * @param  array<string, string>  $w
     * @return array{length: string, attempts: string|null, trips: string, note: string}|null
     */
    private static function trip(array $t, array $w): ?array
    {
        if ($t['median_trip_seconds'] === null) {
            return null;
        }

        return [
            'length' => CampsPage::visitLength((float) $t['median_trip_seconds']),
            'attempts' => $t['median_trip_attempts'] === null ? null : Format::number((float) $t['median_trip_attempts']),
            'trips' => Format::number((int) $t['trips']),
            'note' => $w['attempts'].' no more than two minutes apart, over the last 90 days',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $zones
     * @param  array<string, string>  $w
     * @return list<array<string, mixed>>|null
     */
    private static function zones(array $zones, array $w): ?array
    {
        $top = max([1, ...array_column($zones, 'looted')]);

        return $zones === [] ? null : array_map(fn (array $zone, int $i): array => [
            'label' => (string) ($zone['name'] ?? 'Zone '.$zone['zone_id']),
            'value' => $i === 0 ? Format::count((int) $zone['looted'], $w['result'], $w['results']) : Format::number((int) $zone['looted']),
            'share' => (int) $zone['looted'] / $top * 100,
            'href' => '/zones/'.$zone['zone_id'],
        ], $zones, array_keys($zones));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>|null
     */
    private static function contents(array $rows): ?array
    {
        if ($rows === []) {
            return null;
        }
        $top = max([1, ...array_column($rows, 'opens')]);

        return array_map(fn (array $row, int $i): array => [
            'label' => (string) ($row['name'] ?? 'Item '.$row['item_id']),
            'value' => $i === 0 ? 'found '.Format::count((int) $row['opens'], 'time') : Format::number((int) $row['opens']),
            'share' => (int) $row['opens'] / $top * 100,
            'href' => '/items/'.$row['item_id'],
        ], $rows, array_keys($rows));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>|null
     */
    private static function poles(array $rows, int $casts): ?array
    {
        if ($rows === [] || $casts === 0) {
            return null;
        }

        return array_map(fn (array $row, int $i): array => [
            'label' => (string) ($row['name'] ?? 'Item '.$row['item_id']),
            'value' => (CampsPage::share(min((int) $row['casts'], $casts), $casts) ?? '–').($i === 0 ? ' of casts' : ''),
            'share' => min(100, (int) $row['casts'] / $casts * 100),
            'href' => '/items/'.$row['item_id'],
        ], $rows, array_keys($rows));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>|null
     */
    private static function creatures(array $rows, int $attempts): ?array
    {
        if ($rows === [] || $attempts === 0) {
            return null;
        }

        return array_map(fn (array $row, int $i): array => [
            'label' => (string) ($row['name'] ?? 'Creature '.$row['npc_id']),
            'value' => (CampsPage::share(min((int) $row['gathers'], $attempts), $attempts) ?? '–').($i === 0 ? ' of skins' : ''),
            'share' => min(100, (int) $row['gathers'] / $attempts * 100),
            'href' => '/creatures/'.$row['npc_id'],
        ], $rows, array_keys($rows));
    }

    /**
     * @param  list<array<string, mixed>>  $board
     * @param  array<string, string>  $w
     * @return array{columns: list<array<string, mixed>>, rows: list<array{cells: list<mixed>}>}|null
     */
    private static function board(GatherKind $kind, array $board, array $w): ?array
    {
        if ($board === []) {
            return null;
        }
        $ranked = Board::ofGathering($kind);

        return [
            'columns' => [['label' => '#', 'class' => 'rk'], ['label' => 'Character'], ['label' => 'Class'], ['label' => ucfirst($w['results']), 'numeric' => true], ['label' => 'Ruleset']],
            'rows' => array_map(fn (array $row): array => ['class' => $row['mine'] ? 'me' : null, 'cells' => [
                (string) $row['rank'],
                ['text' => Format::characterName((string) $row['name']), 'href' => '/c/'.rawurlencode((string) $row['name']), 'dot' => true, 'characterClass' => (string) $row['class']],
                Format::character(null, $row['race'], $row['class']),
                $ranked->format((float) $row['value']),
                ['text' => (string) ($row['ruleset_label'] ?? ''), 'tag' => $row['hardcore'] ? 'HC' : null],
            ]], $board),
        ];
    }
}
