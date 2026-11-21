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
        $totals = self::totals($r['totals']);
        $characters = $totals['characters'];

        return [
            'title' => $scope->ruleset?->label() ?? ($scope->server->warMode() === null ? 'All characters' : 'All characters, '.$scope->server->label()),
            'sub' => self::sub($totals, $r['coverage']),
            'figures' => self::figures($r, $levels, $target),
            'curve' => self::curve($levels),
            'reach' => self::reach($levels),
            'xp' => self::xp(self::rows($r['xp_sources'])),
            'ltime' => self::levelTime(self::rows($r['level_time'])),
            'dph' => self::deathsPerHour(self::rows($r['deaths_by_level']), self::rows($r['play_by_level'])),
            'killers' => self::killers(self::rows($r['killers'])),
            'deathZones' => self::deathZones(self::rows($r['death_zones'])),
            'dungeons' => self::dungeons(self::rows($r['dungeons'])),
            'professions' => self::professions(self::rows($r['professions']), $characters),
            'gold' => self::gold(self::rows($r['gold'])),
            'hours' => self::hours(self::rows($r['weekdays'])),
            'zones' => array_map(fn (array $zone): array => ['id' => (int) $zone['zone_id'], 'name' => (string) ($zone['zone'] ?? 'Zone '.$zone['zone_id'])], self::rows($r['zones'])),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rows(mixed $rows): array
    {
        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * @return array<string, int|float|null>
     */
    private static function totals(mixed $totals): array
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
            $text .= ', '.Format::count(round((float) $totals['played_seconds'] / 3600), 'hour').' of play';
        }
        $coverage = is_array($coverage) ? $coverage : [];
        $since = $coverage['since'] ?? null;
        $updated = $coverage['updated_at'] ?? null;
        $text .= $since instanceof Carbon ? ', recorded since '.$since->format('j M Y').'.' : '.';

        return $text.($updated instanceof Carbon ? ' Updated '.$updated->diffForHumans().'.' : '');
    }

    /**
     * @param  array<string, mixed>  $r
     * @param  list<array<string, mixed>>  $levels
     * @return list<array<string, string>>
     */
    private static function figures(array $r, array $levels, int $target): array
    {
        $totals = self::totals($r['totals']);
        $number = fn (int|float|null $value): array => $value === null ? ['value' => '–', 'unit' => Format::TOO_FEW] : ['value' => Format::number($value)];

        $reached = null;
        foreach ($levels as $row) {
            if ((int) $row['level'] <= $target) {
                $reached = $row;
            }
        }
        $median = $reached === null
            ? ['value' => '–', 'unit' => 'none yet']
            : ['value' => Format::duration((float) $reached['median']), 'unit' => 'played'];
        $runs = array_sum(array_map(fn (array $row): int => (int) $row['runs'], self::rows($r['dungeons'])));

        return [
            ['label' => 'Characters', ...$number($totals['characters'])],
            ['label' => 'Hours played', ...($totals['played_seconds'] === null ? $number(null) : ['value' => Format::number(round((float) $totals['played_seconds'] / 3600))])],
            ['label' => 'Median to level '.($reached['level'] ?? $target), ...$median],
            ['label' => 'Deaths', ...$number($totals['deaths'] ?? null)],
            ['label' => 'Quests turned in', ...$number($totals['quests'] ?? null)],
            ['label' => 'Dungeon runs', ...$number($runs)],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $levels
     * @return array{spec: array<string, mixed>, line: array{0: string, 1: string}}|null
     */
    private static function curve(array $levels): ?array
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
            $say[] = Format::line('Level '.$level, ' is reached after a median of '.Format::hours((float) $row['median']).' played. The middle half is '.Format::hours((float) $row['q1'], false).' to '.Format::hours((float) $row['q3'], false).'.');
        }
        $axis = Axis::nice((float) max([0, ...array_column($band, 2)]));
        $last = count($levels) - 1;
        $first = (int) $levels[0]['level'];
        $end = (int) $levels[$last]['level'];

        return [
            'spec' => Charts::line(
                ['x0' => $first, 'x1' => $end, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks'], 'h'), 'xt' => Charts::ticks(Axis::spread($first, $end))],
                [['cls' => 'a', 'pts' => $median, 'band' => $band, 'mark' => $last], ['cls' => 'b thin', 'pts' => $fast]],
                $say, $last, 'curve-out',
            ),
            'line' => $say[$last],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $levels
     * @return array<string, mixed>|null
     */
    private static function reach(array $levels): ?array
    {
        if ($levels === []) {
            return null;
        }
        $data = $say = [];
        foreach ($levels as $row) {
            $data[] = [(int) $row['level'], (int) $row['characters']];
            $say[] = Format::line('Level '.$row['level'], ': '.Format::count((int) $row['characters'], 'character'));
        }
        $axis = Axis::nice((float) max(array_column($data, 1)), 2);
        $first = $data[0][0];
        $end = $data[count($data) - 1][0];

        return Charts::bars(['w' => 380, 'h' => 250, 'x0' => $first - 1, 'x1' => $end + 1, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks']), 'xt' => Charts::ticks(Axis::spread($first, $end, 3))], $data, 't', $say, null, 'reach-out');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{spec: array<string, mixed>, line: array{0: string, 1: string}, x: list<int>}|null
     */
    public static function xp(array $rows): ?array
    {
        $byLevel = [];
        foreach ($rows as $row) {
            $byLevel[(int) $row['level']][(string) $row['source']] = (float) $row['xp'];
        }
        ksort($byLevel);
        if ($byLevel === []) {
            return null;
        }
        $order = [XpSource::Kill, XpSource::Dungeon, XpSource::Quest, XpSource::Exploring, XpSource::Other];
        $shares = $say = [];
        foreach ($byLevel as $level => $sources) {
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
            $say[] = Format::line('Level '.$level, ': '.implode(', ', $text).'.');
        }
        $last = count($shares) - 1;
        $levels = array_keys($byLevel);

        return ['spec' => Charts::shares($shares, $say, $last), 'line' => $say[$last], 'x' => Axis::spread($levels[0], $levels[$last], 4)];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{chart: array<string, mixed>, note: string}|null
     */
    private static function levelTime(array $rows): ?array
    {
        if ($rows === []) {
            return null;
        }
        $data = $say = [];
        $slowest = $rows[0];
        foreach ($rows as $row) {
            $data[] = [(int) $row['level'], (float) $row['median'] / 3600];
            $say[] = Format::line('Level '.$row['level'], ': '.Format::duration((float) $row['median']).' median');
            $slowest = (float) $row['median'] > (float) $slowest['median'] ? $row : $slowest;
        }
        $axis = Axis::nice((float) max(array_column($data, 1)), 3);
        $first = $data[0][0];
        $end = $data[count($data) - 1][0];

        return [
            'chart' => Charts::bars(['w' => 380, 'h' => 250, 'x0' => $first - 1, 'x1' => $end + 1, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks'], 'h'), 'xt' => Charts::ticks(Axis::spread($first, $end, 3))], $data, '', $say, null, 'ltime-out'),
            'note' => 'The slowest level is '.$slowest['level'].', at a median of '.Format::duration((float) $slowest['median']).'.',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $deaths
     * @param  list<array<string, mixed>>  $played
     * @return array{chart: array<string, mixed>, note: string}|null
     */
    private static function deathsPerHour(array $deaths, array $played): ?array
    {
        $seconds = array_column($played, 'seconds', 'level');
        $pts = $say = [];
        $worst = null;
        foreach ($deaths as $row) {
            $time = (float) ($seconds[$row['level']] ?? 0);
            if ($time < self::MIN_RATE_SECONDS) {
                continue;
            }
            $rate = (int) $row['deaths'] / ($time / 3600);
            $pts[] = [(int) $row['level'], $rate];
            $say[] = Format::line('Level '.$row['level'], ': '.Format::number($rate, 2).' deaths an hour');
            $worst = $worst === null || $rate > $worst[1] ? [(int) $row['level'], $rate] : $worst;
        }
        if (count($pts) < 2 || $worst === null) {
            return null;
        }
        $axis = Axis::nice(max(array_column($pts, 1)), 3);
        $first = $pts[0][0];
        $end = $pts[count($pts) - 1][0];

        return [
            'chart' => Charts::line(['w' => 380, 'h' => 250, 'x0' => $first, 'x1' => $end, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks']), 'xt' => Charts::ticks(Axis::spread($first, $end, 3))], [['cls' => 'a', 'pts' => $pts]], $say, count($pts) - 1, 'dph-out'),
            'note' => 'Most dangerous: level '.$worst[0].', at '.Format::number($worst[1], 2).' deaths an hour.',
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
                ['text' => (string) ($row['name'] ?? 'Instance '.$row['instance_id']), 'href' => '/zones/-'.$row['instance_id']],
                Format::number((int) $row['runs']), Format::duration((float) $row['median']),
                Format::number((int) $row['deaths'] / $runs, 1), Format::number((int) $row['bosses'] / $runs, 1),
            ]];
        }, $rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function professions(array $rows, int|float|null $characters): array
    {
        $rows = array_values(array_filter($rows, fn (array $row): bool => $row['kind'] === self::SKILL_KIND && $row['skill'] !== null));
        usort($rows, fn (array $a, array $b): int => (int) $b['characters'] <=> (int) $a['characters']);
        $rows = array_slice($rows, 0, 8);
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
    private static function gold(array $rows): ?array
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
            $say[] = Format::line('Level '.$row['level'], ': '.Format::moneyText((float) $row['earned'] / $n).' earned, '.Format::moneyText((float) $row['spent'] / $n).' spent a character');
        }
        $axis = Axis::nice((float) max([0, ...array_column($earned, 1), ...array_column($spent, 1)]), 3);
        $first = $earned[0][0];
        $end = $earned[count($earned) - 1][0];

        return Charts::line(['w' => 480, 'h' => 250, 'x0' => $first, 'x1' => $end, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks'], 'g'), 'xt' => Charts::ticks(Axis::spread($first, $end))], [['cls' => 'g', 'pts' => $earned], ['cls' => 'b thin', 'pts' => $spent]], $say, count($earned) - 1, 'gold-out');
    }

    /**
     * @param  list<array<string, mixed>>  $rows  weekday (0 is Sunday), hour, seconds
     * @return array<string, mixed>|null
     */
    private static function hours(array $rows): ?array
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
        foreach ($names as $d => $name) {
            for ($h = 0; $h < 24; $h++) {
                $value = $cell[($d + 1) % 7][$h] ?? 0.0;
                $level = $value <= 0 ? 0 : max(1, (int) ceil($value / $top * 4));
                $levels[$d][] = $level;
                $say[] = Format::line($name.' '.str_pad((string) $h, 2, '0', STR_PAD_LEFT).':00', ', '.$words[$level].($value > 0 ? ', '.Format::duration($value).' played' : ''));
            }
        }

        return Charts::hours($levels, $say, 'hours-out');
    }
}
