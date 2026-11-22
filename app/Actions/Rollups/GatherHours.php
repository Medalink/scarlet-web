<?php

namespace App\Actions\Rollups;

use App\Support\CharacterScope;
use App\Support\GatherKind;
use App\Support\Rollup;
use App\Support\RollupRead;

class GatherHours
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Attempt starts by weekday (0 is Sunday) and hour of the day, in UTC. A cell fewer than
     * the scope's threshold of characters started an attempt in is left out.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     * @return list<array{weekday: int, hour: int, gathers: int}>
     */
    public function handle(GatherKind $kind, int|array|CharacterScope|null $scope = null): array
    {
        $query = $this->read->from(Rollup::GathersHourlyRuleset, $scope)
            ->where('kind', $kind->value)
            ->selectRaw("extract(dow from bucket at time zone 'UTC')::int as weekday, extract(hour from bucket at time zone 'UTC')::int as hour, sum(attempts) as gathers")
            ->groupByRaw('1, 2')
            ->havingRaw('sum(attempts) > 0')
            ->orderByRaw('1, 2');

        return array_map(
            fn (array $row): array => ['weekday' => (int) $row['weekday'], 'hour' => (int) $row['hour'], 'gathers' => (int) $row['gathers']],
            $this->read->rows($this->read->shown($query, $scope)),
        );
    }
}
