<?php

namespace App\Support\ClassPage;

/** The bag's slots, with the item in the ones that hold it. `noun` names what they hold: "soul shard". */
final readonly class Bag implements Block
{
    /** @param  list<BagItem>  $items */
    public function __construct(public int $slots, public array $items, public string $noun) {}
}
