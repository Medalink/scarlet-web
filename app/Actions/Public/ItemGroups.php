<?php

namespace App\Actions\Public;

use App\Actions\Rollups\ItemReceivers;
use App\Actions\Rollups\ItemRolls;

class ItemGroups
{
    public function __construct(
        private readonly ItemRolls $rolls,
        private readonly ItemReceivers $receivers,
    ) {}

    /**
     * What groups did with one item (docs/LOOT.md, Item page): the uploaders' own rolls of it, and who received it by
     * class, what other members of a group received and what the uploader received, never a name. Every ruleset.
     *
     * @return array{rolls: array{need: int, greed: int, pass: int, all_passed: int}|null, receivers: list<array{class: string, received: int, rolled: int, won: int}>}
     */
    public function handle(int $itemId): array
    {
        return ['rolls' => $this->rolls->handle($itemId), 'receivers' => $this->receivers->handle($itemId)];
    }
}
