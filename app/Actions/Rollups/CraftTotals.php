<?php

namespace App\Actions\Rollups;

use App\Support\Profession;
use App\Support\Rollup;
use App\Support\RollupRead;

class CraftTotals
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * What the crafts of one profession add up to in the scope. `made` is the sum of `quantity`, `sittings` the crafts
     * that start one (no `sitting_gap`), `median_gap` the median of the gaps the others have, from a percentile sketch.
     * Null while fewer than `config('scarlet.min_characters')` characters crafted.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return array{crafts: int, made: int, characters: int, sittings: int, median_gap: float|null, skill_ups: int, trivial: int}|null
     */
    public function handle(Profession $profession, int|array|null $rulesetId = null): ?array
    {
        $query = $this->read->from(Rollup::CraftsHourlyRuleset, $rulesetId)->where('profession_id', $profession->skillLine())->selectRaw(
            'coalesce(sum(crafts), 0) as crafts, coalesce(sum(made), 0) as made, distinct_count(rollup(characters)) as characters,'
            .' coalesce(sum(sittings), 0) as sittings, approx_percentile(0.5, rollup(gap_digest)) as median_gap,'
            .' coalesce(sum(skill_ups), 0) as skill_ups, coalesce(sum(trivial), 0) as trivial'
        );
        $row = $this->read->rows($query)[0] ?? null;
        if ($row === null || (int) $row['characters'] < (int) config('scarlet.min_characters')) {
            return null;
        }

        return [
            'crafts' => (int) $row['crafts'],
            'made' => (int) $row['made'],
            'characters' => (int) $row['characters'],
            'sittings' => (int) $row['sittings'],
            'median_gap' => $row['median_gap'] === null ? null : (float) $row['median_gap'],
            'skill_ups' => (int) $row['skill_ups'],
            'trivial' => (int) $row['trivial'],
        ];
    }
}
