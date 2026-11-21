<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;

class DeathZones
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Where characters died, by zone. A level range narrows it to the zones a level band dies in.
     *
     * @return list<array<string, mixed>> zone_id, zone, deaths, characters
     */
    public function handle(?int $rulesetId = null, ?int $minLevel = null, ?int $maxLevel = null, int $limit = 25): array
    {
        $query = $this->read->from(Rollup::DeathsDailyRuleset, $rulesetId)
            ->selectRaw('zone_id, (select z.name from zones z where z.id = zone_id) as zone, sum(deaths) as deaths, distinct_count(rollup(characters)) as characters')
            ->whereNotNull('zone_id')
            ->when($minLevel !== null, fn ($q) => $q->where('level', '>=', $minLevel))
            ->when($maxLevel !== null, fn ($q) => $q->where('level', '<=', $maxLevel))
            ->groupBy('zone_id')
            ->orderByRaw('sum(deaths) desc')
            ->orderBy('zone_id')
            ->limit($limit);

        return $this->read->rows($this->read->shown($query));
    }
}
