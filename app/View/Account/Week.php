<?php

namespace App\View\Account;

use App\View\Format;
use Illuminate\Support\Carbon;

/** The four tiles of the week on /account: time played, days in a row, levels gained and feats earned since Monday UTC. */
final class Week
{
    private const int CRESTS = 4;

    /**
     * @param  array<string, mixed>  $b  the bundle of App\Actions\Account\ShowAccountHome
     * @param  array<int, array<string, mixed>>  $by  the roster by character id
     * @return list<array<string, mixed>>
     */
    public static function tiles(array $b, array $by): array
    {
        $since = Carbon::parse($b['week']['since'], 'UTC');
        $totals = array_map(fn (array $seconds): float => array_sum($seconds), $b['days']);
        $spark = [];
        for ($i = 0; $i < 7; $i++) {
            $spark[] = (int) round($totals[$since->copy()->addDays($i)->toDateString()] ?? 0);
        }

        return [
            ['label' => 'Played since Monday', 'value' => Format::hours(array_sum($spark)), 'sub' => null, 'spark' => $spark, 'crests' => null],
            ['label' => 'Days in a row', 'value' => (string) self::streak($totals), 'sub' => self::streakNote($totals), 'spark' => null, 'crests' => null],
            self::levels($b['week']['levels'], $by),
            self::feats($b['week']['feats'], $b['catalog']),
        ];
    }

    /** @param  array<string, float>  $totals */
    private static function streak(array $totals): int
    {
        $day = Carbon::now('UTC')->startOfDay();
        if (($totals[$day->toDateString()] ?? 0) <= 0) {
            $day = $day->subDay();
        }
        $run = 0;
        while (($totals[$day->toDateString()] ?? 0) > 0) {
            $run++;
            $day = $day->subDay();
        }

        return $run;
    }

    /** @param  array<string, float>  $totals */
    private static function streakNote(array $totals): string
    {
        $played = array_keys(array_filter($totals, fn (float $seconds): bool => $seconds > 0));
        if ($played === []) {
            return 'Nothing played yet';
        }
        $last = Carbon::parse((string) max($played), 'UTC');
        $today = Carbon::now('UTC')->startOfDay();

        return match (true) {
            $last->equalTo($today) => 'Played today',
            $last->equalTo($today->copy()->subDay()) => 'Played yesterday',
            default => 'Last played '.$last->format('j M'),
        };
    }

    /**
     * @param  array<int, int>  $levels
     * @param  array<int, array<string, mixed>>  $by
     * @return array<string, mixed>
     */
    private static function levels(array $levels, array $by): array
    {
        arsort($levels);
        $sub = implode(' · ', array_map(fn (int $id, int $gained): string => Format::characterName($by[$id]['name']).' '.$gained, array_keys(array_slice($levels, 0, 3, true)), array_slice($levels, 0, 3)));

        return ['label' => 'Levels since Monday', 'value' => '+'.array_sum($levels), 'sub' => $sub === '' ? null : $sub, 'spark' => null, 'crests' => null];
    }

    /**
     * @param  list<array{character_id: int, feat: string, tier: int}>  $events
     * @param  list<array<string, mixed>>  $catalog
     * @return array<string, mixed>
     */
    private static function feats(array $events, array $catalog): array
    {
        $by = array_column($catalog, null, 'key');
        $crests = [];
        foreach ($events as $event) {
            $feat = $by[$event['feat']] ?? null;
            if ($feat !== null && count($crests) < self::CRESTS) {
                $crests[] = ['glyph' => $feat['glyph'], 'key' => $feat['key'], 'tier' => $event['tier'], 'name' => $feat['name']];
            }
        }

        return ['label' => 'Feats since Monday', 'value' => '+'.count($events), 'sub' => null, 'spark' => null, 'crests' => $crests];
    }
}
