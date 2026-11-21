<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;

class TopKillers
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * What killed characters most often. `kind` is the decoder's killer kind (a creature, a player, the environment).
     *
     * @return list<array<string, mixed>> kind, killer, deaths, characters
     */
    public function handle(?int $rulesetId = null, int $limit = 25): array
    {
        $query = $this->read->from(Rollup::DeathsDailyRuleset, $rulesetId)
            ->selectRaw('killer_kind as kind, killer, sum(deaths) as deaths, distinct_count(rollup(characters)) as characters')
            ->whereNotNull('killer')
            ->groupBy('killer_kind', 'killer')
            ->orderByRaw('sum(deaths) desc')
            ->orderBy('killer')
            ->limit($limit);

        return $this->read->rows($this->read->shown($query));
    }
}
