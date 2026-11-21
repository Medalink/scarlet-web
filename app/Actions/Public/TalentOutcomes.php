<?php

namespace App\Actions\Public;

use App\Support\LevelBracket;
use App\Support\PageScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class TalentOutcomes
{
    /** Builds the page lists. */
    public const int BUILDS = 10;

    /**
     * What happened while each of a spec's builds was in force: from the snapshot that set the build to the next
     * snapshot of the same character. An event counts for the build it happened under, at a level inside the bracket.
     *
     * - `characters`: characters that held the build.
     * - `levels`, `median_s`: level-ups under the build for which the time spent in the level just left is complete
     *   (Scarlet saw both level-ups, with no unrecorded play between them), and the median of that time.
     * - `deaths`: deaths under the build.
     * - `runs`, `bosses`, `run_deaths`: dungeon runs under the build and their bosses and deaths.
     *
     * @param  list<int>  $tabs
     * @return list<array{hash: string, tab_points: string, characters: int, levels: int, median_s: float|null, deaths: int, runs: int, bosses: int, run_deaths: int}> most held first
     */
    public function handle(PageScope $scope, string $classSlug, array $tabs, LevelBracket $bracket): array
    {
        [$from, $to] = $bracket->range();
        $held = $this->held($scope, $classSlug, $tabs, $from, $to);
        $builds = DB::query()->fromSub($held, 'i')->groupBy('i.build_hash')
            ->selectRaw('i.build_hash, min(i.tab_points) as tab_points, count(distinct i.character_id) as characters')
            ->orderByDesc('characters')->orderBy('i.build_hash')->limit(self::BUILDS)->get();
        $hashes = array_values(array_map(fn (\stdClass $build): string => (string) $build->build_hash, $builds->all()));

        $levels = $this->under($held, 'level_ups', $hashes, $from, $to, 'e.reached')
            ->where('e.ding', true)
            ->join('character_levels as cl', fn (JoinClause $join) => $join->on('cl.character_id', '=', 'e.character_id')->whereRaw('cl.level = e.reached - 1')->where('cl.completed', true))
            ->selectRaw('i.build_hash, count(*) as n, percentile_cont(0.5) within group (order by cl.seconds) as median_s')->get()->keyBy('build_hash');
        $deaths = $this->under($held, 'deaths', $hashes, $from, $to, 'e.level')
            ->selectRaw('i.build_hash, count(*) as n')->get()->keyBy('build_hash');
        $runs = $this->under($held, 'dungeon_runs', $hashes, $from, $to, 'e.level')
            ->selectRaw('i.build_hash, count(*) as n, sum(e.bosses) as bosses, sum(e.deaths) as deaths')->get()->keyBy('build_hash');

        return array_values($builds->map(fn (\stdClass $build): array => [
            'hash' => (string) $build->build_hash,
            'tab_points' => (string) $build->tab_points,
            'characters' => (int) $build->characters,
            'levels' => (int) ($levels[$build->build_hash]->n ?? 0),
            'median_s' => isset($levels[$build->build_hash]) ? (float) $levels[$build->build_hash]->median_s : null,
            'deaths' => (int) ($deaths[$build->build_hash]->n ?? 0),
            'runs' => (int) ($runs[$build->build_hash]->n ?? 0),
            'bosses' => (int) ($runs[$build->build_hash]->bosses ?? 0),
            'run_deaths' => (int) ($runs[$build->build_hash]->deaths ?? 0),
        ])->all());
    }

    /**
     * Every counted build a character of the spec held, with the time the next one replaced it, if any.
     *
     * @param  list<int>  $tabs
     */
    private function held(PageScope $scope, string $classSlug, array $tabs, int $from, int $to): Builder
    {
        $all = DB::table('talent_snapshots as s')->where('s.counted', true)
            ->when($scope->ids !== null, fn ($query) => $query->whereIn('s.ruleset_id', (array) $scope->ids))
            ->selectRaw('s.character_id, s.at, s.level, s.build_hash, s.tab_points, s.spec_tab_id, lead(s.at) over (partition by s.character_id order by s.at) as until');

        return DB::query()->fromSub($all, 'i')->join('characters as c', 'c.id', '=', 'i.character_id')
            ->whereRaw('lower(c.class) = ?', [$classSlug])->whereIn('i.spec_tab_id', $tabs)->whereBetween('i.level', [$from, $to])
            ->selectRaw('i.character_id, i.at, i.until, i.build_hash, i.tab_points');
    }

    /**
     * The events of one fact table that happened while one of the builds was held.
     *
     * @param  list<string>  $hashes
     */
    private function under(Builder $held, string $table, array $hashes, int $from, int $to, string $levelColumn): Builder
    {
        return DB::query()->fromSub($held, 'i')->whereIn('i.build_hash', $hashes)
            ->join($table.' as e', function (JoinClause $join): void {
                $join->on('e.character_id', '=', 'i.character_id')->on('e.at', '>=', 'i.at')
                    ->where(fn ($until) => $until->whereNull('i.until')->orWhereColumn('e.at', '<', 'i.until'));
            })
            ->where('e.counted', true)->whereBetween($levelColumn, [$from, $to])->groupBy('i.build_hash');
    }
}
