<?php

namespace App\Actions\Rollups;

use App\Support\GatherKind;
use App\Support\Rollup;

class GatherByLevel
{
    public function __construct(private readonly RankByLevel $ranks) {}

    /**
     * The median over characters of the highest rank each had at each character level, ascending. A level fewer than
     * `config('scarlet.min_characters')` characters gathered at is left out.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return list<array{level: int, median_skill: float|null, characters: int|null}>
     */
    public function handle(GatherKind $kind, int|array|null $rulesetId = null): array
    {
        return $this->ranks->handle(Rollup::GathersDailyCharacter, 'kind', $kind->value, $rulesetId);
    }
}
