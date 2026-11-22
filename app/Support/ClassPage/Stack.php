<?php

namespace App\Support\ClassPage;

/** Shares of time by level: the demon out, the stance held, the form worn, the aura up. */
final readonly class Stack implements Block
{
    public const int EVERY_LEVEL_UP_TO = 12;

    /**
     * @param  list<StackColumn>  $columns
     * @param  list<Series>  $series
     */
    public function __construct(public array $columns, public array $series, public string $label) {}

    /** Whether the axis names the column at `$index`: every column of a short chart, else the first and each ten. */
    public function labelled(int $index): bool
    {
        $label = $this->columns[$index]->label;

        return ! is_int($label) || count($this->columns) <= self::EVERY_LEVEL_UP_TO || $index === 0 || $label % 10 === 0;
    }
}
