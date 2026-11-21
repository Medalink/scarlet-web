<?php

namespace App\View\Pages;

use App\Http\Requests\Web\CharacterRequest;
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
        /** @var array{level: int, seconds_at_cap: int, observed: bool}|null $cap */
        $cap = $r['cap'];
        /** @var array<string, mixed> $totals */
        $totals = $r['totals'];
        $since = self::since($character);
        $levelTimes = CharacterSections::levelTimes($r['levelTimes']);
        $xp = StatsPage::xp($r['xp']);

        return [
            'id' => $character->id,
            'name' => $character->name,
            'owner' => $r['owner'],
            'economy' => $economy,
            'since' => $since,
            'facts' => array_values(array_filter([
                Format::character(null, $character->race, $character->class),
                $character->faction,
                $ruleset['label'] ?? null,
                $character->whole_life || $character->first_level === null ? null : 'First recorded at level '.$character->first_level,
            ])),
            'figures' => array_values(array_filter([
                ['label' => 'Level', 'value' => (string) $character->level],
                $character->played_known
                    ? ['label' => 'Played', 'value' => Format::duration($character->played_seconds)]
                    : ['label' => 'Recorded'.$since, 'value' => Format::duration($character->recorded_seconds)],
                $cap === null ? null : ['label' => 'Time at level '.$cap['level'].($cap['observed'] ? $since : ''), 'value' => Format::duration($cap['seconds_at_cap'])],
                ['label' => 'Kills'.$since, 'value' => Format::number($totals['kills'])],
                ['label' => 'Deaths'.$since, 'value' => Format::number($totals['deaths'])],
                ['label' => 'Quests'.$since, 'value' => Format::number($totals['quests'])],
                ['label' => 'World widths walked'.$since, 'value' => Format::number($totals['distance'], 1)],
            ])),
            'rows' => CharacterLayout::rows($cap !== null, $r['gold'] !== null, $r['talents'] !== null, $levelTimes !== null, $xp !== null),
            'map' => MapSpec::levelled($r['heat']['cells'], [], $r['heat']['fit'], [], $r['marks']),
            'hasMap' => $r['heat']['cells'] !== [],
            'deathCount' => count($r['marks']),
            'week' => $week === null ? null : self::week($week, $economy),
            'levelTimes' => $levelTimes,
            'records' => CharacterRecords::tiles($r['records'], $totals, $r['items'], $r['killed'], $since),
            'xp' => $xp,
            'gold' => $r['gold'] === null ? null : self::gold($r['gold']),
            'runs' => CharacterLists::runs($r['dungeonRuns'], $dungeons),
            'killed' => CharacterLists::killed($r['killed']),
            'crafted' => CharacterLists::crafted($r['crafted']),
            'hours' => StatsPage::hours($r['hours']),
            'zoneHours' => CharacterSections::zones($r['zoneHours']),
            'deaths' => CharacterSections::deaths($r['deathLog'], $deaths, (int) $character->level),
            'items' => CharacterSections::items($r['items']),
            'skills' => CharacterSections::skills($r['skills']),
            'talents' => CharacterTalents::data($r['talents']),
            'days' => self::days($r['days']),
            'sessions' => self::sessions($sessions, $economy),
            'more' => count($sessions) >= $r['history'] && $r['history'] < CharacterRequest::MAX_HISTORY ? '/c/'.$character->name.'?sessions='.min($r['history'] * 4, CharacterRequest::MAX_HISTORY) : null,
        ];
    }

    /** The words that make a count Scarlet made a count of what it recorded, for a character it did not see from its first level: " since 21 Nov". */
    private static function since(Character $character): string
    {
        return $character->whole_life || $character->first_seen_at === null ? '' : ' since '.$character->first_seen_at->utc()->format('j M');
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
        $busiest = Carbon::parse((string) array_search(max($days), $days));
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

        $summary = Format::line('Busiest day', ': '.$busiest->format('D j M').', '.Format::duration($days[$busiest->toDateString()]));

        return ['spec' => Charts::days($cells, $months, $say, 'days-out', (int) $first->diffInDays($busiest), $summary), 'line' => $summary];
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
