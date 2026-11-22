<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;

class ScopeCharacters
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * How many characters have any play in the scope, from `play_hourly_ruleset`. Null while fewer than
     * `config('scarlet.min_characters')` do.
     *
     * @param  int|list<int>|null  $rulesetId
     */
    public function handle(int|array|null $rulesetId = null): ?int
    {
        $characters = $this->read->rows($this->read->shown($this->read->from(Rollup::PlayHourlyRuleset, $rulesetId)->selectRaw('distinct_count(rollup(characters)) as characters')))[0]['characters'] ?? null;

        return $characters === null ? null : (int) $characters;
    }
}
