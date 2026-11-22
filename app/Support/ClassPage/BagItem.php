<?php

namespace App\Support\ClassPage;

/** An item the bags hold and how many slots it fills. */
final readonly class BagItem
{
    public function __construct(public ?Icon $icon, public int $slots) {}
}
