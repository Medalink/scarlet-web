<?php

namespace App\Support\Recap;

use Illuminate\Support\Carbon;

/** The `levels`, `moments` and `buckets` of a recap: what happened and when, in seconds from the session start. */
final class Timeline
{
    private const int BUCKET_SECS = 300;

    private const int MAX_BUCKETS = 96;

    private const int SAME_DEATH_MS = 3000;

    /** @return list<array{level: int, at: int}> */
    public static function levels(Scope $scope): array
    {
        $out = [];
        foreach ($scope->rows('level_ups')->where('ding', true)->orderBy('at')->get(['at', 'reached']) as $row) {
            $out[] = ['level' => (int) $row->reached, 'at' => self::at($scope, (string) $row->at)];
        }

        return $out;
    }

    /** @return list<array{at: int, kind: string, text: string}> */
    public static function moments(Scope $scope): array
    {
        $out = [];
        $lastDeath = null;
        foreach ($scope->rows('deaths')->orderBy('at')->get(['at', 'killer', 'killer_kind', 'environmental_type']) as $row) {
            $ms = (int) round(Carbon::parse((string) $row->at)->getPreciseTimestamp(3));
            if ($lastDeath !== null && $ms - $lastDeath < self::SAME_DEATH_MS) {
                continue;
            }
            $lastDeath = $ms;
            $out[] = ['at' => self::at($scope, (string) $row->at), 'kind' => 'death', 'text' => self::died($row->killer_kind, $row->killer, $row->environmental_type)];
        }
        foreach ($scope->rows('boss_kills')->orderBy('at')->get(['at', 'name']) as $row) {
            $out[] = ['at' => self::at($scope, (string) $row->at), 'kind' => 'boss', 'text' => 'Defeated '.($row->name ?: 'a boss')];
        }
        usort($out, fn (array $a, array $b): int => $a['at'] <=> $b['at']);

        return $out;
    }

    /**
     * XP in five-minute buckets. A session past eight hours doubles the width and folds its entries in pairs, so there are at most 96. The seconds in combat
     * per bucket are not recorded with a time by the decoder, so `combat` is zero in every bucket.
     *
     * @return array{secs: int, xp: list<int>, combat: list<int>}
     */
    public static function buckets(Scope $scope, int $playedMs): array
    {
        $secs = self::BUCKET_SECS;
        while (self::count($playedMs, $secs) > self::MAX_BUCKETS) {
            $secs *= 2;
        }
        $n = self::count($playedMs, $secs);
        $xp = array_fill(0, $n, 0);
        foreach ($scope->rows('xp_gains')->get(['at', 'amount']) as $row) {
            $i = min($n - 1, max(0, intdiv(self::since($scope, (string) $row->at), $secs * 1000)));
            $xp[$i] += (int) $row->amount;
        }

        return ['secs' => $secs, 'xp' => array_values($xp), 'combat' => array_fill(0, $n, 0)];
    }

    private static function count(int $playedMs, int $secs): int
    {
        return max(1, intdiv($playedMs + $secs * 1000 - 1, $secs * 1000));
    }

    private static function at(Scope $scope, string $at): int
    {
        return intdiv(self::since($scope, $at), 1000);
    }

    private static function since(Scope $scope, string $at): int
    {
        return max(0, (int) round(Carbon::parse($at)->getPreciseTimestamp(3)) - $scope->start * 1000);
    }

    private static function died(mixed $kind, mixed $killer, mixed $environment): string
    {
        return match (true) {
            is_string($environment) && $environment !== '' => 'Killed by '.ucfirst(strtolower($environment)),
            $kind === 'p' => 'Killed by a player',
            is_string($killer) && $killer !== '' => 'Killed by '.$killer,
            default => 'Died',
        };
    }
}
