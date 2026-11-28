<?php

namespace App\View\Character;

use App\Actions\Public\Character\ReputationGoals;
use App\Actions\Public\Character\SessionRecords;
use App\Actions\Public\Character\SkillGoals;
use App\Actions\Rollups\RulesetSummaries;
use App\Models\Character;
use App\View\Dates;
use App\View\Format;
use Illuminate\Support\HtmlString;

/** The Level, Skills, Reputation and Records at the top of the overview of /c/{name}, in the order of the app's Character Overview. */
final class Goals
{
    private const array TIERS = [1 => 'Hated', 2 => 'Hostile', 3 => 'Unfriendly', 4 => 'Neutral', 5 => 'Friendly', 6 => 'Honored', 7 => 'Revered', 8 => 'Exalted'];

    private const array RECORDS = [
        SessionRecords::XP_HOUR => 'Most XP an hour',
        SessionRecords::GOLD_HOUR => 'Most gold an hour',
        SessionRecords::KILLS_HOUR => 'Most kills an hour',
        SessionRecords::GATHERS_HOUR => 'Most gathers an hour',
        SessionRecords::XP => 'Most XP in a session',
        SessionRecords::KILLS => 'Most kills in a session',
        SessionRecords::GATHERS => 'Most gathers in a session',
        SessionRecords::ITEM => 'Most of one item',
        SessionRecords::QUESTS => 'Most quests in a session',
        SessionRecords::LOOT => 'Most items looted in a session',
        SessionRecords::PLAYED => 'Longest session',
        SessionRecords::BALANCE => 'Highest balance',
    ];

    private const int REPUTATIONS_SHOWN = 6;

    /**
     * @param  array<string, mixed>  $goals  the answer of App\Actions\Public\CharacterGoals
     * @return array<string, mixed>
     */
    public static function data(array $goals, Character $character): array
    {
        $skills = self::skills($goals['skills']);
        $reputation = self::reputation($goals['reputation']);

        return [
            'level' => self::level($character->level, $goals['xp']),
            'skills' => $skills,
            'reputation' => $reputation,
            'reputationWide' => $reputation !== null && $skills !== null,
            'records' => self::records($goals['records']),
        ];
    }

    /**
     * @param  array{xp: int, max: int}|null  $xp
     * @return array{rows: list<array{0: string, 1: float, 2: string}>}|null
     */
    private static function level(?int $level, ?array $xp): ?array
    {
        if ($level === null || $level < 1) {
            return null;
        }
        $cap = RulesetSummaries::TARGET_LEVEL;
        $rows = [['Level', self::share($level, $cap), $level.' of '.$cap]];
        if ($xp !== null && $level < $cap) {
            $rows[] = ['To level '.($level + 1), self::share($xp['xp'], $xp['max']), Format::number($xp['xp']).' of '.Format::number($xp['max']).' XP'];
        }

        return ['rows' => $rows];
    }

    /**
     * @param  list<array{name: string, rank: int, max: int, kind: string}>  $skills
     * @return list<array{title: string, rows: list<array{0: string, 1: float, 2: string}>}>|null
     */
    private static function skills(array $skills): ?array
    {
        $groups = [];
        foreach ([SkillGoals::PROFESSION => 'Professions', SkillGoals::WEAPON => 'Weapons and defense'] as $kind => $title) {
            $of = array_values(array_filter($skills, fn (array $skill): bool => $skill['kind'] === $kind));
            usort($of, function (array $a, array $b): int {
                $left = fn (array $skill): int => $skill['max'] - $skill['rank'];

                return [$left($a) > 0 ? 0 : 1, $left($a), $a['name']] <=> [$left($b) > 0 ? 0 : 1, $left($b), $b['name']];
            });
            if ($of !== []) {
                $groups[] = ['title' => $title, 'rows' => array_map(fn (array $skill): array => [$skill['name'], self::share($skill['rank'], $skill['max']), Format::number($skill['rank']).' of '.Format::number($skill['max'])], $of)];
            }
        }

        return $groups === [] ? null : $groups;
    }

    /**
     * @param  list<array{id: int, name: string, tier: int, standing: int, from: int, to: int}>  $reps
     * @return array{summary: string, count: int, closest: list<array{0: string, 1: float, 2: string}>, rest: list<array{0: string, 1: float, 2: string}>}|null
     */
    private static function reputation(array $reps): ?array
    {
        if ($reps === []) {
            return null;
        }
        $tiers = array_count_values(array_column($reps, 'tier'));
        krsort($tiers);
        $row = fn (array $rep): array => [$rep['name'], self::share($rep['standing'] - $rep['from'], $rep['to'] - $rep['from']), self::TIERS[$rep['tier']]];
        $closest = array_slice(array_filter($reps, fn (array $rep): bool => $rep['tier'] < ReputationGoals::EXALTED_TIER), 0, self::REPUTATIONS_SHOWN);
        $shown = array_column($closest, 'id');
        $rest = array_filter($reps, fn (array $rep): bool => ! in_array($rep['id'], $shown, true));

        return [
            'summary' => implode(' · ', array_map(fn (int $tier, int $count): string => self::TIERS[$tier].' '.$count, array_keys($tiers), $tiers)),
            'count' => count($reps),
            'closest' => array_map($row, $closest),
            'rest' => array_map($row, array_values($rest)),
        ];
    }

    /**
     * @param  list<array{key: string, value: int|float, at: string, from: int|null, to: int|null, item: string|null}>  $records
     * @return array{cols: int, tiles: list<array{label: string, value: string, sub: HtmlString}>}|null
     */
    private static function records(array $records): ?array
    {
        $tiles = [];
        foreach ($records as $record) {
            $level = match (true) {
                $record['key'] === SessionRecords::BALANCE => null,
                $record['from'] !== null && $record['to'] !== null && $record['to'] > $record['from'] => 'levels '.$record['from'].' to '.$record['to'],
                ($record['to'] ?? $record['from']) !== null => 'level '.($record['to'] ?? $record['from']),
                default => null,
            };
            $tiles[] = [
                'label' => self::RECORDS[$record['key']],
                'value' => self::value($record['key'], $record['value']),
                'sub' => Dates::join(...array_values(array_filter([Dates::day($record['at']), $level === null ? null : ' · '.$level, $record['item'] === null ? null : ' · '.$record['item']]))),
            ];
        }
        if ($tiles === []) {
            return null;
        }
        $count = count($tiles);

        return ['cols' => $count % 4 !== 0 && $count % 3 === 0 ? 3 : min(4, $count), 'tiles' => $tiles];
    }

    private static function value(string $key, int|float $value): string
    {
        return match ($key) {
            SessionRecords::GOLD_HOUR, SessionRecords::BALANCE => Format::moneyText($value),
            SessionRecords::PLAYED => Format::duration($value),
            SessionRecords::KILLS_HOUR, SessionRecords::GATHERS_HOUR => $value >= 10 ? Format::number($value) : (string) round($value, 1),
            default => Format::number($value),
        };
    }

    private static function share(int|float $part, int|float $whole): float
    {
        return $whole <= 0 ? 0.0 : round(max(0, min(1, $part / $whole)) * 100, 1);
    }
}
