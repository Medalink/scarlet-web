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

    /** Flight ends closer than this share of the world are one end, so one route flown twice is drawn once. */
    private const int END_DECIMALS = 4;

    /**
     * What the world map draws for some characters a viewer may see: every cell of the finest world grid they walked
     * with the points walked on foot, mounted and in a stretch of no known mode (a session decoded before version 15, or
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
        $flights = [];
        foreach ($routes as $route) {
            array_push($flights, (float) $route->u0, (float) $route->v0, (float) $route->u1, (float) $route->v1, (int) $route->times);
            array_push($points, [(float) $route->u0, (float) $route->v0], [(float) $route->u1, (float) $route->v1]);
        }

        return ['grid' => [$columns], 'cells' => $flat, 'flights' => $flights, 'fit' => WorldGrid::aroundPoints($points)];
    }

    /** @param  list<int>  $characterIds */
    private static function key(array $characterIds): string
    {
        return 'map-trails:'.md5(implode(',', $characterIds));
    }
}
