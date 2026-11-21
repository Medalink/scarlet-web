<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;

class PlayTimeByWeekday
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Played seconds by weekday (0 is Sunday) and hour of the day, in UTC. AFK time is reported apart.
     *
     *
     * @param  int|list<int>|null  $rulesetId
     * @return list<array<string, mixed>> weekday, hour, seconds, afk_seconds, characters
     */
    public function handle(int|array|null $rulesetId = null): array
    {
        $query = $this->read->from(Rollup::PlayHourlyRuleset, $rulesetId)
            ->selectRaw("extract(dow from bucket at time zone 'UTC')::int as weekday, extract(hour from bucket at time zone 'UTC')::int as hour")
            ->selectRaw('(sum(ms) / 1000.0)::float as seconds, (sum(afk_ms) / 1000.0)::float as afk_seconds, distinct_count(rollup(characters)) as characters')
            ->groupByRaw('1, 2')
            ->orderByRaw('1, 2');

        return $this->read->rows($this->read->shown($query));
    }
}
