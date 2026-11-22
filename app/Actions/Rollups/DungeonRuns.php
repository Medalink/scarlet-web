<?php

namespace App\Actions\Rollups;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class DungeonRuns
{
    /**
     * Runs per instance with the time inside as median and quartiles. A run that carries on the run before it
     * (the character left and came back, or logged out inside) is one run with the time and bosses of both, so
     * this reads the runs themselves: the continuous aggregate counts the pieces. An instance is shown when
     * `config('scarlet.min_characters')` characters ran it. The zone id and name are the ones most runs were recorded
     * under: a dungeon with its own uiMapID is stored under that id, not under its negative instance id.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return list<array<string, mixed>> instance_id, zone_id, name, runs, characters, deaths, bosses, q1, median, q3
     */
    public function handle(int|array|null $rulesetId = null): array
    {
        $pieces = DB::table('dungeon_runs')
            ->where('counted', true)
            ->when(is_int($rulesetId), fn (Builder $query): Builder => $query->where('ruleset_id', $rulesetId))
            ->when(is_array($rulesetId), fn (Builder $query): Builder => $query->whereIn('ruleset_id', $rulesetId))
            ->selectRaw('instance_id, zone_id, name, character_id, inside_ms, bosses, deaths')
            ->selectRaw('sum((not continues_previous)::int) over (partition by character_id, instance_id order by at, run_index) as run');
        $runs = DB::query()->fromSub($pieces, 'p')
            ->groupBy('instance_id', 'character_id', 'run')
            ->selectRaw('instance_id, character_id, sum(inside_ms) / 1000.0 as inside, sum(bosses) as bosses, sum(deaths) as deaths')
            ->selectRaw('mode() within group (order by zone_id) as zone_id, mode() within group (order by name) as name');

        $rows = DB::query()->fromSub($runs, 'r')
            ->selectRaw('instance_id, coalesce(mode() within group (order by zone_id), -instance_id) as zone_id')
            ->selectRaw('coalesce(mode() within group (order by r.name), (select z.name from zones z where z.instance_id = r.instance_id order by z.id limit 1)) as name')
            ->selectRaw('count(*) as runs, count(distinct character_id) as characters, sum(deaths) as deaths, sum(bosses) as bosses')
            ->selectRaw('percentile_cont(0.25) within group (order by inside) as q1')
            ->selectRaw('percentile_cont(0.5) within group (order by inside) as median')
            ->selectRaw('percentile_cont(0.75) within group (order by inside) as q3')
            ->groupBy('instance_id')
            ->havingRaw('count(distinct character_id) >= ?', [(int) config('scarlet.min_characters')])
            ->orderByRaw('count(*) desc')
            ->orderBy('instance_id')
            ->get();

        return array_values($rows->map(fn (object $row): array => (array) $row)->all());
    }
}
