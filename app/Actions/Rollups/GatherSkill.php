<?php

namespace App\Actions\Rollups;

use App\Support\GatherKind;
use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Support\Facades\DB;

class GatherSkill
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * The median over characters of the highest rank each had in the scope, and how many of them reached the cap. A
     * character with no rank on record is left out. Both are null while fewer than `config('scarlet.min_characters')`
     * characters have one.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return array{median_skill: float|null, at_cap: int|null}
     */
    public function handle(GatherKind $kind, int|array|null $rulesetId = null): array
    {
        $best = $this->read->from(Rollup::GathersDailyCharacter, $rulesetId)
            ->where('kind', $kind->value)
            ->selectRaw('character_id, max(max_skill) as skill')
            ->groupBy('character_id');
        $query = DB::query()->fromSub($best, 'b')
            ->whereNotNull('skill')
            ->selectRaw('count(*) as characters, percentile_cont(0.5) within group (order by skill) as median_skill, count(*) filter (where skill >= ?) as at_cap', [GatherKind::SKILL_CAP]);
        $row = $this->read->rows($query)[0] ?? null;
        if ($row === null || (int) $row['characters'] < (int) config('scarlet.min_characters')) {
            return ['median_skill' => null, 'at_cap' => null];
        }

        return ['median_skill' => (float) $row['median_skill'], 'at_cap' => (int) $row['at_cap']];
    }
}
