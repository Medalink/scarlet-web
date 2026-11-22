<?php

namespace App\View\Pages;

use App\Support\Board;
use App\View\Charts;
use App\View\Format;

/**
 * What the page of a gathering and the page of a crafting profession share. Each page class returns the same keys, and
 * resources/views/pages/profession.blade.php reads these: `title` (the page title), `words` (`title`, `description` and the
 * `attempt`, `attempts`, `result`, `results` and `doing` the module views read), `facts`, `callouts`, `figures`, `note`,
 * `heads` and `modules` by module key, `layout` (rows of `key`, `span`, `class`), `hoursSummary` and `dots`, which the
 * controller turns into a map when it is not null.
 */
final class ProfessionPage
{
    /** The width a chart is drawn at when it is the only module in its row and takes the whole sheet. */
    private const int SHEET_PLOT = 1140;

    /** The modules that are one chart each. */
    private const array CHARTS = ['waits', 'points', 'levels'];

    /** The modules that hold a table, which the narrow layout tightens. */
    private const array TABLES = ['items', 'recipes', 'difficulty', 'board'];

    /**
     * The modules of a page in rows, the spans a row of two takes and the span a row of one takes, with the empty
     * modules left out. A row of three is thirds, and a chart that is alone in its row is drawn across the sheet.
     *
     * @param  list<array{0: list<string>, 1: list<string|null>, 2: string}>  $rows  the module keys of a row, its pair of spans and its single span
     * @param  array<string, array<mixed>|null>  $modules
     * @return array{modules: array<string, array<string, mixed>>, layout: list<list<array{key: string, span: string|null, class: string|null}>>}
     */
    public static function arrange(array $rows, array $modules): array
    {
        $modules = array_filter($modules, fn (?array $module): bool => $module !== null && $module !== []);
        $layout = [];
        foreach ($rows as [$keys, $pair, $single]) {
            $keys = array_values(array_intersect($keys, array_keys($modules)));
            $spans = match (count($keys)) {
                0 => [],
                1 => [$single],
                2 => $pair,
                default => [null, null, null],
            };
            if (count($keys) === 1 && in_array($keys[0], self::CHARTS, true)) {
                $modules[$keys[0]]['spec']['w'] = self::SHEET_PLOT;
            }
            if ($keys !== []) {
                $layout[] = array_map(fn (string $key, ?string $span): array => ['key' => $key, 'span' => $span, 'class' => in_array($key, self::TABLES, true) ? 'camp-mod' : null], $keys, $spans);
            }
        }

        return ['modules' => $modules, 'layout' => $layout];
    }

    /** @return array{label: string, value: string, icon: string} a figure, with a dash while the scope is under the character threshold or the number is unknown */
    public static function figure(bool $few, string $label, ?string $value, string $icon): array
    {
        return ['label' => $label, 'value' => $few ? '–' : ($value ?? '–'), 'icon' => $icon];
    }

    public static function few(string $what): string
    {
        return 'The numbers show once more characters have recorded '.$what.'.';
    }

    /** "15 seconds", "3.2 seconds": a whole number from ten seconds up. */
    public static function seconds(float $seconds): string
    {
        $text = $seconds < 10 ? Format::number($seconds, 1) : Format::number($seconds);

        return $text.($text === '1' || $text === '1.0' ? ' second' : ' seconds');
    }

    /**
     * A share with its words, "43% of catches", or null when the whole is zero. The part never passes the whole.
     */
    public static function shareOf(mixed $part, int $whole, string $suffix): ?string
    {
        $share = CampsPage::share(min((int) $part, $whole), $whole);

        return $share === null ? null : $share.' '.$suffix;
    }

    /**
     * Callouts with the title, text and share of each; one without a title or text is left out and one without a share is
     * kept without it.
     *
     * @param  list<array{0: string, 1: string|null, 2: string|null, 3: string|null}>  $callouts  key, title, text, share
     * @return list<array{key: string, title: string, text: string, share: string|null}>
     */
    public static function callouts(array $callouts): array
    {
        $kept = [];
        foreach ($callouts as [$key, $title, $text, $share]) {
            if ($title !== null && $text !== null) {
                $kept[] = ['key' => $key, 'title' => $title, 'text' => $text, 'share' => $share];
            }
        }

        return $kept;
    }

    /**
     * The week of events in UTC, Monday 00:00 first, which resources/js/local-time.js reads in the viewer's time zone.
     *
     * @param  list<array<string, mixed>>  $hours  weekday (0 is Sunday), hour and the count under `$count`
     * @return array{spec: array<string, mixed>}
     */
    public static function week(array $hours, string $count, string $attempt, string $attempts): array
    {
        $cells = array_fill(0, 168, 0.0);
        foreach ($hours as $row) {
            $cells[((int) $row['weekday'] + 6) % 7 * 24 + (int) $row['hour']] = (float) $row[$count];
        }
        $peak = StatsPage::busiest(self::asSeconds($hours, $count));

        return ['spec' => Charts::weekCells($cells, $peak === null ? null : $peak['day'] * 24 + $peak['hour'], 'hours-out', $attempt, ucfirst($attempts))];
    }

    /**
     * @param  list<array<string, mixed>>  $hours
     * @return array{0: string, 1: string}|null
     */
    public static function hoursSummary(array $hours, string $count): ?array
    {
        return StatsPage::hoursSummary(self::asSeconds($hours, $count));
    }

    /**
     * The zones with the most events, the first with its unit.
     *
     * @param  list<array<string, mixed>>  $zones
     * @return list<array<string, mixed>>|null
     */
    public static function zones(array $zones, string $count, string $result, string $results): ?array
    {
        if ($zones === []) {
            return null;
        }
        $top = max([1, ...array_column($zones, $count)]);

        return array_map(fn (array $zone, int $i): array => [
            'label' => (string) ($zone['name'] ?? 'Zone '.$zone['zone_id']),
            'value' => $i === 0 ? Format::count((int) $zone[$count], $result, $results) : Format::number((int) $zone[$count]),
            'share' => (int) $zone[$count] / $top * 100,
            'href' => '/zones/'.$zone['zone_id'],
        ], $zones, array_keys($zones));
    }

    /**
     * @param  list<array<string, mixed>>  $board
     * @return array{columns: list<array<string, mixed>>, rows: list<array{cells: list<mixed>}>}|null
     */
    public static function board(Board $ranked, array $board, string $results): ?array
    {
        if ($board === []) {
            return null;
        }

        return [
            'columns' => [['label' => '#', 'class' => 'rk'], ['label' => 'Character'], ['label' => 'Class'], ['label' => ucfirst($results), 'numeric' => true], ['label' => 'Ruleset']],
            'rows' => array_map(fn (array $row): array => ['class' => $row['mine'] ? 'me' : null, 'cells' => [
                (string) $row['rank'],
                ['text' => Format::characterName((string) $row['name']), 'href' => '/c/'.rawurlencode((string) $row['name']), 'dot' => true, 'characterClass' => (string) $row['class']],
                Format::character(null, $row['race'], $row['class']),
                $ranked->format((float) $row['value']),
                ['text' => (string) ($row['ruleset_label'] ?? ''), 'tag' => $row['hardcore'] ? 'HC' : null],
            ]], $board),
        ];
    }

    /**
     * StatsPage reads a week of played seconds; a week of events is the same shape under another key.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function asSeconds(array $rows, string $count): array
    {
        return array_map(fn (array $row): array => ['weekday' => $row['weekday'], 'hour' => $row['hour'], 'seconds' => $row[$count]], $rows);
    }
}
