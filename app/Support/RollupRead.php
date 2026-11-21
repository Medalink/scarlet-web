<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * What every read of a ruleset aggregate shares: the optional ruleset (none means every ruleset, summed)
 * and the rule that a bucket is shown only when enough characters contributed to it.
 */
class RollupRead
{
    public function from(Rollup $rollup, ?int $rulesetId): Builder
    {
        return DB::table($rollup->value)
            ->when($rulesetId !== null, fn (Builder $query): Builder => $query->where('ruleset_id', $rulesetId));
    }

    /** Hide the groups that fewer than `config('scarlet.min_characters')` characters contributed to. */
    public function shown(Builder $query): Builder
    {
        return $query->havingRaw('distinct_count(rollup(characters)) >= ?', [(int) config('scarlet.min_characters')]);
    }

    /** @return list<array<string, mixed>> */
    public function rows(Builder $query): array
    {
        return array_values($query->get()->map(fn (object $row): array => (array) $row)->all());
    }
}
