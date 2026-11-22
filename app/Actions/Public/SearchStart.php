<?php

namespace App\Actions\Public;

use App\Actions\Rollups\CreatureKills;
use App\Actions\Rollups\DeathZones;
use App\Actions\Rollups\QuestTurnIns;
use App\Support\SearchKind;

class SearchStart
{
    public function __construct(
        private readonly CreatureKills $kills,
        private readonly DeathZones $deaths,
        private readonly QuestTurnIns $quests,
    ) {}

    /**
     * What players look up: the most killed creatures, the zones with the most deaths and the most finished quests,
     * across every ruleset, each only where enough characters contributed to it. Keyed by the kind the page of its
     * rows belongs to.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function handle(int $limit = 5): array
    {
        return [
            SearchKind::Creatures->value => $this->kills->handle(null, $limit),
            SearchKind::Zones->value => $this->deaths->handle(null, null, null, $limit),
            SearchKind::Quests->value => $this->quests->handle(null, $limit),
        ];
    }
}
