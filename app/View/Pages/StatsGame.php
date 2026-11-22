<?php

namespace App\View\Pages;

use App\Actions\Rollups\Roster;
use App\Support\KillerKind;
use App\Support\XpSource;
use App\View\Format;

/**
 * The art and game layer of the stats dashboard (docs/DESIGN.md, "Art and game layer"): the tally, the records, the
 * next round numbers, the climb and who plays, from the answer of App\Actions\Public\StatsDashboard.
 *
 * @phpstan-type Tile array{icon: string, label: string, value: string, count: int|null, unit: string|null}
 * @phpstan-type Record array{icon: string, value: string, href: string|null, label: string, detail: string, class: string|null}
 * @phpstan-type Rule array{icon: string, label: string, value: string, at: string, left: string, target: string}
 * @phpstan-type Flag array{summit: bool, title: string, x: string, y: string, top: string, pennant: string, tx: string, ty: string, count: string}
 * @phpstan-type Ascent array{viewBox: string, label: string, grid: list<array{y: string, text: string, ty: string}>, left: string, right: string, base: string, ticks: list<array{x: string, y2: string, ty: string, anchor: string, dx: string|null, text: string}>, land: string, ridge: string, beyond: array{path: string, cx: string, cy: string, tx: string, ty: string, text: string}|null, flags: list<Flag>}
 * @phpstan-type ClassRow array{class: string, name: string, value: string, share: string}
 * @phpstan-type Split array{label: string, sides: list<array{tone: string, name: string, count: int}>}
 */
final class StatsGame
{
    private const int W = 1180;

    private const int H = 330;

    private const int LEFT = 46;

    private const int RIGHT = 1150;

    private const int TOP = 44;

    private const int BASE = 282;

    private const array LEVEL_TICKS = [1, 10, 20, 30, 40, 50, 60];

    private const array DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    private const int COPPER_PER_GOLD = 10000;

    private const int RECORD_DEATHS = 2;

    /**
     * @param  array<string, mixed>  $r  the answer of StatsDashboard
     * @param  list<array<string, mixed>>  $levels  the levelling curve rows
     * @param  list<array<string, mixed>>  $reached  characters that reached each level, lowest first
     * @return array{cap: int, tally: list<Tile>, records: list<Record>, rules: list<Rule>, ascent: Ascent|null, classes: list<ClassRow>, split: Split|null}
     */
    public static function data(array $r, array $levels, array $reached, int $cap): array
    {
        $roster = is_array($r['roster'] ?? null) ? $r['roster'] : [];

        return [
            'cap' => $cap,
            'tally' => self::tally($r, $reached),
            'records' => self::records($r),
            'rules' => self::rules($r),
            'ascent' => self::ascent($levels, $reached, $cap),
            'classes' => self::classes(StatsPage::rows($roster['classes'] ?? [])),
            'split' => self::split(StatsPage::rows($roster['factions'] ?? [])),
        ];
    }

    /**
     * @param  array<string, mixed>  $r
     * @param  list<array<string, mixed>>  $reached
     * @return list<Tile>
     */
    private static function tally(array $r, array $reached): array
    {
        $totals = StatsPage::totals($r['totals']);
        $played = $totals['played_seconds'] ?? null;
        $runs = array_sum(array_map(fn (array $row): int => (int) $row['runs'], StatsPage::rows($r['dungeons'])));
        $top = $reached === [] ? null : $reached[count($reached) - 1];
        $highest = $top === null
            ? ['icon' => 'level', 'label' => 'Highest level', 'value' => '–', 'count' => null, 'unit' => 'none yet']
            : ['icon' => 'level', 'label' => 'Highest level', 'value' => (string) (int) $top['level'], 'count' => (int) $top['level'], 'unit' => Format::count((int) $top['characters'], 'character')];

        return [
            self::tile('characters', 'Characters', $totals['characters'] ?? null),
            $highest,
            self::tile('hours', 'Hours recorded', $played === null ? null : round($played / 3600)),
            self::tile('kills', 'Kills', $totals['kills'] ?? null),
            self::tile('deaths', 'Deaths', $totals['deaths'] ?? null),
            self::tile('quests', 'Quests turned in', $totals['quests'] ?? null),
            self::tile('dungeons', 'Dungeon runs', $runs),
        ];
    }

