<?php

namespace App\Actions\Rollups;

use App\Support\CharacterScope;
use App\Support\GatherKind;
use App\Support\Rollup;
use App\Support\RollupRead;

class GatherQuantity
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * How many items the attempts gave in all, what the item rows held, not what came out of a caught container. It
     * is the Scarlet term of the items an addon counts beside it, and a total like the attempts: the whole scope shows
     * it or none of it does.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     */
    public function handle(GatherKind $kind, int|array|CharacterScope|null $scope = null): int
    {
        $query = $this->read->from(Rollup::GatherItemsDailyRuleset, $scope)->where('kind', $kind->value)->whereNull('container_item_id')
            ->selectRaw('coalesce(sum(quantity), 0) as quantity');

        return (int) $this->read->value($query, 'quantity');
    }
}
