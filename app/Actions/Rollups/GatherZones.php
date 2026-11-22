<?php

namespace App\Actions\Rollups;

use App\Support\CharacterScope;
use App\Support\GatherKind;
use App\Support\Rollup;
use App\Support\RollupRead;

class GatherZones
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * The zones with the most looted attempts. Zone ids are uiMap ids, or the negative instance id of a dungeon. A zone
     * fewer than the scope's threshold of characters gathered in is left out.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     * @return list<array{zone_id: int, name: string|null, gathers: int, looted: int, characters: int|null}>
     */
    public function handle(GatherKind $kind, int|array|CharacterScope|null $scope = null, int $limit = 10): array
    {
        $query = $this->read->from(Rollup::GathersHourlyRuleset, $scope)
            ->where('kind', $kind->value)
            ->whereNotNull('zone_id')
            ->selectRaw('zone_id, (select z.name from zones z where z.id = zone_id) as name, sum(attempts) as gathers, sum(looted) as looted, distinct_count(rollup(characters)) as characters')
            ->groupBy('zone_id')
            ->orderByRaw('sum(looted) desc')
            ->orderByRaw('sum(attempts) desc')
            ->orderBy('zone_id')
            ->limit($limit);

        return array_map(fn (array $row): array => [
            'zone_id' => (int) $row['zone_id'],
            'name' => $row['name'] === null ? null : (string) $row['name'],
            'gathers' => (int) $row['gathers'],
            'looted' => (int) $row['looted'],
            'characters' => (int) $row['characters'],
        ], $this->read->rows($this->read->shown($query, $scope)));
    }
}
