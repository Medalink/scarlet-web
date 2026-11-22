<?php

namespace App\Actions\Rollups;

use App\Support\CharacterScope;
use App\Support\Rollup;
use App\Support\RollupRead;

class CampHours
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Visit starts by weekday (0 is Sunday) and hour of the day, in UTC. A cell fewer than
     * the scope's threshold of characters started a visit in is left out.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     * @return list<array{weekday: int, hour: int, visits: int}>
     */
    public function handle(int|array|CharacterScope|null $scope = null): array
    {
        $query = $this->read->from(Rollup::CampStaysHourlyRuleset, $scope)
            ->selectRaw("extract(dow from bucket at time zone 'UTC')::int as weekday, extract(hour from bucket at time zone 'UTC')::int as hour, sum(visits) as visits")
            ->groupByRaw('1, 2')
            ->havingRaw('sum(visits) > 0')
            ->orderByRaw('1, 2');

        return array_map(
            fn (array $row): array => ['weekday' => (int) $row['weekday'], 'hour' => (int) $row['hour'], 'visits' => (int) $row['visits']],
            $this->read->rows($this->read->shown($query, $scope)),
        );
    }
}
