<?php

namespace App\View\Pages;

use App\Models\RulesetKind;
use App\View\Charts;
use App\View\Format;

/** The data of docs/comps/rulesets.html from the answer of App\Actions\Public\RulesetComparison. */
final class RulesetsPage
{
    /** The comparison chart draws the busiest rulesets, in the order of these chart classes. */
    private const array LINES = ['a' => 'var(--accent)', 'b' => 'var(--fg)', 'c' => 'var(--s-dungeon)', 'p1' => 'var(--p1)'];

    private const int SPARSE = 5;

    /** The width of the widest bar of a table column, as on the record pages. */
    private const float BAR_REM = 5.2;

    private const float BAR_MIN_REM = 0.15;

    /**
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    public static function data(array $r): array
    {
        /** @var list<array{id: int, slug: string, label: string, kind: string|null, hardcore: bool, war_mode: string}> $rulesets */
        $rulesets = $r['rulesets'];
        /** @var list<array<string, mixed>> $rows */
        $rows = $r['rows'];
        /** @var array<int, list<array{level: int, seconds: float, characters: int}>> $curves */
        $curves = $r['curves'];
        $byId = array_column($rulesets, null, 'id');

        $unidentified = fn (array $row): bool => ($byId[$row['ruleset_id']]['kind'] ?? null) === null;
        usort($rows, fn (array $a, array $b): int => [$unidentified($a), (int) ($b['characters'] ?? 0)] <=> [$unidentified($b), (int) ($a['characters'] ?? 0)]);
        $counted = array_filter($rows, fn (array $row): bool => $row['characters'] !== null);
        $top = [
            'characters' => (float) max([0, ...array_map(fn (array $row): int => (int) $row['characters'], $counted)]),
            'hours' => (float) max([0, ...array_map(fn (array $row): float => (float) $row['seconds'] / 3600, $counted)]),
        ];
        $table = array_map(fn (array $row): array => self::row($row, $byId[$row['ruleset_id']], $top), $rows);
        if (! array_any($rulesets, fn (array $ruleset): bool => $ruleset['kind'] === RulesetKind::Hardcore->value)) {
            $table[] = self::notOut(RulesetKind::Hardcore);
        }

