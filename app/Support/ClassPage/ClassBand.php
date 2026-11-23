<?php

namespace App\Support\ClassPage;

use App\Support\PlayerClass;
use InvalidArgumentException;

/** The band of up to five of the class's figures on `/c/{name}`, linking to the class page at `href`. A figure the character's shared rows cannot answer is not in it. */
final readonly class ClassBand
{
    public const int MOST = 5;

    /** @param  list<Figure>  $figures  one to five */
    public function __construct(public PlayerClass $class, public string $href, public array $figures, public ?Icon $icon = null)
    {
        if ($figures === [] || count($figures) > self::MOST) {
            throw new InvalidArgumentException('The band holds one to five figures.');
        }
    }
}
