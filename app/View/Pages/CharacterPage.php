<?php

namespace App\View\Pages;

use App\Actions\Public\CharacterOverview;
use App\Models\Character;
use App\View\Charts;
use App\View\Format;
use Illuminate\Support\Carbon;

/** The data of docs/comps/character.html and the overview of /demo from the answer of App\Actions\Public\CharacterOverview. */
final class CharacterPage
{
    /**
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    public static function data(array $r): array
    {
        /** @var Character $character */
        $character = $r['character'];
        /** @var array<string, mixed>|null $ruleset */
        $ruleset = $r['ruleset'];
        /** @var array<string, mixed>|null $week */
        $week = $r['week'];
        /** @var list<array<string, mixed>> $dungeons */
        $dungeons = $r['dungeons'];
        /** @var array{total: int, killers: list<array<string, mixed>>, zones: list<array<string, mixed>>} $deaths */
        $deaths = $r['deaths'];
        /** @var list<array<string, mixed>> $sessions */
        $sessions = $r['sessions'];
        $economy = (bool) $r['economy'];
        $topRuns = $dungeons === [] ? 1 : max(1, (int) $dungeons[0]['runs']);
        $topKill = $deaths['killers'] === [] ? 1 : max(1, (int) $deaths['killers'][0]['deaths']);