        return [
            'unidentified' => array_any($rows, $unidentified),
            'kinds' => array_map(fn (RulesetKind $kind): array => ['name' => $kind->label().($kind->available() || self::hasCharacters($kind, $rulesets, $counted) ? '' : ' (not out yet)'), 'summary' => $kind->summary()], RulesetKind::cases()),
            'rows' => $table,
            'compare' => self::compare($rows, $byId, $curves),
            'survival' => self::survival($r['hardcore']),
        ];
    }

    /**
     * @param  list<array{id: int, slug: string, label: string, kind: string|null, hardcore: bool, war_mode: string}>  $rulesets
     * @param  array<int, array<string, mixed>>  $counted
     */
    private static function hasCharacters(RulesetKind $kind, array $rulesets, array $counted): bool
    {
        $ids = array_column(array_filter($rulesets, fn (array $ruleset): bool => $ruleset['kind'] === $kind->value), 'id');

        return array_any($counted, fn (array $row): bool => in_array($row['ruleset_id'], $ids, true) && (int) $row['characters'] > 0);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{id: int, slug: string, label: string, hardcore: bool}  $ruleset
     * @param  array{characters: float, hours: float}  $top  the largest value of each bar column
     * @return array{cells: list<mixed>}
     */
    private static function row(array $row, array $ruleset, array $top): array
    {
        $name = ['text' => $ruleset['label'].' →', 'href' => '/stats/'.$ruleset['slug']];
        if ($row['characters'] === null) {
            return ['cells' => [$name, Format::TOO_FEW, '–', '–', '–', '–', '–']];
        }
        $characters = (int) $row['characters'];
        $hours = (float) $row['seconds'] / 3600;

        return ['cells' => [
            $name,
            ['text' => Format::number($characters), ...self::bar($characters, $top['characters'])],
            ['text' => Format::number(round($hours)), ...self::bar($hours, $top['hours'])],
            $row['median_to_target'] === null ? 'None recorded' : Format::duration((float) $row['median_to_target']),
            $hours > 0 ? Format::number((int) $row['deaths'] / $hours, 2) : '–',
            Format::percent((int) $row['reached'] / $characters).' ('.Format::number((int) $row['reached']).')',
            (int) $row['xp'] > 0 ? Format::percent((int) $row['quest_xp'] / (int) $row['xp']) : '–',
        ]];
    }

    /**
     * The bar of a table cell, scaled to the largest value of its column; no bar for a value of zero.
     *
     * @return array{bar?: string}
     */
    private static function bar(float|int $value, float $top): array
    {
        if ($value <= 0 || $top <= 0) {
            return [];
        }

        return ['bar' => max(self::BAR_MIN_REM, $value / $top * self::BAR_REM).'rem'];
    }

    /**
     * The table row of a ruleset that cannot be played yet: named, with no link and no numbers.
     *
     * @return array{class: string, cells: list<string>}
     */
    private static function notOut(RulesetKind $kind): array
    {
        return ['class' => 'off', 'cells' => [$kind->label().' (not out yet)', '–', '–', '–', '–', '–', '–']];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, array{id: int, slug: string, label: string, hardcore: bool}>  $byId
     * @param  array<int, list<array{level: int, seconds: float, characters: int}>>  $curves
     * @return array{spec: array<string, mixed>, key: list<array<string, string>>}|null
     */
    private static function compare(array $rows, array $byId, array $curves): ?array
    {
        $series = $key = [];
        $classes = array_keys(self::LINES);
        usort($rows, fn (array $a, array $b): int => ($byId[$b['ruleset_id']]['hardcore'] ?? false) <=> ($byId[$a['ruleset_id']]['hardcore'] ?? false));
        foreach ($rows as $row) {
            $curve = $curves[$row['ruleset_id']] ?? [];
            if (count($curve) < 2 || count($series) === count($classes)) {
                continue;
            }
            $class = $classes[count($series)];
            $series[] = ['cls' => $class, 'pts' => array_map(fn (array $p): array => [$p['level'], $p['seconds'] / 3600], $curve), 'dash' => array_values(array_map(fn (array $p): int => $p['level'], array_filter($curve, fn (array $p): bool => $p['characters'] < self::SPARSE)))];
            $key[] = ['label' => $byId[$row['ruleset_id']]['label'], 'class' => 'k-line', 'style' => 'background:'.self::LINES[$class]];
        }
        if ($series === []) {
            return null;
        }
        $top = max(array_map(fn (array $s): float => (float) max(array_column($s['pts'], 1)), $series));
        $end = max(array_map(fn (array $s): int => (int) max(array_column($s['pts'], 0)), $series));
        $axis = Axis::nice($top);

        return [
            'spec' => Charts::line(['x0' => 1, 'x1' => $end, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks'], 'h'), 'xt' => Charts::ticks(Axis::spread(1, $end))], $series),
            'key' => $key,
        ];
    }

    /**
     * Of the hardcore characters, the share that reached each level, by the level the game reports: a hardcore
     * character that dies is done.
     *
     * @param  list<array{level: int, characters: int}>  $reached
     * @return array<string, mixed>|null
     */
    private static function survival(array $reached): ?array
    {
        if (count($reached) < 2) {
            return null;
        }
        $start = (float) $reached[0]['characters'];
        $pts = $say = [];
        foreach ($reached as $row) {
            $pts[] = [$row['level'], min(100, $row['characters'] / $start * 100)];
            $say[] = Format::line('Level '.$row['level'], ': '.Format::percent(min(1, $row['characters'] / $start)).' of '.Format::count((int) $start, 'hardcore character').' reached it');
        }
        $first = $reached[0]['level'];
        $last = $reached[count($reached) - 1]['level'];

        return Charts::line(['w' => 380, 'h' => 250, 'x0' => $first, 'x1' => $last, 'y1' => 100, 'yt' => Charts::ticks([0, 50, 100], '%'), 'xt' => Charts::ticks(Axis::spread($first, $last, 3))], [['cls' => 'a', 'pts' => $pts]], $say, count($pts) - 1);
    }
}
