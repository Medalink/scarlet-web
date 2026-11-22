<?php

namespace App\View\Pages;

use App\Models\MapRect;
use App\Support\Board;
use App\Support\CampArt;
use App\Support\CampSpell;
use App\View\Charts;
use App\View\Format;

/** The data of docs/comps/camps.html from the answer of App\Actions\Public\CampsDashboard. */
final class CampsPage
{
    /** A level's rate is drawn once characters spent this long at it; less is noise. */
    private const int MIN_RATE_SECONDS = 600;

    /** The steps of 30 seconds a visit is shorter than when it ends before Camp Benefits can arrive. */
    private const int BENEFITS_STEP = 2;

    /** The visit length ranges as the first and last step each covers, with the label and its axis tick. */
    private const array RANGES = [
        [0, 0, 'Under 30 s', '<30s'],
        [1, 1, '30 to 59 s', '30s'],
        [2, 3, '1 to 2 min', '1m'],
        [4, 9, '2 to 5 min', '2m'],
        [10, 19, '5 to 10 min', '5m'],
        [20, 40, '10 min and over', '10m+'],
    ];

    /** The most cells drawn on the camp map. */
    private const int MAP_CELLS = 600;

    /** The cells of a zone map's heat on each side. */
    private const int HEAT_CELLS = 100;

    /** The width of the longest bar in the benefits table, in rem. */
    private const float BAR_REM = 5.6;

    /**
     * @param  array<string, mixed>  $r  the answer of CampsDashboard
     * @return array<string, mixed>
     */
    public static function data(array $r, ?MapRect $rect): array
    {
        $t = StatsPage::totals($r['totals']);
        $visits = (int) $t['visits'];
        $few = $t['characters'] === null;
        $heat = is_array($r['heat'] ?? null) ? $r['heat'] : null;
        $hours = StatsPage::rows($r['hours']);
        $buffed = is_array($r['buffed'] ?? null) ? $r['buffed'] : null;
        $camp = is_array($r['camp_benefits'] ?? null) ? $r['camp_benefits'] : [];
        $benefits = StatsPage::rows($r['benefits']);
        $byLevel = StatsPage::rows($r['by_level']);

        return [
            'callouts' => self::callouts($benefits, $t, (int) ($camp['gains'] ?? 0), $few),
            'figures' => self::figures($t, $visits, $few),
            'benefits' => self::benefits($benefits, (int) ($camp['gains'] ?? 0)),
            'twoHours' => $few ? null : self::share((int) ($camp['two_hour_gains'] ?? 0), (int) ($camp['gains'] ?? 0)),
            'setup' => $visits === 0 || $few ? null : self::setup(StatsPage::rows($r['fires']), $t, $visits),
            'lengths' => $few ? null : self::lengths(StatsPage::rows($r['lengths'])),
            'visitRate' => $few ? null : self::levelRate($byLevel, 'visits', 'visits', 'visits-out'),
            'restRate' => $few ? null : self::levelRate($byLevel, 'tent_rests', 'tent rests', 'rests-out'),
            'hours' => $hours === [] ? null : self::hours($hours),
            'hoursSummary' => self::hoursSummary($hours),
            'buffed' => $buffed === null ? null : self::buffed($buffed),
            'dots' => self::dots($heat, $rect),
            'heatName' => is_string($heat['name'] ?? null) ? $heat['name'] : null,
            'zones' => self::zones(StatsPage::rows($r['zones'])),
            'doing' => $few ? [] : self::doing($t),
            'ends' => $few || $visits === 0 ? [] : self::ends($t, $visits),
            'board' => self::board(StatsPage::rows($r['board'])),
        ];
    }

    /** "4m 10s": a visit is measured to the second. */
    public static function visitLength(int|float $seconds): string
    {
        $whole = (int) round($seconds);

        return $whole < 60 ? $whole.'s' : intdiv($whole, 60).'m'.($whole % 60 === 0 ? '' : ' '.str_pad((string) ($whole % 60), 2, '0', STR_PAD_LEFT).'s');
    }

