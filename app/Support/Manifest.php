<?php

namespace App\Support;

/**
 * What an install says it holds for one character: the groups that are on and every part of them.
 *
 * @phpstan-type Entry array{id: string, session: int, group: string, lines: int, raw_bytes: int}
 */
final readonly class Manifest
{
    /**
     * @param  list<string>  $groups
     * @param  array<string, Entry>  $parts  by id
     */
    public function __construct(
        public string $character,
        public array $groups,
        public array $parts,
    ) {}
}
