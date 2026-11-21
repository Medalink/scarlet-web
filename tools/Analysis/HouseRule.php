<?php

namespace Scarlet\Analysis;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class HouseRule
{
    public function __construct(public string $id, public string $rule, public string $fix, public RatchetMode $mode) {}

}
