<?php

namespace App\Support\ClassPage;

/**
 * The bag's slots, with the item in the ones that hold it. `noun` names what they hold: "soul shard". A bag draws at
 * most `MOST_SLOTS` slots, the comp's, whatever number a row stores; the count itself is written beside the bag.
 */
final readonly class Bag implements Block
{
    public const int MOST_SLOTS = 32;

    public int $slots;

    /** @param  list<BagItem>  $items */
    public function __construct(int $slots, public array $items, public string $noun)
    {
        $this->slots = max(0, min($slots, self::MOST_SLOTS));
    }
}
