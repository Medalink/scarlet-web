<?php

namespace App\View\Pages;

use App\View\Charts;
use App\View\Format;
use Illuminate\Support\Carbon;

/** The view data of /account/characters: every section of the page, formatted, from the rows its Actions return. */
final class AccountCharactersPage
{
    /** @var list<string> the chart series classes of resources/css/app.css, in the order of the most played characters; the characters beyond them share one */
    private const array SERIES = ['s1', 's2', 's3', 's4', 's5'];

    private const string OTHER = 'o';

    /** Days of the gold line. */
    private const int GOLD_DAYS = 60;

    /**
     * @param  list<array<string, mixed>>  $characters
     * @param  array<string, array<int, float>>  $days
     * @param  array{totals: array<int, array{earned: int, spent: int, transfer: int}>, balances: list<array{character_id: int, at: string, balance: int}>}  $gold
     * @param  list<array{a: int, b: int, seconds: int, sessions: int, last: string}>  $together
     * @param  array<int, array{character: list<array{level: int, seconds: int}>, ruleset: list<array<string, mixed>>}>  $levelTimes
     * @param  array<int, array<string, array{rank: int, cap: int}>>  $professions
     * @param  list<array<string, mixed>>  $rulesets
     * @return array<string, mixed>
     */
    public static function data(array $characters, array $days, array $gold, array $together, array $levelTimes, array $professions, array $rulesets): array
    {
        $names = array_column($characters, 'name', 'id');
        $classes = [];
        foreach (array_column($characters, 'id') as $rank => $id) {
            $classes[$id] = self::SERIES[$rank] ?? self::OTHER;
        }

        return [
            'empty' => $characters === [],
            'figures' => self::figures($characters),
            'table' => self::table($characters, $classes, $rulesets),
            'days' => self::days($characters, $classes, $days),
            'gold' => self::gold($characters, $gold),
            'together' => self::together($names, $together),
            'levels' => self::levels($characters, $levelTimes),
            'professions' => self::professions($characters, $professions),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $characters
     * @return list<array{label: string, value: string}>
     */
    private static function figures(array $characters): array
    {
        return [
            ['label' => 'Characters', 'value' => Format::number(count($characters))],
            ['label' => 'Time recorded', 'value' => Format::duration(array_sum(array_column($characters, 'recorded_seconds')))],
            ['label' => 'Sessions', 'value' => Format::number(array_sum(array_column($characters, 'sessions')))],
            ['label' => 'Kills recorded', 'value' => Format::number(array_sum(array_column($characters, 'kills')))],
            ['label' => 'Deaths recorded', 'value' => Format::number(array_sum(array_column($characters, 'deaths')))],
            ['label' => 'Gold', 'value' => Format::moneyText(array_sum(array_column($characters, 'balance')))],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $characters
     * @param  array<int, string>  $classes
     * @param  list<array<string, mixed>>  $rulesets
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    private static function table(array $characters, array $classes, array $rulesets): array
    {
        $labels = array_column($rulesets, 'label', 'id');

        return [
            'columns' => [['label' => 'Character'], ['label' => 'Level', 'numeric' => true], ['label' => 'Class'], ['label' => 'Ruleset'], ['label' => 'Played', 'numeric' => true], ['label' => 'Last played'], ['label' => 'Gold', 'numeric' => true], ['label' => 'Page']],
            'rows' => array_map(fn (array $c): array => ['cells' => [
                ['text' => $c['name'], 'href' => '/c/'.rawurlencode($c['name']), 'dot' => self::color($classes[$c['id']])],
                $c['level'] === null ? '' : (string) $c['level'],
                trim(Format::raceName($c['race']).' '.Format::className($c['class'])),
                $c['ruleset_id'] === null ? '' : (string) ($labels[$c['ruleset_id']] ?? ''),
                $c['played_seconds'] === null ? Format::duration($c['recorded_seconds']).' recorded' : Format::duration($c['played_seconds']),
                $c['last_seen_at'] === null ? '' : Carbon::parse($c['last_seen_at'])->format('j M Y'),
                $c['balance'] === null ? '' : Format::moneyText($c['balance']),
                $c['is_public'] ? 'Public' : 'Private',
            ]], $characters),
        ];
    }

    /**
     * Hours by day, one stack per day, one part per character; the characters beyond the named ones share a part.
     *
     * @param  list<array<string, mixed>>  $characters
     * @param  array<int, string>  $classes
     * @param  array<string, array<int, float>>  $days
     * @return array{spec: array<string, mixed>, key: list<array<string, mixed>>}|null
     */
    private static function days(array $characters, array $classes, array $days): ?array
    {
        if ($days === []) {
            return null;
        }
        $parts = [];
        foreach ($characters as $c) {
            $class = $classes[$c['id']];
            $parts[$class] ??= ['label' => $class === self::OTHER ? 'Other' : $c['name'], 'ids' => []];
            $parts[$class]['ids'][] = $c['id'];
        }
        $data = $say = $ticks = [];
        $top = 0.0;
        $first = Carbon::parse((string) array_key_first($days));
        $count = (int) $first->diffInDays(Carbon::parse((string) array_key_last($days))) + 1;
        $last = $count - 1;
        for ($i = 0; $i < $count; $i++) {
            $day = $first->copy()->addDays($i)->toDateString();
            $seconds = $days[$day] ?? [];
            $stack = $spoken = [];
            foreach ($parts as $part) {
                $sum = array_sum(array_map(fn (int $id): float => $seconds[$id] ?? 0.0, $part['ids']));
                $stack[] = round($sum / 3600, 3);
                if ($sum > 0) {
                    $spoken[] = $part['label'].' '.Format::duration($sum);
                }
            }
            $when = Carbon::parse($day);
            $top = max($top, array_sum($stack));
            $data[] = [$i, $stack];
            $say[] = Format::line($when->format('D j M'), ': '.Format::duration(array_sum($seconds)).' played'.($spoken === [] ? '' : ': '.implode(', ', $spoken)).'.');
            if ($i === $last || $i % 7 === 0) {
                $ticks[] = [$i, $when->format('j M')];
            }
        }
        $axis = Axis::nice($top);

        return [
            'spec' => Charts::stack(['w' => 1100, 'h' => 240, 'x0' => -1, 'x1' => $count, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks'], 'h'), 'xt' => $ticks], $data, array_keys($parts), $say),
            'key' => array_map(fn (string $class, array $part): array => ['label' => $part['label'], 'style' => 'background:'.self::color($class)], array_keys($parts), $parts),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $characters
     * @param  array{totals: array<int, array{earned: int, spent: int, transfer: int}>, balances: list<array{character_id: int, at: string, balance: int}>}  $gold
     * @return array<string, mixed>|null
     */
    private static function gold(array $characters, array $gold): ?array
    {
        if ($gold['balances'] === [] && $gold['totals'] === []) {
            return null;
        }
        $rows = [];
        $sum = ['balance' => 0, 'earned' => 0, 'spent' => 0, 'transfer' => 0];
        foreach ($characters as $c) {
            $own = $gold['totals'][$c['id']] ?? ['earned' => 0, 'spent' => 0, 'transfer' => 0];
            $balance = $c['balance'] ?? 0;
            $rows[] = ['cells' => [$c['name'], Format::moneyText($balance), Format::moneyText($own['earned']), Format::moneyText($own['spent']), Format::moneyText($own['earned'] - $own['spent'], true), Format::moneyText($own['transfer'], true)]];
            $sum = ['balance' => $sum['balance'] + $balance, 'earned' => $sum['earned'] + $own['earned'], 'spent' => $sum['spent'] + $own['spent'], 'transfer' => $sum['transfer'] + $own['transfer']];
        }
        $rows[] = ['class' => 'me', 'cells' => ['All characters', Format::moneyText($sum['balance']), Format::moneyText($sum['earned']), Format::moneyText($sum['spent']), Format::moneyText($sum['earned'] - $sum['spent'], true), Format::moneyText($sum['transfer'], true)]];

        return [
            'columns' => [['label' => 'Character'], ['label' => 'Balance', 'numeric' => true], ['label' => 'Earned', 'numeric' => true], ['label' => 'Spent', 'numeric' => true], ['label' => 'Net', 'numeric' => true], ['label' => 'Moved between yours', 'numeric' => true]],
            'rows' => $rows,
            'spec' => self::goldLine($gold['balances']),
        ];
    }

    /**
     * The combined balance at the end of each day with a session: every character counts at the balance of its latest session so far.
     *
     * @param  list<array{character_id: int, at: string, balance: int}>  $balances
     * @return array<string, mixed>|null
     */
    private static function goldLine(array $balances): ?array
    {
        $latest = $byDay = [];
        foreach ($balances as $row) {
            $latest[$row['character_id']] = $row['balance'];
            $byDay[Carbon::parse($row['at'])->utc()->toDateString()] = array_sum($latest);
        }
        $byDay = array_slice($byDay, -self::GOLD_DAYS, null, true);
        if (count($byDay) < 2) {
            return null;
        }
        $points = $say = $ticks = [];
        $last = count($byDay) - 1;
        $i = 0;
        foreach ($byDay as $day => $total) {
            $when = Carbon::parse($day);
            $points[] = [$i, $total / 10000];
            $say[] = Format::line($when->format('j M'), ': '.Format::moneyText($total));
            if ($i % 7 === 0) {
                $ticks[] = [$i, $when->format('j M')];
            }
            $i++;
        }
        $axis = Axis::nice(max([0.0, ...array_column($points, 1)]));

        return Charts::line(['w' => 1100, 'h' => 240, 'x0' => 0, 'x1' => $last, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks'], 'g'), 'xt' => $ticks], [['cls' => 'g', 'pts' => $points, 'mark' => $last]], $say, $last);
    }

    /**
     * @param  array<int, string>  $names
     * @param  list<array{a: int, b: int, seconds: int, sessions: int, last: string}>  $together
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}|null
     */
    private static function together(array $names, array $together): ?array
    {
        if ($together === []) {
            return null;
        }

        return [
            'columns' => [['label' => 'Characters'], ['label' => 'Online together', 'numeric' => true], ['label' => 'Sessions', 'numeric' => true], ['label' => 'Last time']],
            'rows' => array_map(fn (array $pair): array => ['cells' => [
                ($names[$pair['a']] ?? '').' and '.($names[$pair['b']] ?? ''),
                Format::duration($pair['seconds']),
                Format::number($pair['sessions']),
                Carbon::parse($pair['last'])->format('j M Y'),
            ]], $together),
        ];
    }

    /**
     * One chart per character that has left at least two levels.
     *
     * @param  list<array<string, mixed>>  $characters
     * @param  array<int, array{character: list<array{level: int, seconds: int}>, ruleset: list<array<string, mixed>>}>  $levelTimes
     * @return list<array{id: string, name: string, spec: array<string, mixed>, key: list<array<string, mixed>>}>
     */
    private static function levels(array $characters, array $levelTimes): array
    {
        $charts = [];
        foreach ($characters as $c) {
            $chart = CharacterSections::levelTimes($levelTimes[$c['id']]);
            if ($chart !== null) {
                $charts[] = ['id' => 'level-times-'.$c['id'], 'name' => $c['name'], ...$chart];
            }
        }

        return $charts;
    }

    /**
     * A row per profession any character has, a column per character.
     *
     * @param  list<array<string, mixed>>  $characters
     * @param  array<int, array<string, array{rank: int, cap: int}>>  $professions
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}|null
     */
    private static function professions(array $characters, array $professions): ?array
    {
        $best = [];
        foreach ($professions as $own) {
            foreach ($own as $name => $skill) {
                $best[$name] = max($best[$name] ?? 0, $skill['rank']);
            }
        }
        if ($best === []) {
            return null;
        }
        arsort($best);
        $rows = [];
        foreach (array_keys($best) as $name) {
            $rows[] = ['cells' => [$name, ...array_map(fn (array $c): string => isset($professions[$c['id']][$name]) ? $professions[$c['id']][$name]['rank'].' of '.$professions[$c['id']][$name]['cap'] : '', $characters)]];
        }

        return [
            'columns' => [['label' => 'Profession'], ...array_map(fn (array $c): array => ['label' => $c['name'], 'numeric' => true], $characters)],
            'rows' => $rows,
        ];
    }

    private static function color(string $class): string
    {
        return 'var(--c-'.$class.')';
    }
}
