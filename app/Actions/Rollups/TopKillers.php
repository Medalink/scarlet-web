<?php

namespace App\Actions\Rollups;

use App\Support\KillerKind;
use App\Support\KillerRows;
use App\Support\NameKind;
use App\Support\Rollup;
use App\Support\RollupRead;

class TopKillers
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * What killed characters most often. `kind` is the decoder's killer kind (a creature, a player, the environment);
     * `npc_id` is the creature's id when the killer is one the dictionary knows by name.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return list<array<string, mixed>> kind, killer, npc_id, deaths, characters
     */
    public function handle(int|array|null $rulesetId = null, int $limit = 25): array
    {
        $query = $this->read->from(Rollup::DeathsDailyRuleset, $rulesetId)
            ->selectRaw('killer_kind as kind, killer, sum(deaths) as deaths, distinct_count(rollup(characters)) as characters')
            ->selectRaw('(select n.id from names n where n.kind = ? and killer_kind is distinct from ? and n.name = killer order by n.votes desc limit 1) as npc_id', [NameKind::Npc->value, KillerKind::Player->value])
            ->whereNotNull('killer')
            ->groupBy('killer_kind', 'killer')
            ->orderByRaw('sum(deaths) desc')
            ->orderBy('killer')
            ->limit($limit);

        return KillerRows::shown($this->read->rows($this->read->shown($query)));
    }
}
