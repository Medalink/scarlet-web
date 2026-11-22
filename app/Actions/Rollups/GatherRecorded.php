<?php

namespace App\Actions\Rollups;

use App\Support\CharacterScope;
use App\Support\GatherKind;
use App\Support\Rollup;
use App\Support\RollupRead;

class GatherRecorded
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Whether any character gathered in the scope, whatever the threshold. Only the addon read asks: a scope no Scarlet
     * character gathered in has no figure of Scarlet's that a total could give away.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     */
    public function handle(GatherKind $kind, int|array|CharacterScope|null $scope = null): bool
    {
        $query = $this->read->from(Rollup::GathersHourlyRuleset, $scope)->where('kind', $kind->value)->selectRaw('distinct_count(rollup(characters)) as characters');

        return (int) ($this->read->rows($query)[0]['characters'] ?? 0) > 0;
    }
}
