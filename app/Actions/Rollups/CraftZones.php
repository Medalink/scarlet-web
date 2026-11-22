<?php

namespace App\Actions\Rollups;

use App\Support\CharacterScope;
use App\Support\Profession;
use App\Support\Rollup;
use App\Support\RollupRead;

class CraftZones
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * The zones with the most crafts. Zone ids are uiMap ids, or the negative instance id of a dungeon. A zone fewer than
     * the scope's threshold of characters crafted in is left out.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     * @return list<array{zone_id: int, name: string|null, crafts: int, characters: int|null}>
     */
    public function handle(Profession $profession, int|array|CharacterScope|null $scope = null, int $limit = 10): array
    {
        $query = $this->read->from(Rollup::CraftsHourlyRuleset, $scope)
            ->where('profession_id', $profession->skillLine())
            ->whereNotNull('zone_id')
            ->selectRaw('zone_id, (select z.name from zones z where z.id = zone_id) as name, sum(crafts) as crafts, distinct_count(rollup(characters)) as characters')
            ->groupBy('zone_id')
            ->orderByRaw('sum(crafts) desc')
            ->orderBy('zone_id')
            ->limit($limit);

        return array_map(fn (array $row): array => [
            'zone_id' => (int) $row['zone_id'],
            'name' => $row['name'] === null ? null : (string) $row['name'],
            'crafts' => (int) $row['crafts'],
            'characters' => (int) $row['characters'],
        ], $this->read->rows($this->read->shown($query, $scope)));
    }
}
