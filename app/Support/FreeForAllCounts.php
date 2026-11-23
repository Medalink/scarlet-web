<?php

namespace App\Support;

/** The free-for-all arm of one source that counts one reader, with what each item listed in the windows it counts (App\Actions\Rollups\FreeForAllArm). */
final readonly class FreeForAllCounts
{
    /**
     * @param  array<int, array{drops: int, quantity: int}>  $items  by item id, for the items the arm's windows listed
     * @param  array<int, array{drops: int, quantity: int}>  $raw  by item id, what every free-for-all window of the source listed, for the items the read was for
     */
    public function __construct(
        public LootArm $arm,
        public array $items,
        public array $raw = [],
    ) {}
}
