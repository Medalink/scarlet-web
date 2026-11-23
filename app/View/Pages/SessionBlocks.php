<?php

namespace App\View\Pages;

use App\Support\Feat;
use App\Support\FeatText;
use App\View\Format;
use App\View\Sc\Crest;

/** The lower blocks of the session page: where the time went, experience by source, the counts and the moments. */
final class SessionBlocks
{
    /**
     * @param  array<string, mixed>  $recap
     * @param  array<string, int>  $counters
     * @return array<string, mixed>
     */
    public static function data(array $recap, array $counters, float $hours, float $miles): array
    {
        $top = $recap['top'] ?? [];

        return [
            'time' => self::time($recap, $counters),
            'xpRows' => self::experience($recap['xp']),
            'levelRows' => self::levels($recap),
            'killed' => array_map(fn (array $row): array => [$row['name'], $row['n']], $top['killed'] ?? []),
            'counts' => self::counts($counters, $miles),
            'moments' => self::moments($recap),
            'feats' => self::feats($recap['feats'] ?? ['earned' => [], 'near' => []]),
            'questZones' => array_map(fn (array $row): array => [$row['zone'], $row['n']], $top['quests_by_zone'] ?? []),
            'looted' => array_map(fn (array $row): array => [$row['name'], $row['n']], $top['looted'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $recap
     * @param  array<string, int>  $counters
     * @return list<array{heading: string, rows: list<array{0: string, 1: int, 2: string, 3: string}>}>
     */
    private static function time(array $recap, array $counters): array
    {
        $played = (int) $recap['played'];
        $moves = [
            ['Swimming', $counters['swimTime'] ?? 0, '--m-swim'],
            ['On foot', ($counters['footTime'] ?? 0) + ($counters['walkTime'] ?? 0), '--m-foot'],
            ['Mounted', ($counters['mountTime'] ?? 0) + ($counters['flyTime'] ?? 0), '--m-mount'],
            ['Flight paths', $counters['taxiTime'] ?? 0, '--m-flight'],
        ];
        $moving = array_sum(array_column($moves, 1));
        $moves[] = ['Standing still', max(0, $played - $moving), '--m-still'];
        $blocks = [['heading' => 'Moving and standing', 'rows' => self::timed($moves)]];
        $combat = $counters['combat'] ?? 0;
        if ($combat > 0) {
            $blocks[] = ['heading' => 'In combat', 'rows' => self::timed([['In combat', min($combat, $played), '--s-kill'], ['Out of combat', max(0, $played - $combat), '--s-other']])];
        }
        $group = $counters['groupTime'] ?? 0;
        if ($group > 0) {
            $blocks[] = ['heading' => 'In a group', 'rows' => self::timed([['In a group', min($group, $played), '--s-kill'], ['Solo', max(0, $played - $group), '--s-other']])];
        }

        return $blocks;
    }

    /**
     * @param  list<array{0: string, 1: int, 2: string}>  $rows
     * @return list<array{0: string, 1: int, 2: string, 3: string}>
     */
    private static function timed(array $rows): array
    {
        return array_values(array_map(fn (array $row): array => [$row[0], $row[1], $row[2], self::span($row[1])], array_filter($rows, fn (array $row): bool => $row[1] > 0)));
    }

    private static function span(int $seconds): string
    {
        return $seconds < 60 ? $seconds.'s' : Format::duration($seconds);
    }

    /**
     * @param  array<string, int>  $xp
     * @return list<array{0: string, 1: int, 2: string, 3: string}>
     */
    private static function experience(array $xp): array
    {
        $rows = [['Kills', $xp['kills'], '--s-kill'], ['Exploring', $xp['explore'], '--s-explore'], ['Quests', $xp['quests'], '--s-quest'], ['Dungeons', $xp['dungeons'], '--s-dungeon'], ['Other', $xp['other'], '--s-other']];

        return array_values(array_map(fn (array $row): array => [$row[0], $row[1], $row[2], Format::number($row[1])], array_filter($rows, fn (array $row): bool => $row[1] > 0)));
    }

    /**
     * Time on each level the session spent time on, from the moments the levels were reached.
     *
     * @param  array<string, mixed>  $recap
     * @return list<array{0: string, 1: int, 2: string}>
     */
    private static function levels(array $recap): array
    {
        if ($recap['levels'] === []) {
            return [];
        }
        $rows = [];
        $level = (int) $recap['level'][0];
        $from = 0;
        foreach ([...$recap['levels'], ['level' => $level, 'at' => (int) $recap['played']]] as $next) {
            $secs = max(0, (int) $next['at'] - $from);
            $rows[] = ['Level '.$level, $secs, self::span($secs)];
            $from = (int) $next['at'];
            $level = (int) $next['level'];
        }

        return array_values(array_filter($rows, fn (array $row): bool => $row[1] > 0));
    }

    /**
     * @param  array<string, int>  $counters
     * @return list<array{label: string, value: string, sub: string|null}>
     */
    private static function counts(array $counters, float $miles): array
    {
        $combat = $counters['combat'] ?? 0;
        $perMinute = fn (int $n): string => $combat >= 60 ? Format::number($n / ($combat / 60)).' a minute in combat' : '';
        $damage = $counters['damage'] ?? 0;
        $rows = [
            ['Jumps', $counters['jumps'] ?? 0, $miles >= 0.1 ? Format::number(($counters['jumps'] ?? 0) / $miles).' a mile' : ''],
            ['Casts', $counters['casts'] ?? 0, $perMinute($counters['casts'] ?? 0)],
            ['Damage done', $damage, $perMinute($damage)],
            ['Damage taken', $counters['damageTaken'] ?? 0, $damage > 0 ? Format::percent(($counters['damageTaken'] ?? 0) / $damage).' of the damage done' : ''],
            ['Healing done', $counters['healing'] ?? 0, ''],
            ['Quests turned in', $counters['quests'] ?? 0, ''],
            ['Items looted', $counters['loot'] ?? 0, ''],
            ['Places discovered', $counters['discoveries'] ?? 0, ''],
            ['Interrupts', $counters['interrupts'] ?? 0, ''],
            ['Time AFK', $counters['afkTime'] ?? 0, '', true],
        ];

        return array_values(array_map(
            fn (array $row): array => ['label' => $row[0], 'value' => ($row[3] ?? false) ? self::span($row[1]) : Format::number($row[1]), 'sub' => $row[2] === '' ? null : $row[2]],
            array_filter($rows, fn (array $row): bool => $row[1] > 0),
        ));
    }

    /**
     * Levels, deaths, bosses and places in the order they happened, then the feats, which carry no minute.
     *
     * @param  array<string, mixed>  $recap
     * @return list<array{at: int|null, glyph: string, text: string}>
     */
    private static function moments(array $recap): array
    {
        $rows = [];
        foreach ($recap['levels'] as $level) {
            $rows[] = ['at' => (int) $level['at'], 'glyph' => 'level', 'text' => 'Reached level '.$level['level']];
        }
        foreach ($recap['moments'] as $moment) {
            $glyph = match ($moment['kind']) {
                'death' => 'deaths',
                'boss' => 'dungeons',
                'discovery' => 'zones',
                default => 'crown',
            };
            $rows[] = ['at' => (int) $moment['at'], 'glyph' => $glyph, 'text' => (string) $moment['text']];
        }
        usort($rows, fn (array $a, array $b): int => $a['at'] <=> $b['at']);
        foreach ($recap['feats']['earned'] ?? [] as $earned) {
            $feat = Feat::from($earned['key']);
            $rows[] = ['at' => null, 'glyph' => 'crown', 'text' => $feat->label().', '.Crest::TIER_NAMES[$earned['tier']].': '.FeatText::counted($feat, $earned['value'])];
        }

        return $rows;
    }

    /**
     * @param  array{earned: list<array<string, mixed>>, near: list<array<string, mixed>>}  $feats
     * @return array{earned: list<array<string, mixed>>, near: list<array<string, mixed>>}
     */
    private static function feats(array $feats): array
    {
        return [
            'earned' => array_map(function (array $row): array {
                $feat = Feat::from($row['key']);

                return [...self::card($feat, $row['tier'], $row['value']), 'count' => FeatText::counted($feat, $row['value']), 'to' => $row['from'] > 0 ? 'Earned, up from '.Crest::TIER_NAMES[$row['from']] : 'Earned'];
            }, $feats['earned']),
            'near' => array_map(function (array $row): array {
                $feat = Feat::from($row['key']);

                return [...self::card($feat, $row['tier'], $row['value']), 'count' => FeatText::counted($feat, $row['value']).' · '.FeatText::toNext($feat, $row['left'], $row['tier'])];
            }, $feats['near']),
        ];
    }

    /** @return array{key: string, name: string, glyph: string, family: string, tier: int, progress: float, href: string} */
    private static function card(Feat $feat, int $tier, int|float $value): array
    {
        $thresholds = $feat->thresholds();
        $next = $thresholds[$tier] ?? null;
        $from = $thresholds[$tier - 1] ?? 0;

        return [
            'key' => $feat->value, 'name' => $feat->label(), 'glyph' => $feat->icon(), 'family' => $feat->family(), 'tier' => $tier,
            'progress' => $next === null ? 1.0 : round(max(0, min(1, ($value - $from) / ($next - $from))), 3),
            'href' => '/feats/'.$feat->value,
        ];
    }
}
