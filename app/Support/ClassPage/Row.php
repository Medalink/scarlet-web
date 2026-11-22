<?php

namespace App\Support\ClassPage;

/** One or more modules side by side on the sheet; their spans add up to twelve, or all take a third. */
final readonly class Row
{
    /** @param  list<Module>  $modules */
    public function __construct(public array $modules) {}
}
