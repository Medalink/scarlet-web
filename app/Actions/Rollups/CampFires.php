<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;

class CampFires
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Visits by the campfire the character placed. A stay at a fire someone else lit or that was already there has no
     * campfire spell and comes back under the key 0. A tier fewer than `config('scarlet.min_characters')` characters
     * placed is left out.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return array<int, int> campfire spell id (0 for none) => visits
     */
    public function handle(int|array|null $rulesetId = null): array
    {
        $query = $this->read->from(Rollup::CampStaysHourlyRuleset, $rulesetId)
            ->selectRaw('coalesce(campfire_spell_id, 0) as spell_id, sum(visits) as visits')
            ->groupByRaw('1')
            ->havingRaw('sum(visits) > 0');

        $fires = [];
        foreach ($this->read->rows($this->read->shown($query)) as $row) {
            $fires[(int) $row['spell_id']] = (int) $row['visits'];
        }

        return $fires;
    }
}
