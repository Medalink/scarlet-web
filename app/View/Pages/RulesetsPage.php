<?php

namespace App\View\Pages;

use App\Models\Ruleset;
use App\View\Charts;
use App\View\Format;

/** The data of docs/comps/rulesets.html from the answer of App\Actions\Public\RulesetComparison. */
final class RulesetsPage
{
    /** The comparison chart draws the busiest rulesets, in the order of these chart classes. */
    private const array LINES = ['a' => 'var(--accent)', 'b' => 'var(--fg)', 'c' => 'var(--s-dungeon)'];

    /**
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    public static function data(array $r): array
    {
        /** @var list<array{id: int, slug: string, label: string, hardcore: bool, war_mode: string}> $rulesets */
        $rulesets = $r['rulesets'];
        /** @var list<array<string, mixed>> $rows */
        $rows = $r['rows'];
        /** @var array<int, list<array{level: int, seconds: float, characters: int}>> $curves */
        $curves = $r['curves'];
        $byId = array_column($rulesets, null, 'id');

        $unrecorded = fn (array $row): bool => ($byId[$row['ruleset_id']]['slug'] ?? '') === Ruleset::UNKNOWN_SLUG;
        usort($rows, fn (array $a, array $b): int => [$unrecorded($a), (int) ($b['characters'] ?? 0)] <=> [$unrecorded($b), (int) ($a['characters'] ?? 0)]);

        return [
            'unrecorded' => array_any($rows, $unrecorded),
            'rows' => array_map(fn (array $row): array => self::row($row, $byId[$row['ruleset_id']]), $rows),
            'compare' => self::compare($rows, $byId, $curves),
            'survival' => self::survival($rulesets, $curves),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{id: int, slug: string, label: string, hardcore: bool}  $ruleset
     * @return array{cells: list<mixed>}
     */
    private static function row(array $row, array $ruleset): array
    {
        $name = ['text' => $ruleset['label'], 'href' => '/rulesets/'.$ruleset['slug'], 'tag' => $ruleset['hardcore'] ? 'HC' : null];
        if ($row['characters'] === null) {
            return ['cells' => [$name, Format::TOO_FEW, '–', '–', '–', '–', '–']];
        }
        $characters = (int) $row['characters'];
        $hours = (float) $row['seconds'] / 3600;

        return ['cells' => [
            $name,
            Format::number($characters),
            Format::number(round($hours)),
            $row['median_to_target'] === null ? 'None reached' : Format::duration((float) $row['median_to_target']),
            $hours > 0 ? Format::number((int) $row['deaths'] / $hours, 2) : '–',
            Format::percent((int) $row['reached'] / $characters),
            (int) $row['xp'] > 0 ? Format::percent((int) $row['quest_xp'] / (int) $row['xp']) : '–',
        ]];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, array{id: int, slug: string, label: string}>  $byId
     * @param  array<int, list<array{level: int, seconds: float, characters: int}>>  $curves
     * @return array{spec: array<string, mixed>, key: list<array<string, string>>}|null
     */
    private static function compare(array $rows, array $byId, array $curves): ?array
    {
        $series = $key = [];
        $classes = array_keys(self::LINES);
        foreach ($rows as $row) {
            $curve = $curves[$row['ruleset_id']] ?? [];
            if (count($curve) < 2 || count($series) === count($classes)) {
                continue;
            }
            $class = $classes[count($series)];
            $series[] = ['cls' => $class, 'pts' => array_map(fn (array $p): array => [$p['level'], $p['seconds'] / 3600], $curve)];
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
     * Of the hardcore characters, the share that reached each level: a hardcore character that dies is done.
     *
     * @param  list<array{id: int, hardcore: bool}>  $rulesets
     * @param  array<int, list<array{level: int, seconds: float, characters: int}>>  $curves
     * @return array<string, mixed>|null
     */
    private static function survival(array $rulesets, array $curves): ?array
    {
        $byLevel = [];
        foreach ($rulesets as $ruleset) {
            foreach ($ruleset['hardcore'] ? ($curves[$ruleset['id']] ?? []) : [] as $point) {
                $byLevel[$point['level']] = ($byLevel[$point['level']] ?? 0) + $point['characters'];
            }
        }
        ksort($byLevel);
        if (count($byLevel) < 2) {
            return null;
        }
        $start = (float) reset($byLevel);
        $pts = $say = [];
        foreach ($byLevel as $level => $count) {
            $pts[] = [$level, min(100, $count / $start * 100)];
            $say[] = Format::line('Level '.$level, ': '.Format::percent(min(1, $count / $start)).' of hardcore characters reached it');
        }
        $first = (int) array_key_first($byLevel);
        $last = (int) array_key_last($byLevel);

        return Charts::line(['w' => 380, 'h' => 250, 'x0' => $first, 'x1' => $last, 'y1' => 100, 'yt' => Charts::ticks([0, 50, 100], '%'), 'xt' => Charts::ticks(Axis::spread($first, $last, 3))], [['cls' => 'a', 'pts' => $pts]], $say, count($pts) - 1);
    }
}