    /**
     * @param  list<array<string, mixed>>  $benefits
     * @param  array<string, int|float|null>  $t
     * @return list<array{key: string, title: string, text: string, share: string|null}>
     */
    private static function callouts(array $benefits, array $t, int $gains, bool $few): array
    {
        $visits = (int) $t['visits'];
        $chair = array_column($benefits, null, 'spell_id')[CampSpell::CampChair->value] ?? null;
        $withChair = ($chair['characters'] ?? null) === null ? null : self::share((int) $chair['gains'], $gains);
        $shared = fn (?string $share, string $what): ?string => $few || $share === null ? null : $share.' '.$what;

        return [
            ['key' => 'fire', 'title' => 'Campfire', 'text' => 'Sit 60 seconds for Camp Benefits', 'share' => $shared(self::share((int) $t['sat'], $visits), 'of visits sit down')],
            ['key' => 'tent', 'title' => 'Camp Tent', 'text' => 'Rest 30 seconds for rested experience, once an hour', 'share' => $shared(self::share((int) $t['tented'], $visits), 'of visits use a tent')],
            ['key' => 'chair', 'title' => 'Camp Chair', 'text' => 'Adds 2% critical strike to the benefits', 'share' => $shared($withChair, 'of Camp Benefits come with a chair')],
        ];
    }

