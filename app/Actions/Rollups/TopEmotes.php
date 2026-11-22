<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;

class TopEmotes
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * The emotes characters use most, most used first. A token fewer than `config('scarlet.min_characters')`
     * characters used is left out.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return list<array<string, mixed>> token, uses, characters
     */
    public function handle(int|array|null $rulesetId = null, int $limit = 8): array
    {
        $query = $this->read->from(Rollup::EmotesDailyRuleset, $rulesetId)
            ->selectRaw('token, sum(uses) as uses, distinct_count(rollup(characters)) as characters')
            ->whereNotNull('token')
            ->groupBy('token')
            ->orderByRaw('sum(uses) desc')
            ->orderBy('token')
            ->limit($limit);

        return $this->read->rows($this->read->shown($query));
    }
}
