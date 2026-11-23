<?php

namespace App\Support\ClassPage;

/** Shares of time by level: the demon out, the stance held, the form worn, the aura up. It draws at most `MOST_COLUMNS` columns of `MOST_SERIES` series. */
final readonly class Stack implements Block
{
    public const int EVERY_LEVEL_UP_TO = 12;

    public const int MOST_COLUMNS = 60;

    public const int MOST_SERIES = 12;

    /** @var list<StackColumn> */
    public array $columns;

    /** @var list<Series> */
    public array $series;

    /**
     * @param  list<StackColumn>  $columns
     * @param  list<Series>  $series
     */
    public function __construct(array $columns, array $series, public string $label)
    {
        $this->columns = array_slice($columns, 0, self::MOST_COLUMNS);
        $this->series = array_slice($series, 0, self::MOST_SERIES);
    }

    /** Whether the axis names the column at `$index`: every column of a short chart, else the first and each ten. */
    public function labelled(int $index): bool
    {
        $label = $this->columns[$index]->label;

        return ! is_int($label) || count($this->columns) <= self::EVERY_LEVEL_UP_TO || $index === 0 || $label % 10 === 0;
    }
}
