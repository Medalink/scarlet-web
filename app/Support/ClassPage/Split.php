<?php

namespace App\Support\ClassPage;

/** Shares in one bar, with a key under it. */
final readonly class Split implements Block
{
    /** @param  list<SplitPart>  $parts */
    public function __construct(public array $parts) {}
}
