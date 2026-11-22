<?php

namespace App\View\Pages;

use App\Support\KillerKind;
use App\Support\PageScope;
use App\Support\XpSource;
use App\View\Charts;
use App\View\Format;
use Illuminate\Support\Carbon;

/** The data of the stats dashboard (docs/comps/stats.html) from the answer of App\Actions\Public\StatsDashboard. */
final class StatsPage
{
    /** A level's deaths an hour is shown once characters spent this long at it; less is noise. */
    private const int MIN_RATE_SECONDS = 600;

    /** The skill kind of the decoder that holds the named skills. */
    private const string SKILL_KIND = 'SK';

    /**
     * @param  array<string, mixed>  $r  the answer of StatsDashboard
     * @return array<string, mixed>
     */
    public static function data(array $r, PageScope $scope, int $target): array
    {
        $levels = self::rows($r['levels']);
        $reached = self::rows($r['reached']);
        $totals = self::totals($r['totals']);
        $characters = $totals['characters'];
        $top = self::top($r);

        return [
            'title' => $scope->ruleset?->label() ?? ($scope->server->warMode() === null ? 'All characters' : 'All characters, '.$scope->server->label()),
            'sub' => self::sub($totals, $r['coverage']),
            'game' => StatsGame::data($r, $levels, $reached, $target),
            'cap' => self::cap($r, $reached, $target),
            'curve' => self::curve($levels, $top),
            'reach' => self::reach($reached, $top),
            'xp' => self::xp(self::rows($r['xp_sources']), $top),
            'ltime' => self::levelTime(self::rows($r['level_time']), $top),
            'dph' => self::deathsPerHour(self::rows($r['deaths_by_level']), self::rows($r['play_by_level']), $top),
            'killers' => self::killers(self::rows($r['killers'])),
            'deathZones' => self::deathZones(self::rows($r['death_zones'])),
            'dungeons' => self::dungeons(self::rows($r['dungeons'])),
            'professions' => self::professions(self::rows($r['professions']), $characters),
            'gold' => self::gold(self::rows($r['gold']), $top),
            'hours' => self::hours(self::rows($r['weekdays'])),
            'zones' => array_map(fn (array $zone): array => ['id' => (int) $zone['zone_id'], 'name' => (string) ($zone['zone'] ?? 'Zone '.$zone['zone_id'])], self::rows($r['zones'])),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function rows(mixed $rows): array
    {
        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * @return array<string, int|float|null>
     */
    public static function totals(mixed $totals): array
    {
        /** @var array<string, int|float|null> $totals */
        $totals = is_array($totals) ? $totals : [];

        return $totals + ['characters' => null, 'played_seconds' => null];
    }

    /**
     * @param  array<string, int|float|null>  $totals
     */
    private static function sub(array $totals, mixed $coverage): string
    {
        if ($totals['characters'] === null) {
            return Format::TOO_FEW.'.';
        }
        $characters = (int) $totals['characters'];
        $text = Format::count($characters, 'character');
        if ($totals['played_seconds'] !== null) {
            $text .= ', '.Format::count(round((float) $totals['played_seconds'] / 3600), 'hour').' recorded';
        }
        $coverage = is_array($coverage) ? $coverage : [];
        $since = $coverage['since'] ?? null;
        $updated = $coverage['updated_at'] ?? null;
        $text .= $since instanceof Carbon ? ($totals['played_seconds'] === null ? ', recorded' : '').' since '.$since->format('j M Y').'.' : '.';

        return $text.($updated instanceof Carbon ? ' Updated '.$updated->diffForHumans().'.' : '');
    }

    /**
     * @param  array<string, mixed>  $r
     */
    private static function top(array $r): int
    {
        $levels = [];
        foreach (['reached', 'levels', 'level_time', 'xp_sources', 'deaths_by_level', 'play_by_level', 'gold'] as $key) {
            foreach (self::rows($r[$key]) as $row) {
                $levels[] = (int) ($row['level'] ?? 0);
            }
        }

        return max([2, ...$levels]);
    }

    /**
     * @param  array<string, mixed>  $r
     * @param  list<array<string, mixed>>  $reached
     * @return array{heading: string, rows: list<array{cells: list<string>}>, note: string|null}
     */
    private static function cap(array $r, array $reached, int $target): array
    {
        $totals = self::totals($r['totals']);
        $at = (int) (array_column($reached, 'characters', 'level')[$target] ?? 0);
        $since = ', since first seen';
        $row = fn (string $label, string $value, string $basis): array => ['cells' => [$label, $value, $basis]];
        $figure = function (string $label, ?array $data, callable $value) use ($row): array {
            return $data === null ? $row($label, '–', Format::TOO_FEW) : $row($label, $value($data), Format::count((int) $data['characters'], 'character'));
        };
        $cap = is_array($r['at_cap'] ?? null) ? $r['at_cap'] : [];
        $money = $cap['money'] ?? null;
        $purse = fn (string $label, string $key): array => $money === null
            ? $row($label, '–', 'No economy data shared')
            : $row($label, Format::moneyText((float) $money[$key]), Format::count((int) $money['characters'], 'character'));

        return [
            'heading' => 'At the level cap',
            'rows' => $at === 0 ? [] : [
                $row('Characters at level '.$target, Format::number($at), $totals['characters'] === null ? Format::TOO_FEW : 'of '.Format::count((int) $totals['characters'], 'character')),
                $figure('Recorded time at level '.$target.$since, $cap['seconds'] ?? null, fn (array $d): string => Format::duration((float) $d['seconds'])),
                $purse('Gold earned at level '.$target.$since, 'earned'),
                $purse('Gold spent at level '.$target.$since, 'spent'),
                $figure('Dungeon runs at level '.$target.$since, $cap['runs'] ?? null, fn (array $d): string => Format::number((int) $d['runs'])),
                $figure('Deaths at level '.$target.$since, $cap['deaths'] ?? null, fn (array $d): string => Format::number((int) $d['deaths'])),
            ],
            'note' => $at === 0 ? 'No character is at level '.$target.' yet.' : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $levels
     * @return array{spec: array<string, mixed>, line: array{0: string, 1: string}}|null
     */
    private static function curve(array $levels, int $top): ?array
    {
        if (count($levels) < 2) {
            return null;
        }
        $median = $band = $fast = $say = [];
        foreach ($levels as $row) {
            $level = (int) $row['level'];
            $hours = fn (string $key): float => (float) $row[$key] / 3600;
            $median[] = [$level, $hours('median')];
            $band[] = [$level, $hours('q1'), $hours('q3')];
            $fast[] = [$level, $hours('p10')];
            $say[] = Format::line('Level '.$level, ' is reached after a median of '.Format::hours((float) $row['median']).' played, from '.Format::count((int) $row['characters'], 'character').'. The middle half is '.Format::hours((float) $row['q1'], false).' to '.Format::hours((float) $row['q3'], false).'.');
        }
        $axis = Axis::nice((float) max([0, ...array_column($band, 2)]));
        $last = count($levels) - 1;

        return [
            'spec' => Charts::line(
                ['x0' => 1, 'x1' => $top, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks'], 'h'), 'xt' => Charts::ticks(Axis::spread(1, $top))],
                [['cls' => 'a', 'pts' => $median, 'band' => $band, 'mark' => $last], ['cls' => 'b thin', 'pts' => $fast]],
                $say, $last, 'curve-out',
            ),
            'line' => $say[$last],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $reached
     * @return array<string, mixed>|null
     */
    private static function reach(array $reached, int $top): ?array
    {
        if ($reached === []) {
            return null;
        }
        $byLevel = array_column($reached, 'characters', 'level');
        $data = $say = [];
        for ($level = 1; $level <= $top; $level++) {
            $count = (int) ($byLevel[$level] ?? 0);
            $data[] = [$level, $count];
            $say[] = Format::line('Level '.$level, ': '.Format::count($count, 'character'));
        }
        $axis = Axis::nice((float) max([0, ...array_column($data, 1)]), 2);

        return Charts::bars(['w' => 380, 'h' => 250, 'x0' => 0, 'x1' => $top + 1, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks']), 'xt' => Charts::ticks(Axis::spread(1, $top, 3))], $data, 't', $say, null, 'reach-out');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{spec: array<string, mixed>, line: array{0: string, 1: string}, x: list<int>}|null
     */
    public static function xp(array $rows, int $top): ?array
    {
        $byLevel = [];
        foreach ($rows as $row) {
            $byLevel[(int) $row['level']][(string) $row['source']] = (float) $row['xp'];
        }
        if ($byLevel === []) {
            return null;
        }
        $order = [XpSource::Kill, XpSource::Dungeon, XpSource::Quest, XpSource::Exploring, XpSource::Other];
        $shares = $say = [];
        for ($level = 1; $level <= $top; $level++) {
            $sources = $byLevel[$level] ?? [];
            $total = array_sum($sources);
            $row = array_map(fn (XpSource $source): float => $total > 0 ? ($sources[$source->value] ?? 0.0) / $total : 0.0, $order);
            $shares[] = $row;
            $parts = array_combine(array_map(fn (XpSource $source): string => $source->label(), $order), $row);
            arsort($parts);
            $text = [];
            foreach ($parts as $name => $share) {
                if ($share >= 0.005) {
                    $text[] = $name.' '.Format::percent($share);
                }
            }
            $say[] = Format::line('Level '.$level, $text === [] ? ': no experience recorded.' : ': '.implode(', ', $text).'.');
        }
        $last = (int) max(array_keys($byLevel)) - 1;

        return ['spec' => Charts::shares($shares, $say, $last), 'line' => $say[$last], 'x' => Axis::spread(1, $top, 4)];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{chart: array<string, mixed>, note: string}|null
     */
    private static function levelTime(array $rows, int $top): ?array
    {
        if ($rows === []) {
            return null;
        }
        $data = $say = [];
        $slowest = self::slowest($rows) ?? $rows[0];
        foreach ($rows as $row) {
            $data[] = [(int) $row['level'], (float) $row['median'] / 3600];
            $say[] = Format::line('Level '.$row['level'], ': '.Format::duration((float) $row['median']).' median, from '.Format::count((int) $row['completed'], 'character'));
        }
        $axis = Axis::nice((float) max(array_column($data, 1)), 3);

        return [
            'chart' => Charts::bars(['w' => 380, 'h' => 250, 'x0' => 0, 'x1' => $top + 1, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks'], 'h'), 'xt' => Charts::ticks(Axis::spread(1, $top, 3))], $data, '', $say, null, 'ltime-out'),
            'note' => 'The slowest level is '.$slowest['level'].', at a median of '.Format::duration((float) $slowest['median']).', from '.Format::count((int) $slowest['completed'], 'character').'.',
        ];
    }

    /**
     * The row of the level with the highest median time to reach it (the first of equals).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    public static function slowest(array $rows): ?array
    {
        $slowest = null;
        foreach ($rows as $row) {
            $slowest = $slowest === null || (float) $row['median'] > (float) $slowest['median'] ? $row : $slowest;
        }

        return $slowest;
    }

    /**
     * Deaths an hour at each level that has enough played time to rate.
     *
     * @param  list<array<string, mixed>>  $deaths
     * @param  list<array<string, mixed>>  $played
     * @return list<array{level: int, rate: float, characters: int}>
     */
    public static function deathRates(array $deaths, array $played): array
    {
        $seconds = array_column($played, 'seconds', 'level');
        $characters = array_column($played, 'characters', 'level');
        $rates = [];
        foreach ($deaths as $row) {
            $time = (float) ($seconds[$row['level']] ?? 0);
            if ($time < self::MIN_RATE_SECONDS) {
                continue;
            }
            $rates[] = ['level' => (int) $row['level'], 'rate' => (int) $row['deaths'] / ($time / 3600), 'characters' => (int) $characters[$row['level']]];
        }

        return $rates;
    }

    /**
     * The level with the highest deaths an hour (the first of equals).
     *
     * @param  list<array{level: int, rate: float, characters: int}>  $rates
     * @return array{level: int, rate: float, characters: int}|null
     */
    public static function mostDangerous(array $rates): ?array
    {
        $worst = null;
        foreach ($rates as $rate) {
            $worst = $worst === null || $rate['rate'] > $worst['rate'] ? $rate : $worst;
        }

        return $worst;
    }

    /**
     * @param  list<array<string, mixed>>  $deaths
     * @param  list<array<string, mixed>>  $played
     * @return array{chart: array<string, mixed>, note: string}|null
     */
    private static function deathsPerHour(array $deaths, array $played, int $top): ?array
    {
        $rates = self::deathRates($deaths, $played);
        $worst = self::mostDangerous($rates);
        if (count($rates) < 2 || $worst === null) {
            return null;
        }
        $pts = $say = [];
        foreach ($rates as $rate) {
            $pts[] = [$rate['level'], $rate['rate']];
            $say[] = Format::line('Level '.$rate['level'], ': '.Format::number($rate['rate'], 2).' deaths an hour, from '.Format::count($rate['characters'], 'character'));
        }
        $axis = Axis::nice(max(array_column($pts, 1)), 3);

        return [
            'chart' => Charts::line(['w' => 380, 'h' => 250, 'x0' => 1, 'x1' => $top, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks']), 'xt' => Charts::ticks(Axis::spread(1, $top, 3))], [['cls' => 'a', 'pts' => $pts]], $say, count($pts) - 1, 'dph-out'),
            'note' => 'Most dangerous: level '.$worst['level'].', at '.Format::number($worst['rate'], 2).' deaths an hour, from '.Format::count($worst['characters'], 'character').'.',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function killers(array $rows): array
    {
        $top = (int) max([0, ...array_column($rows, 'deaths')]);

        return array_map(function (array $row, int $i) use ($top): array {
            $deaths = (int) $row['deaths'];
            $name = $row['kind'] === KillerKind::Player->value ? 'Another player' : (string) $row['killer'];

            return [
                'label' => $name, 'value' => $i === 0 ? Format::count($deaths, 'death') : Format::number($deaths), 'share' => $top > 0 ? $deaths / $top * 100 : 0,
                'href' => $row['npc_id'] === null ? null : '/creatures/'.$row['npc_id'],
            ];
        }, $rows, array_keys($rows));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function deathZones(array $rows): array
    {
        $top = (int) max([0, ...array_column($rows, 'deaths')]);

        return array_map(fn (array $row, int $i): array => [
            'label' => (string) ($row['zone'] ?? 'Zone '.$row['zone_id']), 'value' => $i === 0 ? Format::count((int) $row['deaths'], 'death') : Format::number((int) $row['deaths']),
            'share' => $top > 0 ? (int) $row['deaths'] / $top * 100 : 0, 'href' => '/zones/'.$row['zone_id'],
        ], $rows, array_keys($rows));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function dungeons(array $rows): array
    {
        return array_map(function (array $row): array {
            $runs = max(1, (int) $row['runs']);

            return ['cells' => [
                ['text' => (string) ($row['name'] ?? 'Instance '.$row['instance_id']), 'href' => '/zones/'.$row['zone_id']],
                Format::number((int) $row['runs']), Format::duration((float) $row['median']),
                Format::number((int) $row['deaths'] / $runs, 1), Format::number((int) $row['bosses'] / $runs, 1),
            ]];
        }, $rows);
    }

    /**
     * The named skills, the one most characters trained first.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function trainedSkills(array $rows): array
    {
        $rows = array_values(array_filter($rows, fn (array $row): bool => $row['kind'] === self::SKILL_KIND && $row['skill'] !== null));
        usort($rows, fn (array $a, array $b): int => (int) $b['characters'] <=> (int) $a['characters']);

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function professions(array $rows, int|float|null $characters): array
    {
        $rows = array_slice(self::trainedSkills($rows), 0, 8);
        $total = max(1, (int) $characters);

        return array_map(function (array $row, int $i) use ($total): array {
            $share = min(100, (int) $row['characters'] / $total * 100);

            return ['label' => (string) $row['skill'], 'value' => Format::number($share).'%'.($i === 0 ? ' of characters · best rank '.$row['best_rank'] : ' · '.$row['best_rank']), 'share' => $share];
        }, $rows, array_keys($rows));
    }

    /**
     * Copper earned and spent a character, by level.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private static function gold(array $rows, int $top): ?array
    {
        $rows = array_values(array_filter($rows, fn (array $row): bool => $row['level'] !== null && (int) $row['characters'] > 0));
        if (count($rows) < 2) {
            return null;
        }
        $earned = $spent = $say = [];
        foreach ($rows as $row) {
            $n = (int) $row['characters'];
            $earned[] = [(int) $row['level'], (float) $row['earned'] / $n / 10000];
            $spent[] = [(int) $row['level'], (float) $row['spent'] / $n / 10000];
            $say[] = Format::line('Level '.$row['level'], ': '.Format::moneyText((float) $row['earned'] / $n).' earned, '.Format::moneyText((float) $row['spent'] / $n).' spent a character, from '.Format::count($n, 'character'));
        }
        $axis = Axis::nice((float) max([0, ...array_column($earned, 1), ...array_column($spent, 1)]), 3);

        return Charts::line(['w' => 480, 'h' => 250, 'x0' => 1, 'x1' => $top, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks'], 'g'), 'xt' => Charts::ticks(Axis::spread(1, $top))], [['cls' => 'g', 'pts' => $earned], ['cls' => 'b thin', 'pts' => $spent]], $say, count($earned) - 1, 'gold-out');
    }

    /**
     * The hour of the week with the most played time, first of equals from Monday 00:00.
     *
     * @param  list<array<string, mixed>>  $rows  weekday (0 is Sunday), hour, seconds
     * @return array{day: int, hour: int, seconds: float}|null day 0 is Monday
     */
    public static function busiest(array $rows): ?array
    {
        $cell = [];
        foreach ($rows as $row) {
            $cell[(int) $row['weekday']][(int) $row['hour']] = (float) $row['seconds'];
        }
        $best = null;
        for ($day = 0; $day < 7; $day++) {
            for ($hour = 0; $hour < 24; $hour++) {
                $seconds = $cell[($day + 1) % 7][$hour] ?? 0.0;
                $best = $seconds > 0 && $seconds > ($best['seconds'] ?? 0.0) ? ['day' => $day, 'hour' => $hour, 'seconds' => $seconds] : $best;
            }
        }

        return $best;
    }

    /**
     * @param  list<array<string, mixed>>  $rows  weekday (0 is Sunday), hour, seconds
     * @return array<string, mixed>|null
     */
    public static function hours(array $rows): ?array
    {
        if ($rows === []) {
            return null;
        }
        $cell = [];
        foreach ($rows as $row) {
            $cell[(int) $row['weekday']][(int) $row['hour']] = (float) $row['seconds'];
        }
        $top = max(array_map(fn (array $day): float => max($day), $cell));
        $names = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $words = ['Quiet', 'Light', 'Moderate', 'Busy', 'Busiest'];
        $levels = $say = [];
        $peak = self::busiest($rows);
        $busiest = $peak === null ? null : $peak['day'] * 24 + $peak['hour'];
        foreach ($names as $d => $name) {
            for ($h = 0; $h < 24; $h++) {
                $value = $cell[($d + 1) % 7][$h] ?? 0.0;
                $level = $value <= 0 ? 0 : max(1, (int) ceil($value / $top * 4));
                $levels[$d][] = $level;
                $say[] = Format::line($name.' '.str_pad((string) $h, 2, '0', STR_PAD_LEFT).':00', ', '.$words[$level].($value > 0 ? ', '.Format::duration($value).' played' : ''));
            }
        }

        $summary = $busiest === null ? null : Format::line('Busiest hour', ': '.$names[intdiv($busiest, 24)].' '.str_pad((string) ($busiest % 24), 2, '0', STR_PAD_LEFT).':00 UTC');

        return Charts::hours($levels, $say, 'hours-out', $busiest, $summary);
    }
}
