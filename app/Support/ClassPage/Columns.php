<?php

namespace App\Support\ClassPage;

use InvalidArgumentException;

/** Two to four named charts side by side, such as the four elements' totem ranks. */
final readonly class Columns implements Block
{
    /** @param  list<Titled>  $columns */
    public function __construct(public array $columns)
    {
        if (count($columns) < 2 || count($columns) > 4) {
            throw new InvalidArgumentException('Columns holds two to four charts.');
        }
    }
}