    /**
     * @return Tile
     */
    private static function tile(string $icon, string $label, int|float|null $value): array
    {
        if ($value === null) {
            return ['icon' => $icon, 'label' => $label, 'value' => '–', 'count' => null, 'unit' => Format::TOO_FEW];
        }

        return ['icon' => $icon, 'label' => $label, 'value' => Format::number($value), 'count' => (int) round($value), 'unit' => null];
    }

    /**
     * @param  array<string, mixed>  $r
     * @return list<Record>
     */
    private static function records(array $r): array
    {
        $totals = StatsPage::totals($r['totals']);
        $records = array_values(array_filter([
            self::killer(StatsPage::rows($r['killers'])),
            self::deathZone(StatsPage::rows($r['death_zones'])),
            self::dangerousLevel(StatsPage::rows($r['deaths_by_level']), StatsPage::rows($r['play_by_level'])),
            self::slowestLevel(StatsPage::rows($r['level_time'])),
            self::busiestHour(StatsPage::rows($r['weekdays'])),
            self::dungeon(StatsPage::rows($r['dungeons'])),
            self::profession(StatsPage::rows($r['professions']), $totals['characters'] ?? null),
            self::questShare(StatsPage::rows($r['xp_sources'])),
        ]));
        foreach ($records as $i => $record) {
            $records[$i]['class'] = $i === 0 ? 'rec-lead' : $record['class'];
        }

        return $records;
    }

