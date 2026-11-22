<?php

namespace App\Support\ClassPage;

use App\Support\PlayerClass;
use InvalidArgumentException;

/** The band of five of the class's figures on `/c/{name}`, linking to the class page at `href`. */
final readonly class ClassBand
{
    /** @param  list<Figure>  $figures */
    public function __construct(public PlayerClass $class, public string $href, public array $figures, public ?Icon $icon = null)
    {
        if (count($figures) !== 5) {
            throw new InvalidArgumentException('The band holds five figures.');
        }
    }
}
