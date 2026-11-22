<?php

namespace App\Actions\Rollups;

use App\Support\CharacterScope;
use App\Support\GatherKind;
use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Support\Facades\DB;

class GatherItemTotals
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * How many different items the attempts gave, and how many of those item rows were a container that was opened later
     * in the scope. An item fewer than the scope's threshold of characters got is not counted among the items.
     * An attempt that gave two such containers counts twice in `container_catches`: no key ties an item row to its cast.
     *
     * @param  int|list<int>|CharacterScope|null  $scope
     * @return array{items: int, container_catches: int}
     */
    public function handle(GatherKind $kind, int|array|CharacterScope|null $scope = null): array
    {
        $given = fn () => $this->read->from(Rollup::GatherItemsDailyRuleset, $scope)->where('kind', $kind->value);
        $items = $this->read->shown($given()->whereNull('container_item_id')->selectRaw('item_id')->groupBy('item_id'), $scope);
        $opened = $given()->whereNotNull('container_item_id')->select('container_item_id');
        $caught = $given()->whereNull('container_item_id')->whereIn('item_id', $opened)->selectRaw('coalesce(sum(items), 0) as caught');

        return [
            'items' => (int) $this->read->value(DB::query()->fromSub($items, 'i')->selectRaw('count(*) as items'), 'items'),
            'container_catches' => (int) $this->read->value($caught, 'caught'),
        ];
    }
}
