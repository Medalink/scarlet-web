<?php

namespace App\Actions\Rollups;

use App\Support\GatherKind;
use App\Support\Rollup;
use App\Support\RollupRead;

class GatherTotals
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * What the attempts of one gathering add up to in the scope. `seconds` is the time cast, and for fishing also the
     * gaps between casts, which is the time at the water. The median is of the looted attempts' `seconds`. Null while
     * fewer than `config('scarlet.min_characters')` characters gathered.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return array{attempts: int, looted: int, characters: int, seconds: int, median_seconds: float|null, trips: int, in_combat: int, skill_ups: int, first_strikes: int}|null
     */
    public function handle(GatherKind $kind, int|array|null $rulesetId = null): ?array
    {
        $query = $this->read->from(Rollup::GathersHourlyRuleset, $rulesetId)->where('kind', $kind->value)->selectRaw(
            'coalesce(sum(attempts), 0) as attempts, coalesce(sum(looted), 0) as looted, distinct_count(rollup(characters)) as characters,'
            .' coalesce(sum(seconds), 0) as seconds, coalesce(sum(gap_seconds), 0) as gap_seconds,'
            .' approx_percentile(0.5, rollup(looted_seconds)) as median_seconds, coalesce(sum(trips), 0) as trips,'
            .' coalesce(sum(in_combat), 0) as in_combat, coalesce(sum(skill_ups), 0) as skill_ups, coalesce(sum(first_strikes), 0) as first_strikes'
        );
        $row = $this->read->rows($query)[0] ?? null;
        if ($row === null || (int) $row['characters'] < (int) config('scarlet.min_characters')) {
            return null;
        }

        return [
            'attempts' => (int) $row['attempts'],
            'looted' => (int) $row['looted'],
            'characters' => (int) $row['characters'],
            'seconds' => (int) round((float) $row['seconds'] + ($kind->countsGaps() ? (float) $row['gap_seconds'] : 0.0)),
            'median_seconds' => $row['median_seconds'] === null ? null : (float) $row['median_seconds'],
            'trips' => (int) $row['trips'],
            'in_combat' => (int) $row['in_combat'],
            'skill_ups' => (int) $row['skill_ups'],
            'first_strikes' => (int) $row['first_strikes'],
        ];
    }
}
