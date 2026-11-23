<?php

namespace App\Support;

use Generator;
use IteratorAggregate;
use RuntimeException;

/**
 * Newline-delimited JSON rows in a work file, read a row at a time. The file is removed when the object is dropped.
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
final class RowFile implements IteratorAggregate
{
    public function __construct(private readonly string $file) {}

    public function __destruct()
    {
        is_file($this->file) && unlink($this->file);
    }

    /** @return Generator<int, array<string, mixed>> */
    public function getIterator(): Generator
    {
        $handle = fopen($this->file, 'rb');
        if ($handle === false) {
            throw new RuntimeException("cannot read {$this->file}");
        }
        try {
            while (($line = fgets($handle)) !== false) {
                if (trim($line) === '') {
                    continue;
                }
                /** @var array<string, mixed> $row */
                $row = json_decode($line, associative: true, flags: JSON_THROW_ON_ERROR);
                yield $row;
            }
        } finally {
            fclose($handle);
        }
    }
}
