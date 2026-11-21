<?php

namespace App\Actions\Maps;

use App\Actions\Ingest\MeasureDistance;
use App\Actions\Rollups\RefreshAggregates;
use Illuminate\Support\Facades\DB;

/**
 * Fills `u, v` on the rows that have none, from `map_rects` (docs/DESIGN.md, Maps). It touches only rows whose map has a
 * rectangle, so it can run again whenever rectangles arrive, and a second run finds nothing to do. It works in
 * windows of seven days, so one statement stays small, then brings the world heat up to date over what it changed.
 * Must not be called from a transaction (the refresh is a CALL).
 */
class PlaceWorldPoints
{
    private const int WINDOW = 7 * 86400;

    /** The three tables, with the columns that hold their map, x and y; a sighting's cell is the middle of its cell. */
    private const array TABLES = [
        'path_points' => ['map', 't.x / 10000.0', 't.y / 10000.0'],
        'deaths' => ['map_id', 't.x / 10000.0', 't.y / 10000.0'],
        'creature_sightings' => ['map', '(t.cell_x + 0.5) * 0.015', '(t.cell_y + 0.5) * 0.015'],
    ];

    public function __construct(private readonly RefreshAggregates $refresh, private readonly MeasureDistance $distance) {}

    /** @return array<string, int> rows placed per table */
    public function handle(): array
    {
        $placed = [];
        $from = null;
        $until = null;

        foreach (self::TABLES as $table => [$map, $x, $y]) {
            $placed[$table] = 0;
            $span = DB::table($table.' as t')
                ->join('map_rects as r', 'r.ui_map_id', '=', 't.'.$map)
                ->whereNull('t.u')
                ->selectRaw('extract(epoch from min(t.at)) as first, extract(epoch from max(t.at)) as last')
                ->first();
            if ($span === null || $span->first === null) {
                continue;
            }
            $first = (int) floor((float) $span->first);
            $last = (int) ceil((float) $span->last);
            $from = min($from ?? $first, $first);
            $until = max($until ?? $last, $last);

            for ($start = $first; $start <= $last; $start += self::WINDOW) {
                $placed[$table] += DB::update(
                    "update {$table} t set u = r.u0 + {$x} * (r.u1 - r.u0), v = r.v0 + {$y} * (r.v1 - r.v0)
                     from map_rects r
                     where r.ui_map_id = t.{$map} and t.u is null and t.at >= to_timestamp(?) and t.at < to_timestamp(?)",
                    [$start, $start + self::WINDOW],
                );
            }
        }

        if ($from !== null && $until !== null) {
            $this->refresh->handle($from, $until);
        }

        // Sessions stored before their points could be placed have no distance yet.
        $placed['sessions'] = 0;
        foreach (DB::table('sessions')->whereNull('distance')->get(['character_id', 'session_start']) as $session) {
            $this->distance->handle((int) $session->character_id, (int) $session->session_start);
            $placed['sessions']++;
        }

        return $placed;
    }
}
