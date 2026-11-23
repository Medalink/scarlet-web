<?php

namespace App\Actions\Maps;

use App\Actions\Ingest\MeasureDistance;
use App\Actions\Rollups\RefreshAggregates;
use App\Support\DecompressionLimit;
use Illuminate\Support\Facades\DB;

/**
 * Fills `u, v` on the rows that have none, from `map_rects` (docs/DESIGN.md, Maps). It touches only rows whose map has a
 * rectangle, so it can run again whenever rectangles arrive, and a second run finds nothing to do. It works in
 * windows of seven days, so one statement stays small, then brings the world heat up to date over what it changed and
 * measures the sessions whose path points it placed, and any session with placed points and no distance yet.
 * `maps:load-rects` runs it after loading, and the deploy runs that, so a rectangle added to docs/map-rects.tsv
 * reaches the points recorded before it. Must not be called from a transaction (the refresh is a CALL).
 */
class PlaceWorldPoints
{
    private const int WINDOW = 7 * 86400;

    private const string WALKED = 'path_points';

    /**
     * The placed positions, by name: the table, the columns that hold its map, x and y, x and y as a share of the map,
     * and the columns that take `u` and `v`. A sighting's position is the middle of its cell, and a flight has two ends.
     * A row with no x or y cannot be placed and is left alone.
     */
    private const array TABLES = [
        'path_points' => ['path_points', 'map', 'x', 'y', 't.x / 10000.0', 't.y / 10000.0', 'u', 'v'],
        'camp_stays' => ['camp_stays', 'map', 'x', 'y', 't.x / 10000.0', 't.y / 10000.0', 'u', 'v'],
        'gathers' => ['gathers', 'map', 'x', 'y', 't.x / 10000.0', 't.y / 10000.0', 'u', 'v'],
        'loot_opens' => ['loot_opens', 'map', 'x', 'y', 't.x / 10000.0', 't.y / 10000.0', 'u', 'v'],
        'deaths' => ['deaths', 'map_id', 'x', 'y', 't.x / 10000.0', 't.y / 10000.0', 'u', 'v'],
        'creature_sightings' => ['creature_sightings', 'map', 'cell_x', 'cell_y', '(t.cell_x + 0.5) * 0.015', '(t.cell_y + 0.5) * 0.015', 'u', 'v'],
        'flights' => ['flights', 'map', 'x', 'y', 't.x / 10000.0', 't.y / 10000.0', 'u', 'v'],
        'flight_ends' => ['flights', 'to_map', 'to_x', 'to_y', 't.to_x / 10000.0', 't.to_y / 10000.0', 'to_u', 'to_v'],
    ];

    public function __construct(private readonly RefreshAggregates $refresh, private readonly MeasureDistance $distance) {}

    /** @return array<string, int> rows placed per position of TABLES */
    public function handle(): array
    {
        $placed = [];
        $from = null;
        $until = null;
        $walked = [];

        foreach (self::TABLES as $name => [$table, $map, $across, $down, $x, $y, $u, $v]) {
            $placed[$name] = 0;
            $unplaced = fn () => DB::table($table.' as t')
                ->join('map_rects as r', 'r.ui_map_id', '=', 't.'.$map)
                ->whereNull('t.'.$u)
                ->whereNotNull('t.'.$across)
                ->whereNotNull('t.'.$down);
            $span = $unplaced()->selectRaw('extract(epoch from min(t.at)) as first, extract(epoch from max(t.at)) as last')->first();
            if ($span === null || $span->first === null) {
                continue;
            }
            if ($name === self::WALKED) {
                $walked = $unplaced()->distinct()->get(['t.character_id', 't.session_start'])->all();
            }
            $first = (int) floor((float) $span->first);
            $last = (int) ceil((float) $span->last);
            $from = min($from ?? $first, $first);
            $until = max($until ?? $last, $last);

            for ($start = $first; $start <= $last; $start += self::WINDOW) {
                $placed[$name] += DB::transaction(function () use ($table, $map, $across, $down, $x, $y, $u, $v, $start): int {
                    DecompressionLimit::lift();

                    return DB::update(
                        "update {$table} t set {$u} = r.u0 + {$x} * (r.u1 - r.u0), {$v} = r.v0 + {$y} * (r.v1 - r.v0)
                         from map_rects r
                         where r.ui_map_id = t.{$map} and t.{$u} is null and t.{$across} is not null and t.{$down} is not null
                           and t.at >= to_timestamp(?) and t.at < to_timestamp(?)",
                        [$start, $start + self::WINDOW],
                    );
                });
            }
        }

        if ($from !== null && $until !== null) {
            $this->refresh->handle($from, $until);
        }

        $unmeasured = DB::table('sessions as s')
            ->whereNull('s.distance')
            ->whereExists(fn ($points) => $points->selectRaw('1')->from(self::WALKED.' as p')
                ->whereColumn('p.character_id', 's.character_id')
                ->whereColumn('p.session_start', 's.session_start')
                ->whereNotNull('p.u'))
            ->get(['s.character_id', 's.session_start'])
            ->all();
        $sessions = [];
        foreach ([...$walked, ...$unmeasured] as $session) {
            $sessions[$session->character_id.':'.$session->session_start] = $session;
        }
        foreach ($sessions as $session) {
            $this->distance->handle((int) $session->character_id, (int) $session->session_start);
        }
        $placed['sessions'] = count($sessions);

        return $placed;
    }
}
