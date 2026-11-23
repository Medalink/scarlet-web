<?php

namespace App\Actions\Public;

use App\Http\Middleware\PublicApi;
use App\Support\MovementMode;
use App\Support\WorldGrid;
use App\Support\WorldLayer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MapTrails
{
    /** Cells read at most, the most walked first. */
    private const int CELLS = 60000;

    /** Flight routes read at most, the most flown first. */
    private const int ROUTES = 2000;

    /** Flight ends are read rounded to this many decimals of the world, which keeps one row per route and direction. */
    private const int END_DECIMALS = 4;

    /** Flight ends closer than this share of the world are one flight master, so a route flown twice, either way, is drawn once. */
    private const float STOP_RADIUS = 0.005;

    /** A flight that took off this soon after its session began was already in the air, so where it began is unknown. */
    private const int AIRBORNE_SECONDS = 10;

    /**
     * What the world map draws for some characters a viewer may see: every cell of the finest world grid they walked
     * with the points walked on foot, mounted and in a stretch of no known mode (a session decoded before version 16, or
     * a step under a yard), the flight routes they took as straight lines from where each flight began to where it
     * landed, and the world rectangle that fits both. The ids come from the caller, which decides who may see them. Read
     * once in `PublicApi::MAX_AGE`, like the character page's own cells. The counts by mode are filtered aggregates, which
     * the query builder has no words for, so the select is raw.
     *
     * `cells` is one flat list of i, j, on foot, mounted and unknown per cell; `flights` one flat list of u0, v0, u1, v1 and
     * the times flown per route.
     *
     * @param  list<int>  $characterIds
     * @return array{grid: list<int>, cells: list<int>, flights: list<float|int>, fit: array{0: float, 1: float, 2: float, 3: float}|null}
     */
    public function handle(array $characterIds): array
    {
        sort($characterIds);

        /** @var array{grid: list<int>, cells: list<int>, flights: list<float|int>, fit: array{0: float, 1: float, 2: float, 3: float}|null} */
        return Cache::remember(self::key($characterIds), PublicApi::MAX_AGE, fn (): array => $this->read($characterIds));
    }

    /**
     * @param  list<int>  $characterIds
     * @return array{grid: list<int>, cells: list<int>, flights: list<float|int>, fit: array{0: float, 1: float, 2: float, 3: float}|null}
     */
    private function read(array $characterIds): array
    {
        $columns = WorldLayer::Paths->columns();
        $rows = intdiv($columns * 2, 3);
        $modes = fn (bool $mounted): string => '{'.implode(',', array_map(fn (MovementMode $mode): string => $mode->value, array_filter(MovementMode::cases(), fn (MovementMode $mode): bool => $mode->mounted() === $mounted))).'}';

        $cells = DB::table('path_points')
            ->selectRaw(
                'least(floor(u * ?), ?)::int as i, least(floor(v * ?), ?)::int as j, count(*) filter (where mode = any(?::varchar[])) as foot, count(*) filter (where mode = any(?::varchar[])) as mounted, count(*) filter (where mode is null) as unknown',
                [$columns, $columns - 1, $rows, $rows - 1, $modes(false), $modes(true)],
            )
            ->whereIn('character_id', $characterIds)
            ->whereBetween('u', [0, 1])
            ->whereBetween('v', [0, 1])
            ->groupByRaw('1, 2')
            ->orderByRaw('count(*) desc, 1, 2')
            ->limit(self::CELLS)
            ->get();

        $routes = DB::table('flights')
            ->selectRaw('round(u::numeric, ?)::float as u0, round(v::numeric, ?)::float as v0, round(to_u::numeric, ?)::float as u1, round(to_v::numeric, ?)::float as v1, count(*) as times', array_fill(0, 4, self::END_DECIMALS))
            ->whereIn('character_id', $characterIds)
            ->whereNotNull('u')
            ->whereNotNull('to_u')
            ->whereRaw('extract(epoch from at) - session_start >= ?', [self::AIRBORNE_SECONDS])
            ->groupByRaw('1, 2, 3, 4')
            ->orderByRaw('count(*) desc, 1, 2, 3, 4')
            ->limit(self::ROUTES)
            ->get();

        $flat = [];
        $points = [];
        foreach ($cells as $cell) {
            array_push($flat, (int) $cell->i, (int) $cell->j, (int) $cell->foot, (int) $cell->mounted, (int) $cell->unknown);
            $points[] = [((int) $cell->i + 0.5) / $columns, ((int) $cell->j + 0.5) / $rows];
        }
        $flights = self::merged(array_values($routes->map(fn (object $route): array => [(float) $route->u0, (float) $route->v0, (float) $route->u1, (float) $route->v1, (int) $route->times])->all()));
        for ($n = 0; $n < count($flights); $n += 5) {
            array_push($points, [$flights[$n], $flights[$n + 1]], [$flights[$n + 2], $flights[$n + 3]]);
        }

        return ['grid' => [$columns], 'cells' => $flat, 'flights' => $flights, 'fit' => WorldGrid::aroundPoints($points)];
    }

    /**
     * The routes with every end within `STOP_RADIUS` of another taken as one stop at their mean, weighted by times flown, and the
     * routes between the same two stops, either way, summed. A route keeps the direction of its most flown row, which comes first.
     *
     * @param  list<array{0: float, 1: float, 2: float, 3: float, 4: int}>  $rows
     * @return list<float|int>
     */
    private static function merged(array $rows): array
    {
        $stops = [];
        $stopOf = function (float $u, float $v, int $times) use (&$stops): int {
            foreach ($stops as $n => [$su, $sv, $weight]) {
                if (hypot($su / $weight - $u, $sv / $weight - $v) <= self::STOP_RADIUS) {
                    $stops[$n] = [$su + $u * $times, $sv + $v * $times, $weight + $times];

                    return $n;
                }
            }
            $stops[] = [$u * $times, $v * $times, $times];

            return count($stops) - 1;
        };

        $routes = [];
        foreach ($rows as [$u0, $v0, $u1, $v1, $times]) {
            $from = $stopOf($u0, $v0, $times);
            $to = $stopOf($u1, $v1, $times);
            if ($from === $to) {
                continue;
            }
            $key = min($from, $to).'-'.max($from, $to);
            $routes[$key] ??= [$from, $to, 0];
            $routes[$key][2] += $times;
        }

        $flat = [];
        foreach ($routes as [$from, $to, $times]) {
            array_push($flat, $stops[$from][0] / $stops[$from][2], $stops[$from][1] / $stops[$from][2], $stops[$to][0] / $stops[$to][2], $stops[$to][1] / $stops[$to][2], $times);
        }

        return $flat;
    }

    /** @param  list<int>  $characterIds */
    private static function key(array $characterIds): string
    {
        return 'map-trails:'.md5(implode(',', $characterIds));
    }
}