    /**
     * @param  array<string, int|float|null>  $t
     * @return list<array{label: string, value: string, icon: string|null}>
     */
    private static function figures(array $t, int $visits, bool $few): array
    {
        $dash = '–';
        $played = (int) $t['played_seconds'];
        $row = fn (string $label, ?string $value, ?string $icon = null): array => ['label' => $label, 'value' => $few ? $dash : ($value ?? $dash), 'icon' => $icon];

        return [
            $row('Visits', Format::number($visits), 'pin'),
            $row('Characters', $t['characters'] === null ? null : Format::number((int) $t['characters']), 'characters'),
            $row('Hours by the fire', Format::hours((int) $t['seconds'], false), 'hours'),
            $row('Median visit', $t['median_seconds'] === null ? null : self::visitLength((float) $t['median_seconds']), 'clock'),
            $row('Visits a played hour', $played > 0 ? Format::number($visits / ($played / 3600), 2) : null, 'level'),
            $row('Earn Camp Benefits', self::share((int) $t['earned'], $visits), 'shield'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $benefits
     * @return array{rows: list<array<string, mixed>>, note: string|null}
     */
    private static function benefits(array $benefits, int $total): array
    {
        $top = max([0.0, ...array_map(fn (array $b): float => $total > 0 ? (int) $b['gains'] / $total : 0.0, $benefits)]);

        return [
            'rows' => $total === 0 ? [] : array_map(function (array $b) use ($total, $top): array {
                $few = $b['characters'] === null;
                $gains = (int) $b['gains'];

                return [
                    'icon' => CampArt::icon((int) $b['spell_id']),
                    'label' => (string) $b['label'],
                    'effect' => (string) $b['effect'],
                    'share' => $few ? '–' : self::share($gains, $total),
                    'bar' => $few || $top <= 0 ? null : round($gains / $total / $top * self::BAR_REM, 2).'rem',
                    'lost' => $few ? null : ($gains > 0 ? self::share((int) $b['lost_to_death'], $gains) : '–'),
                ];
            }, $benefits),
            'note' => $total === 0 ? 'No Camp Benefits recorded yet.' : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $fires
     * @param  array<string, int|float|null>  $t
     * @return array{fires: list<array<string, mixed>>, placed: list<array<string, mixed>>}
     */
    private static function setup(array $fires, array $t, int $visits): array
    {
        $top = max([1, ...array_column($fires, 'visits')]);
        $someHidden = array_sum(array_column($fires, 'visits')) < $visits;
        $rows = fn (array $items, callable $share, bool $zeroIsUnknown): array => array_map(fn (array $item, int $i): array => [
            'label' => $item[0], 'value' => $zeroIsUnknown && $item[1] === 0 ? '–' : (self::share($item[1], $visits) ?? '–').($i === 0 ? ' of visits' : ''), 'share' => $share($item[1]),
        ], $items, array_keys($items));

        return [
            'fires' => $rows(array_map(fn (array $fire): array => [(string) $fire['label'], (int) $fire['visits']], $fires), fn (int $n): float => $n / $top * 100, $someHidden),
            'placed' => $rows([['Camp Tent', (int) $t['placed_tent']], ['Camp Chair', (int) $t['placed_chair']]], fn (int $n): float => $n / $visits * 100, false),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lengths  step, visits: the shares are of the visits in the steps shown
     * @return array{spec: array<string, mixed>, note: string|null}|null
     */
    private static function lengths(array $lengths): ?array
    {
        $byStep = array_column($lengths, 'visits', 'step');
        $visits = (int) array_sum($byStep);
        if ($visits === 0) {
            return null;
        }
        $counts = array_map(fn (array $range): int => (int) array_sum(array_map(fn (int $step): int => (int) ($byStep[$step] ?? 0), range($range[0], $range[1]))), self::RANGES);
        $data = $say = [];
        foreach (self::RANGES as $i => $range) {
            $data[] = [$i, $counts[$i] / $visits * 100];
            $say[] = Format::line($range[2], ': '.Format::count($counts[$i], 'visit').', '.Format::percent($counts[$i] / $visits, 1));
        }
        $axis = Axis::nice(max(array_column($data, 1)), 3);
        $short = (int) array_sum(array_map(fn (int $step): int => (int) ($byStep[$step] ?? 0), range(0, self::BENEFITS_STEP - 1)));

        return [
            'spec' => Charts::bars(
                ['w' => 400, 'h' => 250, 'x0' => -0.5, 'x1' => count(self::RANGES) - 0.5, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks'], '%'), 'xt' => array_map(fn (array $range, int $i): array => [$i, $range[3]], self::RANGES, array_keys(self::RANGES))],
                $data, 't', $say, 1, 'len-out',
            ),
            'note' => Format::percent($short / $visits).' of visits end before the 60 seconds Camp Benefits need.',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows  level, visits, tent_rests, played_seconds
     * @return array{spec: array<string, mixed>, note: string}|null
     */
    private static function levelRate(array $rows, string $key, string $noun, string $out): ?array
    {
        $rates = [];
        foreach ($rows as $row) {
            if ((int) $row['played_seconds'] >= self::MIN_RATE_SECONDS) {
                $rates[] = ['level' => (int) $row['level'], 'rate' => (int) $row[$key] / ((int) $row['played_seconds'] / 3600), 'seconds' => (int) $row['played_seconds']];
            }
        }
        $best = $rates === [] ? null : array_reduce($rates, fn (?array $a, array $b): array => $a === null || $b['rate'] > $a['rate'] ? $b : $a);
        if (count($rates) < 2 || $best === null || $best['rate'] <= 0) {
            return null;
        }
        $top = max(2, ...array_column($rates, 'level'));
        $pts = $say = [];
        foreach ($rates as $rate) {
            $pts[] = [$rate['level'], $rate['rate']];
            $say[] = Format::line('Level '.$rate['level'], ': '.Format::number($rate['rate'], 2).' '.$noun.' a played hour, from '.Format::hours($rate['seconds'], false).' of play');
        }
        $axis = Axis::nice(max(array_column($pts, 1)), 3);

        return [
            'spec' => Charts::line(['w' => 380, 'h' => 250, 'x0' => 1, 'x1' => $top, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks']), 'xt' => Charts::ticks(Axis::spread(1, $top, 3))], [['cls' => 'a', 'pts' => $pts]], $say, count($pts) - 1, $out),
            'note' => 'Most: level '.$best['level'].', at '.Format::number($best['rate'], 2).' '.$noun.' a played hour.',
        ];
    }

    /**
     * The week of visits in UTC, Monday 00:00 first, which resources/js/local-time.js reads in the viewer's time zone.
     *
     * @param  list<array<string, mixed>>  $rows  weekday (0 is Sunday), hour, visits
     * @return array<string, mixed>
     */
    private static function hours(array $rows): array
    {
        $cells = array_fill(0, 168, 0.0);
        foreach ($rows as $row) {
            $cells[((int) $row['weekday'] + 6) % 7 * 24 + (int) $row['hour']] = (float) $row['visits'];
        }
        $peak = StatsPage::busiest(self::asSeconds($rows));

        return Charts::weekCells($cells, $peak === null ? null : $peak['day'] * 24 + $peak['hour'], 'hours-out', 'visit', 'Camp visits');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: string, 1: string}|null
     */
    private static function hoursSummary(array $rows): ?array
    {
        return StatsPage::hoursSummary(self::asSeconds($rows));
    }

    /**
     * StatsPage reads a week of played seconds; a week of visits is the same shape under another key.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function asSeconds(array $rows): array
    {
        return array_map(fn (array $row): array => ['weekday' => $row['weekday'], 'hour' => $row['hour'], 'seconds' => $row['visits']], $rows);
    }

    /**
     * @param  array<string, mixed>  $b
     * @return list<array{label: string, value: string, tone: string|null}>
     */
    private static function buffed(array $b): array
    {
        $change = function (mixed $actual, mixed $expected): ?array {
            if ($actual === null || $expected === null || (float) $expected <= 0) {
                return null;
            }
            $delta = (float) $actual / (float) $expected - 1;
            if (abs($delta) < 0.005) {
                return ['No difference', null];
            }

            return [($delta >= 0 ? '+' : '−').Format::percent(abs($delta)), $delta >= 0 ? 'up' : 'down'];
        };
        $row = fn (string $label, ?array $change): array => ['label' => $label, 'value' => $change[0] ?? '–', 'tone' => $change[1] ?? null];

        return [
            $row('Experience an hour', $change($b['xp'], $b['expected_xp'])),
            $row('Deaths an hour', $change($b['deaths'], $b['expected_deaths'])),
        ];
    }

    /**
     * One dot at the middle of each of the busiest cells of the zone map, in world coordinates.
     *
     * @param  array<string, mixed>|null  $heat
     * @return list<array{0: float, 1: float}>|null
     */
    public static function dots(?array $heat, ?MapRect $rect): ?array
    {
        if ($heat === null || $rect === null || $heat['cells'] === []) {
            return null;
        }
        $cells = $heat['cells'];
        usort($cells, fn (array $a, array $b): int => (int) $b['visits'] <=> (int) $a['visits']);

        return array_map(fn (array $cell): array => [
            round($rect->u0 + (((int) $cell['cell_x'] + 0.5) / self::HEAT_CELLS) * ($rect->u1 - $rect->u0), 5),
            round($rect->v0 + (((int) $cell['cell_y'] + 0.5) / self::HEAT_CELLS) * ($rect->v1 - $rect->v0), 5),
        ], array_slice($cells, 0, self::MAP_CELLS));
    }

    /**
     * @param  list<array<string, mixed>>  $zones
     * @return list<array<string, mixed>>
     */
    private static function zones(array $zones): array
    {
        $top = max([1, ...array_column($zones, 'visits')]);

        return array_map(fn (array $zone, int $i): array => [
            'label' => (string) ($zone['name'] ?? 'Zone '.$zone['zone_id']),
            'value' => $zone['characters'] === null ? Format::TOO_FEW : ($i === 0 ? Format::count((int) $zone['visits'], 'visit') : Format::number((int) $zone['visits'])),
            'share' => (int) $zone['visits'] / $top * 100,
            'href' => '/zones/'.$zone['zone_id'],
        ], $zones, array_keys($zones));
    }

    /**
     * @param  array<string, int|float|null>  $t
     * @return list<array{label: string, value: string, share: float}>
     */
    private static function doing(array $t): array
    {
        $rows = [];
        if ((int) $t['afk_known_seconds'] > 0) {
            $rows[] = ['Away from the keyboard', (int) $t['afk_seconds'], (int) $t['afk_known_seconds'], ' of camp time'];
        }
        if ((int) $t['crafts_known'] > 0) {
            $rows[] = ['Crafting', (int) $t['crafted'], (int) $t['crafts_known'], ' of visits'];
        }

        return array_map(fn (array $row, int $i): array => [
            'label' => $row[0], 'value' => Format::percent($row[1] / $row[2]).($i === 0 ? $row[3] : ''), 'share' => $row[1] / $row[2] * 100,
        ], $rows, array_keys($rows));
    }

    /**
     * @param  array<string, int|float|null>  $t
     * @return list<array{label: string, value: string, share: float}>
     */
    private static function ends(array $t, int $visits): array
    {
        $out = (int) $t['logged_out'];
        $died = (int) $t['died_after'];
        $rows = [['Walks away', max(0, $visits - $out - $died)], ['Logs out by the fire', $out], ['Dies within five minutes', $died]];

        return array_map(fn (array $row): array => ['label' => $row[0], 'value' => Format::percent($row[1] / $visits), 'share' => $row[1] / $visits * 100], $rows);
    }

    /**
     * @param  list<array<string, mixed>>  $board
     * @return list<array{cells: list<mixed>}>
     */
    private static function board(array $board): array
    {
        return array_map(fn (array $row): array => ['class' => $row['mine'] ? 'me' : null, 'cells' => [
            (string) $row['rank'],
            ['text' => Format::characterName((string) $row['name']), 'href' => '/c/'.rawurlencode((string) $row['name']), 'dot' => true, 'characterClass' => (string) $row['class']],
            Format::character(null, $row['race'], $row['class']),
            Board::MostTimeByTheFire->format((float) $row['value']),
            ['text' => (string) ($row['ruleset_label'] ?? ''), 'tag' => $row['hardcore'] ? 'HC' : null],
        ]], $board);
    }

    public static function share(int|float $part, int|float $whole): ?string
    {
        if ($whole <= 0) {
            return null;
        }
        $share = $part / $whole;

        return Format::percent($share, $share > 0 && $share < 0.1 ? 1 : 0);
    }
}
