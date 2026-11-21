<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;

class Professions
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Skill-ups and the highest rank seen, by skill. `kind` is the decoder's skill kind (profession, weapon, ...).
     *
     * @return list<array<string, mixed>> kind, skill, skill_id, ups, best_rank, characters
     */
    public function handle(?int $rulesetId = null): array
    {
        $query = $this->read->from(Rollup::SkillsDailyRuleset, $rulesetId)
            ->selectRaw('kind, skill, skill_id, sum(ups) as ups, max(best_rank) as best_rank, distinct_count(rollup(characters)) as characters')
            ->groupBy('kind', 'skill', 'skill_id')
            ->orderBy('kind')
            ->orderByRaw('sum(ups) desc')
            ->orderBy('skill');

        return $this->read->rows($this->read->shown($query));
    }
}
