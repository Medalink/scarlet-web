<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;

class QuestTurnIns
{
    public function __construct(private readonly RollupRead $read) {}

    /** @return list<array<string, mixed>> quest_id, name, turn_ins, accepts, abandons, xp, characters */
    public function handle(?int $rulesetId = null, int $limit = 50): array
    {
        $query = $this->read->from(Rollup::QuestsDailyRuleset, $rulesetId)
            ->selectRaw("quest_id, (select n.name from names n where n.kind = 'quest' and n.id = quest_id order by n.votes desc, n.name limit 1) as name")
            ->selectRaw('sum(turn_ins) as turn_ins, sum(accepts) as accepts, sum(abandons) as abandons, sum(xp) as xp, distinct_count(rollup(characters)) as characters')
            ->groupBy('quest_id')
            ->orderByRaw('sum(turn_ins) desc')
            ->orderBy('quest_id')
            ->limit($limit);

        return $this->read->rows($this->read->shown($query));
    }
}
