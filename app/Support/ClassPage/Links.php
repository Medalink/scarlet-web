<?php

namespace App\Support\ClassPage;

/** Where the line of navigation under the head goes: Character, the class page, Battlegrounds. A link left null is not drawn. */
final readonly class Links
{
    public function __construct(public string $character, public ?string $classPage, public ?string $build = null, public ?string $battlegrounds = null) {}
}
