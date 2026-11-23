<?php

namespace App\Actions\Rollups;

use App\Support\FreeForAllCounts;
use App\Support\LootSourceKind;
use App\Support\LootSourceRef;

class FreeForAllArm
{
    public function __construct(private readonly FreeForAllArms $arms) {}

    /**
     * The free-for-all arm of one source when it counts one reader (`LootArm::overDays`, docs/LOOT.md, Units of counting,
     * rule 6), with what each item listed in the windows the arm counts; null for a source with no free-for-all windows, or
     * whose arm counts every window, which the aggregates already sum. The creature page and the API ask for this arm
     * of their one source; the tables that list many sources ask `FreeForAllArms`.
     */
    public function handle(string $sourceKind, int $sourceId): ?FreeForAllCounts
    {
        $source = new LootSourceRef(LootSourceKind::from($sourceKind), $sourceId);

        return $this->arms->handle([$source])[$source->key()] ?? null;
    }
}
