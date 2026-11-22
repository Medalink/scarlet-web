<?php

namespace App\Support\ClassPage;

/** A named chart inside a module, under a small heading with its icon. */
final readonly class Titled implements Block
{
    /** @param  list<Block>  $blocks */
    public function __construct(public string $title, public array $blocks, public ?Icon $icon = null) {}
}