    /**
     * @return Record
     */
    private static function record(string $icon, string $value, ?string $href, string $label, string $detail, ?string $class = null): array
    {
        return ['icon' => $icon, 'value' => $value, 'href' => $href, 'label' => $label, 'detail' => $detail, 'class' => $class];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return Record|null
     */
    private static function killer(array $rows): ?array
    {
        foreach ($rows as $row) {
            if ($row['kind'] !== KillerKind::Player->value) {
                return (int) $row['deaths'] < self::RECORD_DEATHS ? null : self::record('deaths', (string) $row['killer'], $row['npc_id'] === null ? null : '/creatures/'.$row['npc_id'], 'Deadliest creature', Format::count((int) $row['deaths'], 'death'));
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return Record|null
     */
    private static function deathZone(array $rows): ?array
    {
        $row = $rows[0] ?? null;

        return $row === null || (int) $row['deaths'] < self::RECORD_DEATHS ? null : self::record('pin', (string) ($row['zone'] ?? 'Zone '.$row['zone_id']), '/zones/'.$row['zone_id'], 'Where most characters die', Format::count((int) $row['deaths'], 'death'));
    }

    /**
     * @param  list<array<string, mixed>>  $deaths
     * @param  list<array<string, mixed>>  $played
     * @return Record|null
     */
    private static function dangerousLevel(array $deaths, array $played): ?array
    {
        $worst = StatsPage::mostDangerous(StatsPage::deathRates($deaths, $played));

        return $worst === null ? null : self::record('level', 'Level '.$worst['level'], null, 'Most dangerous level', Format::number($worst['rate'], 2).' deaths an hour');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return Record|null
     */
    private static function slowestLevel(array $rows): ?array
    {
        $slowest = StatsPage::slowest($rows);

        return $slowest === null ? null : self::record('hours', 'Level '.$slowest['level'], null, 'Slowest level', Format::duration((float) $slowest['median']).' median');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return Record|null
     */
    private static function busiestHour(array $rows): ?array
    {
        $peak = StatsPage::busiest($rows);

        return $peak === null ? null : self::record('clock', self::DAYS[$peak['day']].' '.str_pad((string) $peak['hour'], 2, '0', STR_PAD_LEFT).':00', null, 'Busiest hour, UTC', Format::duration($peak['seconds']).' played');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return Record|null
     */
    private static function dungeon(array $rows): ?array
    {
        $row = $rows[0] ?? null;
        if ($row === null || (int) $row['runs'] < 1) {
            return null;
        }
        $runs = (int) $row['runs'];

        return self::record(
            'dungeons', (string) ($row['name'] ?? 'Instance '.$row['instance_id']), '/zones/-'.$row['instance_id'], 'Most run dungeon',
            Format::count($runs, 'run').', '.Format::duration((float) $row['median']).' median, '.Format::number((int) $row['bosses'] / $runs, 1).' bosses a run',
            'c2',
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return Record|null
     */
    private static function profession(array $rows, int|float|null $characters): ?array
    {
        $row = StatsPage::trainedSkills($rows)[0] ?? null;
        if ($row === null || $characters === null || $characters < 1) {
            return null;
        }

        return self::record('skills', (string) $row['skill'], null, 'Most trained profession', Format::number(min(100, (int) $row['characters'] / $characters * 100)).'% of characters, best rank '.$row['best_rank']);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return Record|null
     */
    private static function questShare(array $rows): ?array
    {
        $byLevel = [];
        foreach ($rows as $row) {
            $byLevel[(int) $row['level']][(string) $row['source']] = (float) $row['xp'];
        }
        krsort($byLevel);
        foreach ($byLevel as $level => $sources) {
            $total = array_sum($sources);
            if ($total <= 0) {
                continue;
            }
            $others = [];
            arsort($sources);
            foreach ($sources as $source => $xp) {
                $kind = XpSource::tryFrom((string) $source);
                if ($kind !== null && $kind !== XpSource::Quest && $xp / $total >= 0.005) {
                    $others[] = $kind->label().' '.Format::percent($xp / $total);
                }
            }

            return self::record('quests', Format::percent(($sources[XpSource::Quest->value] ?? 0.0) / $total), null, 'Experience from quests at level '.$level, implode(', ', $others));
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $r
     * @return list<Rule>
     */
    private static function rules(array $r): array
    {
        $totals = StatsPage::totals($r['totals']);
        $played = $totals['played_seconds'] ?? null;
        $copper = $totals['copper_earned'] ?? null;
        $runs = array_sum(array_map(fn (array $row): int => (int) $row['runs'], StatsPage::rows($r['dungeons'])));
        $rows = [
            ['kills', 'Kills', $totals['kills'] ?? null, ''],
            ['hours', 'Hours recorded', $played === null ? null : round($played / 3600), ''],
            ['quests', 'Quests turned in', $totals['quests'] ?? null, ''],
            ['deaths', 'Deaths', $totals['deaths'] ?? null, ''],
            ['dungeons', 'Dungeon runs', $runs, ''],
            ['gold', 'Gold earned', $copper === null ? null : floor($copper / self::COPPER_PER_GOLD), 'g'],
        ];
        $rules = [];
        foreach ($rows as [$icon, $label, $value, $suffix]) {
            $count = $value === null ? 0 : (int) round($value);
            if ($count < 1) {
                continue;
            }
            $target = self::nextRound($count);
            $rules[] = [
                'icon' => $icon, 'label' => $label, 'value' => Format::number($count).$suffix,
                'at' => number_format($count / $target * 100, 1, '.', ''),
                'left' => Format::number($target - $count).$suffix.' to go', 'target' => Format::number($target).$suffix,
            ];
        }

        return $rules;
    }

    /** The smallest of 10, 25, 50, 100, 250, 500, 1,000, 2,500 and so on that is greater than the value. */
    private static function nextRound(int $value): int
    {
        for ($scale = 10; ; $scale *= 10) {
            foreach ([$scale, intdiv($scale * 5, 2), $scale * 5] as $target) {
                if ($target > $value) {
                    return $target;
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $levels  level, median seconds to reach it
     * @param  list<array<string, mixed>>  $reached  characters that reached each level, lowest first
     * @return Ascent|null
     */
    private static function ascent(array $levels, array $reached, int $cap): ?array
    {
        $curve = [];
        foreach ($levels as $row) {
            $curve[(int) $row['level']] = (float) $row['median'] / 3600;
        }
        ksort($curve);
        if (count($curve) < 2) {
            return null;
        }
        $topLevel = array_key_last($curve);
        $topHours = $curve[$topLevel];
        $reach = max(0.25, min(1, $topLevel / $cap));
        $yMax = max(1.0, $topHours / $reach);
        $x = fn (int $level): float => self::LEFT + (($level - 1) / ($cap - 1)) * (self::RIGHT - self::LEFT);
        $y = fn (float $hours): float => self::BASE - ($hours / $yMax) * (self::BASE - self::TOP);
        $f = fn (float|int $value): string => number_format($value, 1, '.', '');

        $points = [];
        foreach ($curve as $level => $hours) {
            $points[] = $f($x($level)).' '.$f($y($hours));
        }
        $ridge = implode('L', $points);

        $grid = [];
        $step = $yMax > 160 ? 50 : 25;
        for ($h = $step; $h < $yMax; $h += $step) {
            $grid[] = ['y' => $f($y($h)), 'text' => $h.'h', 'ty' => $f($y($h) + 3.5)];
        }
        $ticks = [];
        foreach (self::LEVEL_TICKS as $level) {
            if ($level <= $cap) {
                $ticks[] = ['x' => $f($x($level)), 'y2' => $f(self::BASE + 5), 'ty' => $f(self::BASE + 19), 'anchor' => $level === 1 ? 'start' : 'middle', 'dx' => $level === 1 ? '-3' : null, 'text' => $level === 1 ? 'Level 1' : (string) $level];
            }
        }

        $byLevel = array_column($reached, 'characters', 'level');
        $highest = $reached === [] ? 0 : (int) $reached[count($reached) - 1]['level'];
        $flags = [];
        $last = -99;
        $tall = false;
        foreach ($reached as $row) {
            $level = (int) $row['level'];
            $count = (int) $row['characters'] - (int) ($byLevel[$level + 1] ?? 0);
            if ($count < 1 || $level > $cap) {
                continue;
            }
            $tall = $level - $last < 4 ? ! $tall : false;
            $last = $level;
            $hours = 0.0;
            for ($below = $level; $below >= 1; $below--) {
                if (isset($curve[$below])) {
                    $hours = $curve[$below];
                    break;
                }
            }
            $fx = $x($level);
            $fy = $y($hours);
            $staff = $tall ? 62 : 34;
            $flags[] = [
                'summit' => $level === $cap, 'title' => Format::number($count).' at level '.$level, 'x' => $f($fx), 'y' => $f($fy), 'top' => $f($fy - $staff),
                'pennant' => 'M'.$f($fx).' '.$f($fy - $staff).'h15l-4.5 5 4.5 5h-15z', 'tx' => $f($fx + 20), 'ty' => $f($fy - $staff + 10), 'count' => Format::number($count),
            ];
        }

        $held = max($topLevel, $highest);
        $beyond = $held < $cap ? [
            'path' => 'M'.$f($x($topLevel)).' '.$f($y($topHours)).'L'.$f($x($cap)).' '.self::TOP,
            'cx' => $f($x($cap)), 'cy' => (string) self::TOP, 'tx' => $f($x($cap) - 10), 'ty' => (string) (self::TOP - 9),
            'text' => 'Level '.$cap.': no character past level '.$held.' yet',
        ] : null;

        return [
            'viewBox' => '0 0 '.self::W.' '.self::H,
            'label' => 'The median played time to reach each level drawn as a ridge, with a flag at each level where characters stand now',
            'grid' => $grid, 'left' => (string) self::LEFT, 'right' => (string) self::RIGHT, 'base' => (string) self::BASE, 'ticks' => $ticks,
            'land' => 'M'.$ridge.'L'.$f($x($topLevel)).' '.self::BASE.'L'.self::LEFT.' '.self::BASE.'z', 'ridge' => 'M'.$ridge,
            'beyond' => $beyond, 'flags' => $flags,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows  class, characters
     * @return list<ClassRow>
     */
    private static function classes(array $rows): array
    {
        $top = max([1, ...array_map(fn (array $row): int => (int) $row['characters'], $rows)]);

        return array_map(fn (array $row, int $i): array => [
            'class' => strtoupper((string) $row['class']), 'name' => Format::className((string) $row['class']),
            'value' => $i === 0 ? Format::count((int) $row['characters'], 'character') : Format::number((int) $row['characters']),
            'share' => number_format((int) $row['characters'] / $top * 100, 1, '.', ''),
        ], $rows, array_keys($rows));
    }

    /**
     * @param  list<array<string, mixed>>  $rows  faction, characters
     * @return Split|null
     */
    private static function split(array $rows): ?array
    {
        $count = array_column($rows, 'characters', 'faction');
        $sides = [];
        foreach ([[Roster::HORDE, 'horde'], [Roster::ALLIANCE, 'alliance']] as [$faction, $tone]) {
            if (isset($count[$faction])) {
                $sides[] = ['tone' => $tone, 'name' => $faction, 'count' => (int) $count[$faction]];
            }
        }
        if ($sides === []) {
            return null;
        }
        $parts = array_map(fn (array $side, int $i): string => $i === 0 ? Format::count($side['count'], $side['name'].' character') : Format::number($side['count']).' '.$side['name'], $sides, array_keys($sides));

        return ['label' => implode(', ', $parts), 'sides' => $sides];
    }
}
