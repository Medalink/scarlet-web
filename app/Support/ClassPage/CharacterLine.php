<?php

namespace App\Support\ClassPage;

/** The line under the class's name: the class icon, the character, its level, its build and the hours it has played. */
final readonly class CharacterLine
{
    public function __construct(public string $name, public int $level, public int|float $hours, public ?Build $build = null, public ?Icon $icon = null) {}
}
