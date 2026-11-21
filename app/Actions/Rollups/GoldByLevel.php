<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;

class GoldByLevel
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Copper earned and spent at each level, from the character's own purse (not a transfer from a mail or a trade).
     *
     *
     * @param  int|list<int>|null  $rulesetId
     * @return list<array<string, mixed>> level, earned, spent, characters
     */
    public function handle(int|array|null $rulesetId = null): array
    {
        $query = $this->read->from(Rollup::MoneyDailyRuleset, $rulesetId)
            ->selectRaw('level, sum(earned) as earned, sum(spent) as spent, distinct_count(rollup(characters)) as characters')
            ->groupBy('level')
            ->orderBy('level');

        return $this->read->rows($this->read->shown($query));
    }
}
