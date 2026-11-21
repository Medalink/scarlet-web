<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;

class CreatureKills
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Kills per creature. `own` counts the kills the character got credit for.
     *
     * @return list<array<string, mixed>> npc_id, name, kills, own, characters
     */
    public function handle(?int $rulesetId = null, int $limit = 50): array
    {
        $query = $this->read->from(Rollup::KillsDailyRuleset, $rulesetId)
            ->selectRaw("npc_id, (select n.name from names n where n.kind = 'npc' and n.id = npc_id order by n.votes desc, n.name limit 1) as name")
            ->selectRaw('sum(kills) as kills, sum(own) as own, distinct_count(rollup(characters)) as characters')
            ->groupBy('npc_id')
            ->orderByRaw('sum(kills) desc')
            ->orderBy('npc_id')
            ->limit($limit);

        return $this->read->rows($this->read->shown($query));
    }
}
