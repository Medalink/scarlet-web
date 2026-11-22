<?php

namespace App\View\Pages;

use App\Actions\Rollups\RulesetSummaries;
use App\Http\Requests\Web\CharacterRequest;
use App\Models\Character;
use App\View\Charts;
use App\View\Format;
use Illuminate\Support\Carbon;

/** The data of docs/comps/character.html and the overview of /demo from the answer of App\Actions\Public\CharacterOverview. */
final class CharacterPage
{
    private const int RATE_FROM = 600;

    private const float BAR_REM = 6.0;

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
        $levels = CharacterLevels::figure($r['levelTimes'], $r['xp'], $r['deathLevels']);

        $quests = CharacterLists::quests($r['questsDone']);
        $creatures = CharacterLists::creatures($r['creaturesMet']);
        $steps = CharacterSkills::chart($r['skillHistory'], $r['playBuckets']);

        return [
            'id' => $character->id,
            'name' => $character->name,
            'owner' => $r['owner'],
            'economy' => $economy,
            'since' => $since,
            'facts' => array_values(array_filter([
                ['text' => Format::character(null, $character->race, $character->class), 'href' => null],
                ['text' => $character->faction, 'href' => null],
                $ruleset === null ? null : ['text' => $ruleset['name'], 'href' => '/rulesets/'.$ruleset['slug']],
                $character->whole_life || $character->first_level === null ? null : ['text' => 'First recorded at level '.$character->first_level, 'href' => null],
            ], fn (?array $fact): bool => $fact !== null && (string) $fact['text'] !== '')),
            'figures' => array_values(array_filter([
                ['label' => 'Level', 'value' => (string) $character->level, 'count' => $character->level, 'icon' => 'level'],
                $character->played_known
                    ? ['label' => 'Played', 'value' => Format::duration($character->played_seconds), 'icon' => 'hours']
                    : ['label' => 'Recorded'.$since, 'value' => Format::duration($character->recorded_seconds), 'icon' => 'hours'],
                $cap === null ? null : ['label' => 'Time at level '.$cap['level'].($cap['observed'] ? $since : ''), 'value' => Format::duration($cap['seconds_at_cap']), 'icon' => null],
                ['label' => 'Kills'.$since, 'value' => Format::number($totals['kills']), 'icon' => 'kills'],
                ['label' => 'Deaths'.$since, 'value' => Format::number($totals['deaths']), 'icon' => 'deaths'],
                ['label' => 'Quests'.$since, 'value' => Format::number($totals['quests']), 'icon' => 'quests'],
                round($totals['distance'], 1) > 0 ? ['label' => 'World widths walked'.$since, 'value' => Format::number($totals['distance'], 1), 'icon' => 'distance'] : null,
            ])),
            'classKey' => strtoupper((string) $character->class),
            'levelRule' => self::levelRule($character->level),
            'chapters' => CharacterLayout::chapters($cap !== null, $r['gold'] !== null, $quests !== null, $creatures !== null, $steps !== null, $r['talents'] !== null, $levels !== null),
            'map' => CharacterReplay::map($r['heat'], $r['marks'], '/c/'.rawurlencode($character->name).'/heat'),
            'hasMap' => $r['heat']['cells'] !== [],
            'deathCount' => count($r['marks']),
            'week' => $week === null ? null : self::week($week, $economy),
            'levels' => $levels,
            'records' => CharacterRecords::tiles($r['records'], $totals, $since),
            'feats' => CharacterFeats::section($r['feats'], $character),
            'standings' => CharacterStandings::rows($r['standings']),
            'gold' => $r['gold'] === null ? null : self::gold($r['gold']),
            'quests' => $quests,
            'creatures' => $creatures,
            'runs' => CharacterLists::runs($r['dungeonRuns'], $dungeons),
            'killed' => CharacterLists::killed($r['killed']),
            'crafted' => CharacterLists::crafted($r['crafted']),
            'play' => CharacterPlay::band($r['playBuckets'], Carbon::now()),
            'zoneHours' => CharacterSections::zones($r['zoneHours']),
            'deaths' => CharacterSections::deaths($r['deathLog'], $deaths, (int) $character->level),
            'items' => CharacterSections::items($r['items']),
            'skills' => ['rows' => CharacterSections::skills($r['skills'], $steps['classes'] ?? []), 'steps' => $steps],
            'talents' => CharacterTalents::data($r['talents']),
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
     * The rule from level 1 to the level cap with the character's level on it, or no row for a character with no level.
     *
     * @return list<array{icon: string, label: string, value: string, at: string, left: string, target: string}>
     */
    private static function levelRule(?int $level): array
    {
        if ($level === null || $level < 1) {
            return [];
        }
        $cap = RulesetSummaries::TARGET_LEVEL;
        $share = min(1.0, ($level - 1) / ($cap - 1));

        return [[
            'icon' => 'level', 'label' => 'Level', 'value' => (string) $level,
            'at' => number_format($share * 100, 1, '.', ''),
            'left' => $level >= $cap ? 'At the level cap' : ($cap - $level).' to go', 'target' => (string) $cap,
        ]];
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
            ['label' => 'Played', 'value' => Format::duration($week['seconds']), 'icon' => 'hours'],
            ['label' => 'Dungeons', 'value' => Format::number($week['runs']), 'icon' => 'dungeons'],
            ['label' => 'Quests', 'value' => Format::number($week['quests']), 'icon' => 'quests'],
        ];
        if ($economy && $week['gold'] !== null) {
            $figures[] = ['label' => 'Gold', 'value' => Format::moneyText($week['gold'], true), 'icon' => 'gold'];
        }
        $figures[] = ['label' => 'Zones', 'value' => Format::number($week['zones']), 'icon' => 'zones'];

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

    private static function xpPerHour(int $xp, int $seconds): string
    {
        return $xp > 0 && $seconds >= self::RATE_FROM ? Format::number($xp / $seconds * 3600) : '';
    }

    /**
     * @param  list<array<string, mixed>>  $sessions
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    private static function sessions(array $sessions, bool $economy): array
    {
        $longest = max([1, ...array_column($sessions, 'seconds')]);
        $columns = [['label' => 'Started'], ['label' => 'Played', 'numeric' => true], ['label' => 'Played, to scale', 'hide' => true, 'class' => 'viz'], ['label' => 'XP per hour', 'numeric' => true], ['label' => 'Levels', 'numeric' => true], ['label' => 'XP', 'numeric' => true], ['label' => 'Kills', 'numeric' => true], ['label' => 'Deaths', 'numeric' => true], ['label' => 'Quests', 'numeric' => true]];
        if ($economy) {
            $columns[] = ['label' => 'Gold', 'numeric' => true];
        }

        return [
            'columns' => $columns,
            'rows' => array_map(function (array $row) use ($economy, $longest): array {
                $levels = $row['level_start'] === null ? '' : ($row['level_start'] === $row['level_end'] ? (string) $row['level_start'] : $row['level_start'].' to '.$row['level_end']);
                $cells = [Carbon::parse((string) $row['at'])->format('j M H:i'), Format::duration($row['seconds']), ['text' => '', 'bar' => round($row['seconds'] / $longest * self::BAR_REM, 2).'rem'], self::xpPerHour((int) ($row['xp'] ?? 0), (int) $row['seconds']), $levels, Format::number($row['xp'] ?? 0), Format::number($row['kills'] ?? 0), Format::number($row['deaths'] ?? 0), Format::number($row['quests'] ?? 0)];
                if ($economy) {
                    $cells[] = Format::moneyText($row['gold'] ?? 0, true);
                }

                return ['cells' => $cells];
            }, $sessions),
        ];
    }
}
