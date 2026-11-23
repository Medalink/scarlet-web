<?php

namespace App\Support\ClassPage;

/** Shares in one bar, with a key under it, at most `MOST_PARTS`. */
final readonly class Split implements Block
{
    public const int MOST_PARTS = 8;

    /** @var list<SplitPart> */
    public array $parts;

    /** @param  list<SplitPart>  $parts */
    public function __construct(array $parts)
    {
        $this->parts = array_slice($parts, 0, self::MOST_PARTS);
    }
}
