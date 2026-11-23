<?php

namespace App\View\Character;

use App\Actions\Rollups\RulesetSummaries;
use App\Models\Character;
use App\View\Format;
use App\View\Sc\ClassColor;
use Carbon\CarbonInterface;

/** The top of /c/{name} (design/final/character-web.html): the name, one line, the level on a 1 to 60 rule, six tiles and the route. */
final class Head
{
    /**
     * @param  array<string, mixed>  $r  the answer of App\Actions\Public\CharacterOverview
     * @param  array<string, mixed>  $feats  the summary of BuildCharacterFeats
     * @return array<string, mixed>
     */
    public static function data(array $r, array $feats): array
    {
        /** @var Character $character */
        $character = $r['character'];
        /** @var array<string, mixed> $totals */
        $totals = $r['totals'];
        $name = Format::characterName($character->name);
        $added = $r['addonCounts'] ?? ['kills' => 0, 'quests' => 0];
        $seconds = $character->played_known ? $character->played_seconds : $character->recorded_seconds;
        $hours = max(1, $character->recorded_seconds) / 3600;
        $kills = $totals['kills'] + $added['kills'];
        $quests = $totals['quests'] + $added['quests'];
        $yards = array_sum(array_column(array_filter($r['records']['modes'], fn (array $mode): bool => $mode['mode'] !== 'a'), 'yards'));
        $deathZone = $r['deaths']['zones'][0]['zone'] ?? null;
        $total = (int) $feats['total'];

        return [
            'name' => $name,
            'first' => explode(' ', $name)[0],
            'color' => ClassColor::of($character->class),
            'line' => self::line($character, $r),
            'rule' => self::rule($character->level),
            'tiles' => array_values(array_filter([
                ['label' => 'Played', 'value' => Format::duration($seconds), 'sub' => $character->played_known && $character->recorded_seconds > 0 && $character->recorded_seconds < $seconds ? Format::duration($character->recorded_seconds).' recorded' : null],
                ['label' => 'Kills', 'value' => Format::number($kills), 'sub' => self::rate($kills, $hours)],
                ['label' => 'Quests', 'value' => Format::number($quests), 'sub' => self::rate($quests, $hours)],
                ['label' => 'Deaths', 'value' => Format::number($totals['deaths']), 'sub' => $deathZone === null ? null : 'most in '.$deathZone],
                $yards > 0 ? ['label' => 'Distance', ...Format::distance($yards, $yards >= 17600 ? 0 : 1), 'sub' => $r['records']['jumps'] > 0 ? Format::number($r['records']['jumps']).' jumps on the way' : null] : null,
                $total > 0 ? ['label' => 'Feats', 'value' => (string) $feats['held'], 'unit' => 'of '.$total, 'sub' => self::tierLine(array_values(array_map('intval', $feats['tiers'])))] : null,
            ])),
            'route' => Trail::spec($r['heat'], $r['marks']),
            'map' => '/map?'.http_build_query(['character' => $character->name]),
        ];
    }

    /** @param  array<string, mixed>  $r */
    private static function line(Character $character, array $r): string
    {
        $parts = [Format::character($character->level, $character->race, $character->class)];
        $spec = self::tree($r['talents']);
        if ($spec !== null) {
            $parts[] = $spec;
        }
        if ($r['ruleset'] !== null) {
            $parts[] = $r['ruleset']['name'].' ruleset';
        }
        if ($character->last_seen_at !== null) {
            $parts[] = self::played($character->last_seen_at);
        }
        if ($character->first_seen_at !== null && ! $character->whole_life) {
            $parts[] = 'recorded since '.$character->first_seen_at->utc()->format('j F');
        }

        return implode(' · ', array_filter($parts, fn (string $part): bool => $part !== ''));
    }

    /** @param  array<string, mixed>|null  $talents */
    private static function tree(?array $talents): ?string
    {
        if ($talents === null) {
            return null;
        }
        $groups = array_filter($talents['groups'], fn (array $group): bool => (int) $group['group'] === (int) $talents['active']);
        $group = $groups === [] ? end($talents['groups']) : reset($groups);
        $best = null;
        foreach ($group['tabs'] ?? [] as $tab) {
            if ($tab['spent'] > 0 && ($best === null || $tab['spent'] > $best['spent'])) {
                $best = $tab;
            }
        }

        return $best['name'] ?? null;
    }

    private static function played(CarbonInterface $at): string
    {
        $days = (int) $at->copy()->utc()->startOfDay()->diffInDays(now()->utc()->startOfDay());

        return match (true) {
            $days <= 0 => 'played today',
            $days === 1 => 'played yesterday',
            default => 'played '.$days.' days ago',
        };
    }

    /** @return array{at: string, ticks: list<array{at: string, n: int}>}|null */
    private static function rule(?int $level): ?array
    {
        if ($level === null || $level < 1) {
            return null;
        }
        $cap = RulesetSummaries::TARGET_LEVEL;
        $at = fn (int $n): string => number_format(min(100, max(0, ($n - 1) / ($cap - 1) * 100)), 1, '.', '');

        return ['at' => $at($level), 'ticks' => array_map(fn (int $n): array => ['at' => $at($n), 'n' => $n], [1, 20, 40, $cap])];
    }

    private static function rate(int|float $count, float $hours): string
    {
        $per = $count / $hours;

        return $count > 0 && $hours >= 1 ? Format::number($per, $per < 10 ? 1 : 0).' an hour' : '';
    }

    /** @param  list<int>  $tiers  characters' feats at each tier, Copper first */
    private static function tierLine(array $tiers): string
    {
        $names = ['Copper', 'Silver', 'Gold', 'Scarlet', 'Legend'];
        $lines = [];
        for ($tier = 4; $tier >= 0 && count($lines) < 2; $tier--) {
            if ($tiers[$tier] > 0) {
                $lines[] = $tiers[$tier].' '.$names[$tier];
            }
        }

        return implode(' · ', $lines);
    }
}
