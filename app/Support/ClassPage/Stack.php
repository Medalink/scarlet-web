<?php

namespace App\Support\ClassPage;

/** Shares of time by level: the demon out, the stance held, the form worn, the aura up. */
final readonly class Stack implements Block
{
    /**
     * @param  list<StackColumn>  $columns
     * @param  list<Series>  $series
     */
    public function __construct(public array $columns, public array $series, public string $label) {}
}
