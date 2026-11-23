<?php

namespace App\Support\Recap;

use Illuminate\Support\Carbon;

/** The `route` of a recap: the session's path fitted to one sheet, as the app's recap.rs fits it. Coordinates are percent of a map until the last step. */
final class Route
{
    private const float SHEET = 1000.0;

    private const int SHEET_POINTS = 400;

    private const int LEG_POINTS = 600;

    private const float MAP_ASPECT = 1.5;

    private const float MIN_ASPECT = 0.4;

    private const float MAX_ASPECT = 2.5;

    /**
     * @return array{legs: list<array{map: int, mode: string, points: list<int>}>, sheet: array{aspect: float, points: list<int>, breaks: list<int>, air: list<bool>}}|null
     */
    public static function read(Scope $scope): ?array
    {
        $legs = self::legs($scope);
        $lines = array_values(array_filter($legs, fn (array $leg): bool => count($leg['points']) >= 2));
        $map = self::mainMap($lines);
        if ($map === null) {
            return null;
        }
        $sheet = self::sheet(array_values(array_filter($lines, fn (array $leg): bool => $leg['map'] === $map)));
        if ($sheet === null) {
            return null;
        }

        return [
            'legs' => array_map(fn (array $leg): array => [
                'map' => $leg['map'],
                'mode' => $leg['dash'] ? 'flight' : 'ground',
                'points' => self::flat(self::thin($leg['points'], self::LEG_POINTS), 100.0),
            ], $lines),
            'sheet' => $sheet,
        ];
    }

    /** @return list<array{map: int, dash: bool, points: list<array{0: float, 1: float}>}> */
    private static function legs(Scope $scope): array
    {
        $flights = $scope->rows('flights')->whereColumn('map', 'to_map')->whereNotNull('x')->whereNotNull('to_x')->orderBy('at')
            ->get(['at', 'map', 'x', 'y', 'to_x', 'to_y'])->all();
        $legs = [];
        $open = false;
        $next = 0;
        $drop = function (string $until) use (&$legs, &$open, &$next, $flights): void {
            while ($next < count($flights) && Carbon::parse((string) $flights[$next]->at)->lte(Carbon::parse($until))) {
                $f = $flights[$next++];
                $legs[] = ['map' => (int) $f->map, 'dash' => true, 'points' => [[$f->x / 100, $f->y / 100], [$f->to_x / 100, $f->to_y / 100]]];
                $open = false;
            }
        };
        foreach ($scope->rows('path_points')->whereNotNull('map')->whereNotNull('x')->whereNotNull('y')->orderBy('at')->cursor() as $point) {
            $drop((string) $point->at);
            $last = count($legs) - 1;
            $pair = [$point->x / 100, $point->y / 100];
            if ($open && ! $legs[$last]['dash'] && $legs[$last]['map'] === (int) $point->map) {
                $legs[$last]['points'][] = $pair;

                continue;
            }
            $legs[] = ['map' => (int) $point->map, 'dash' => false, 'points' => [$pair]];
            $open = true;
        }
        $drop('9999-12-31');

        return $legs;
    }

    /** @param  list<array{map: int, dash: bool, points: list<array{0: float, 1: float}>}>  $lines */
    private static function mainMap(array $lines): ?int
    {
        $by = [];
        foreach ($lines as $leg) {
            if (! $leg['dash']) {
                $by[$leg['map']] = ($by[$leg['map']] ?? 0) + count($leg['points']);
            }
        }
        $best = null;
        foreach ($by as $map => $n) {
            if ($best === null || $n > $by[$best] || ($n === $by[$best] && $map < $best)) {
                $best = $map;
            }
        }

        return $best;
    }

    /**
     * @param  list<array{map: int, dash: bool, points: list<array{0: float, 1: float}>}>  $onMap
     * @return array{aspect: float, points: list<int>, breaks: list<int>, air: list<bool>}|null
     */
    private static function sheet(array $onMap): ?array
    {
        $total = array_sum(array_map(fn (array $leg): int => count($leg['points']), $onMap));
        $stride = max(1, (int) ceil($total / self::SHEET_POINTS));
        $kept = [];
        foreach ($onMap as $leg) {
            $cap = max(2, intdiv(count($leg['points']), $stride));
            $kept[] = ['dash' => $leg['dash'], 'points' => array_map(fn (array $p): array => [$p[0] * self::MAP_ASPECT, $p[1]], self::thin($leg['points'], $cap))];
        }
        $all = array_merge(...array_map(fn (array $leg): array => $leg['points'], $kept));
        if (count($all) < 2) {
            return null;
        }
        $xs = array_column($all, 0);
        $ys = array_column($all, 1);
        $left = min($xs);
        $top = max($ys);
        $w = max(1.0, max($xs) - $left);
        $h = max(1.0, $top - min($ys));
        if ($w > $h * self::MAX_ASPECT) {
            $grown = $w / self::MAX_ASPECT;
            $top += ($grown - $h) / 2;
            $h = $grown;
        }
        if ($w < $h * self::MIN_ASPECT) {
            $grown = $h * self::MIN_ASPECT;
            $left -= ($grown - $w) / 2;
            $w = $grown;
        }
        $scale = self::SHEET / max($w, $h);
        $points = [];
        $breaks = [];
        $air = [];
        foreach ($kept as $i => $leg) {
            if ($i > 0) {
                $breaks[] = count($air) + 1;
            }
            foreach ($leg['points'] as $k => [$x, $y]) {
                $points[] = (int) round(($x - $left) * $scale);
                $points[] = (int) round(($top - $y) * $scale);
                $air[] = $leg['dash'] && $k > 0;
            }
        }
        while ($air !== [] && ! end($air)) {
            array_pop($air);
        }

        return ['aspect' => round($w / $h, 4), 'points' => $points, 'breaks' => $breaks, 'air' => $air];
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     * @return list<array{0: float, 1: float}>
     */
    private static function thin(array $points, int $cap): array
    {
        if (count($points) <= $cap) {
            return $points;
        }
        $stride = (int) ceil(count($points) / $cap);
        $out = [];
        for ($i = 0; $i < count($points); $i += $stride) {
            $out[] = $points[$i];
        }
        $last = $points[count($points) - 1];
        if ($out[count($out) - 1] !== $last) {
            $out[] = $last;
        }

        return $out;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     * @return list<int>
     */
    private static function flat(array $points, float $by): array
    {
        $out = [];
        foreach ($points as [$x, $y]) {
            $out[] = (int) round($x * $by);
            $out[] = (int) round($y * $by);
        }

        return $out;
    }
}
