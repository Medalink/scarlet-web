<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Support\Facades\DB;

class RankByLevel
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * The median over characters of the highest rank each had at each character level, ascending, from a by-character
     * aggregate with `level` and `max_skill` whose rows of one profession have `$column` equal to `$value`. A level fewer
     * than `config('scarlet.min_characters')` characters reached a rank at is left out.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return list<array{level: int, median_skill: float|null, characters: int|null}>
     */
    public function handle(Rollup $rollup, string $column, string|int $value, int|array|null $rulesetId = null): array
    {
        $best = $this->read->from($rollup, $rulesetId)
            ->where($column, $value)
            ->whereNotNull('level')
            ->whereNotNull('max_skill')
            ->selectRaw('level, character_id, max(max_skill) as skill')
            ->groupBy('level', 'character_id');
        $query = DB::query()->fromSub($best, 'b')
            ->selectRaw('level, count(*) as characters, percentile_cont(0.5) within group (order by skill) as median_skill')
            ->groupBy('level')
            ->havingRaw('count(*) >= ?', [(int) config('scarlet.min_characters')])
            ->orderBy('level');

        return array_map(fn (array $row): array => [
            'level' => (int) $row['level'],
            'median_skill' => $row['median_skill'] === null ? null : (float) $row['median_skill'],
            'characters' => (int) $row['characters'],
        ], $this->read->rows($query));
    }
}