        return [
            'id' => $character->id,
            'name' => $character->name,
            'owner' => $r['owner'],
            'economy' => $economy,
            'facts' => array_values(array_filter([
                Format::character($character->level, $character->race, $character->class),
                $character->faction,
                $ruleset['label'] ?? null,
                Format::duration($character->played_seconds).' played',
            ])),
            'paceTitle' => isset($ruleset['label']) ? 'Levelling against other '.$ruleset['label'].' characters' : 'Levelling against other characters of the same ruleset',
            'map' => MapSpec::build($r['heat'], [], null, [], $r['marks']),
            'hasMap' => $r['heat'] !== [],
            'deathCount' => count($r['marks']),
            'week' => $week === null ? null : self::week($week, $economy),
            'pace' => self::pace($r['pace'], $character->name),
            'xp' => StatsPage::xp($r['xp']),
            'gold' => $r['gold'] === null ? null : self::gold($r['gold']),
            'dungeons' => array_map(fn (array $row): array => [
                'label' => (string) ($row['name'] ?? 'Instance '.$row['instance_id']), 'value' => Format::count($row['runs'], 'run').', '.Format::duration($row['inside_seconds']),
                'share' => (int) $row['runs'] / $topRuns * 100,
            ], $dungeons),
            'dungeonTotal' => $dungeons === [] ? null : Format::count(array_sum(array_column($dungeons, 'runs')), 'dungeon run').' in all.',
            'deaths' => array_map(fn (array $row): array => ['label' => (string) $row['killer'], 'value' => Format::number($row['deaths']), 'share' => (int) $row['deaths'] / $topKill * 100], $deaths['killers']),
            'deathZones' => array_map(fn (array $row): string => (string) ($row['zone'] ?? 'Zone '.$row['zone_id']).' '.$row['deaths'], $deaths['zones']),
            'deathTotal' => $deaths['total'] === 0 ? 'No deaths recorded.' : Format::count($deaths['total'], 'death').' recorded.',
            'days' => self::days($r['days']),
            'distance' => $r['distance'] > 0 ? Format::number($r['distance'], 1).' world widths walked' : null,
            'sessions' => self::sessions($sessions, $economy),
            'more' => count($sessions) >= CharacterOverview::HISTORY ? '/c/'.$character->name.'?sessions='.(count($sessions) * 4) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $week
     * @return array<string, mixed>
     */
    private static function week(array $week, bool $economy): array
    {
        $from = Carbon::parse((string) $week['from']);
        $to = Carbon::parse((string) $week['to']);
        $figures = [
            ['label' => 'Played', 'value' => Format::duration($week['seconds'])],
            ['label' => 'Dungeons', 'value' => Format::number($week['runs'])],
            ['label' => 'Quests', 'value' => Format::number($week['quests'])],
        ];
        if ($economy && $week['gold'] !== null) {
            $figures[] = ['label' => 'Gold', 'value' => Format::moneyText($week['gold'], true)];
        }
        $figures[] = ['label' => 'Zones', 'value' => Format::number($week['zones'])];

        return ['range' => $from->format('D j').' to '.$to->format('D j M'), 'figures' => $figures];
    }

    /**
     * The character's played time at each level against the ruleset's median and middle half.
     *
     * @param  array{character: list<array{level: int, seconds: int}>, ruleset: list<array<string, mixed>>}  $pace
     * @return array<string, mixed>|null
     */
    private static function pace(array $pace, string $name): ?array
    {
        $own = $pace['character'];
        if (count($own) < 2) {
            return null;
        }
        $curve = [];
        foreach ($pace['ruleset'] as $row) {
            $curve[(int) $row['level']] = $row;
        }
        $mine = $median = $band = $say = [];
        foreach ($own as $row) {
            $level = $row['level'];
            $hours = $row['seconds'] / 3600;
            $mine[] = [$level, $hours];
            $line = $curve[$level] ?? null;
            if ($line !== null) {
                $median[] = [$level, (float) $line['median'] / 3600];
                $band[] = [$level, (float) $line['q1'] / 3600, (float) $line['q3'] / 3600];
            }
            $say[] = Format::line('Level '.$level, $line === null
                ? ' reached after '.Format::hours($row['seconds']).' played.'
                : ' reached after '.Format::hours($row['seconds']).' played, against a median of '.Format::hours((float) $line['median']).'.');
        }
        $top = max([0.0, ...array_column($mine, 1), ...array_column($band, 2)]);
        $axis = Axis::nice($top);
        $first = $mine[0][0];
        $end = $mine[count($mine) - 1][0];
        $last = count($mine) - 1;
        $series = [['cls' => 'a', 'pts' => $median, 'band' => $band], ['cls' => 'b', 'pts' => $mine, 'mark' => $last]];
        $final = $own[$last];
        $compare = isset($curve[$final['level']]) ? (float) $curve[$final['level']]['median'] - $final['seconds'] : null;

        return [
            // The readout steps along the character's own levels (series 1), not the median, which can miss a level.
            'spec' => Charts::line(['x0' => $first, 'x1' => $end, 'y1' => $axis['max'], 'lead' => 1, 'yt' => Charts::ticks($axis['ticks'], 'h'), 'xt' => Charts::ticks(Axis::spread($first, $end))], $series, $say, $last, 'pace-out'),
            'line' => [
                'Reached level '.$final['level'],
                ' in '.Format::hours($final['seconds']).' played'.match (true) {
                    $compare === null => '.',
                    abs($compare) < 300 => ', level with the median.',
                    $compare > 0 => ', '.Format::hours($compare).' faster than the median.',
                    default => ', '.Format::hours(-$compare).' slower than the median.',
                },
            ],
            'key' => [['label' => $name, 'class' => 'k-line', 'style' => 'background:var(--fg)'], ['label' => 'Median', 'class' => 'k-line', 'style' => 'background:var(--accent)'], ['label' => 'Middle half', 'style' => 'background:rgba(255,45,85,.3)']],
        ];
    }

    /**
     * @param  list<array{at: string, balance: int}>  $gold
     * @return array<string, mixed>
     */
    private static function gold(array $gold): array
    {
        $last = count($gold) - 1;
        $points = $say = [];
        foreach ($gold as $i => $row) {
            $points[] = [$i, $row['balance'] / 10000];
            $say[] = Format::line(Carbon::parse($row['at'])->format('j M'), ': '.Format::moneyText($row['balance']));
        }
        $change = $last < 1 ? 0 : $gold[$last]['balance'] - $gold[0]['balance'];
        $axis = Axis::nice(max([0.0, ...array_column($points, 1)]));

        return [
            'balance' => Format::money($gold[$last]['balance'] ?? 0),
            'change' => $last < 1 ? 'One session recorded.' : Format::moneyText($change, true).' over the last '.Format::count($last + 1, 'session'),
            'spec' => $last < 1 ? null : Charts::line(['x0' => 0, 'x1' => $last, 'y1' => $axis['max'], 'yt' => Charts::ticks($axis['ticks'], 'g'), 'xt' => []], [['cls' => 'g', 'pts' => $points, 'mark' => $last]], $say, $last, 'gold-out'),
        ];
    }

    /**
     * Play time by day from the first played day to the last, a column per week from Monday.
     *
     * @param  array<string, float>  $days
     * @return array<string, mixed>|null
     */
    private static function days(array $days): ?array
    {
        if ($days === []) {
            return null;
        }
        $first = Carbon::parse((string) array_key_first($days))->startOfWeek();
        $end = Carbon::parse((string) array_key_last($days));
        $cells = $say = $months = [];
        $previous = null;
        for ($day = $first->copy(); $day <= $end->copy()->endOfWeek(); $day->addDay()) {
            $seconds = $days[$day->toDateString()] ?? 0.0;
            $hours = $seconds / 3600;
            $cells[] = [match (true) {
                $seconds <= 0 => 0,
                $hours < 1 => 1,
                $hours < 2 => 2,
                $hours < 4 => 3,
                default => 4,
            }, $day > $end ? 1 : 0];
            $say[] = Format::line($day->format('D j M'), $seconds > 0 ? ': '.Format::duration($seconds).' played.' : ': not played.');
            if ($day->dayOfWeekIso === 1) {
                $months[] = $day->format('M') === $previous ? '' : $day->format('M');
                $previous = $day->format('M');
            }
        }

        return ['spec' => Charts::days($cells, $months, $say, 'days-out'), 'line' => ['Hover a day', ' to read it.']];
    }

    /**
     * @param  list<array<string, mixed>>  $sessions
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    private static function sessions(array $sessions, bool $economy): array
    {
        $columns = [['label' => 'Started'], ['label' => 'Played', 'numeric' => true], ['label' => 'Levels', 'numeric' => true], ['label' => 'XP', 'numeric' => true], ['label' => 'Kills', 'numeric' => true], ['label' => 'Deaths', 'numeric' => true], ['label' => 'Quests', 'numeric' => true]];
        if ($economy) {
            $columns[] = ['label' => 'Gold', 'numeric' => true];
        }

        return [
            'columns' => $columns,
            'rows' => array_map(function (array $row) use ($economy): array {
                $levels = $row['level_start'] === null ? '' : ($row['level_start'] === $row['level_end'] ? (string) $row['level_start'] : $row['level_start'].' to '.$row['level_end']);
                $cells = [Carbon::parse((string) $row['at'])->format('j M H:i'), Format::duration($row['seconds']), $levels, Format::number($row['xp'] ?? 0), Format::number($row['kills'] ?? 0), Format::number($row['deaths'] ?? 0), Format::number($row['quests'] ?? 0)];
                if ($economy) {
                    $cells[] = Format::moneyText($row['gold'] ?? 0, true);
                }

                return ['cells' => $cells];
            }, $sessions),
        ];
    }
}
