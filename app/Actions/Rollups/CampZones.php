<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;

class CampZones
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * The zones with the most visits to a campfire. Zone ids are uiMap ids, or the negative instance id of a dungeon.
     * A zone fewer than `config('scarlet.min_characters')` characters stayed in is left out.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return list<array{zone_id: int, name: string|null, visits: int, seconds: int, characters: int}>
     */
    public function handle(int|array|null $rulesetId = null, int $limit = 10): array
    {
        $query = $this->read->from(Rollup::CampStaysHourlyRuleset, $rulesetId)
            ->selectRaw('zone_id, (select z.name from zones z where z.id = zone_id) as name, sum(visits) as visits, sum(seconds) as seconds, distinct_count(rollup(characters)) as characters')
            ->whereNotNull('zone_id')
            ->groupBy('zone_id')
            ->havingRaw('sum(visits) > 0')
            ->orderByRaw('sum(visits) desc')
            ->orderBy('zone_id')
            ->limit($limit);

        return array_map(fn (array $row): array => [
            'zone_id' => (int) $row['zone_id'],
            'name' => $row['name'] === null ? null : (string) $row['name'],
            'visits' => (int) $row['visits'],
            'seconds' => (int) $row['seconds'],
            'characters' => (int) $row['characters'],
        ], $this->read->rows($this->read->shown($query)));
    }
}
