<?php

namespace App\Support;

/** What a read covers when it is about particular characters, not the rulesets: these characters, within these rulesets (docs/DESIGN.md, Rollups). */
final readonly class CharacterScope
{
    /**
     * @param  list<int>  $characterIds  an empty list matches no character
     * @param  int|list<int>|null  $rulesetIds  one ruleset, several or null for all
     */
    public function __construct(
        public array $characterIds,
        public int|array|null $rulesetIds,
    ) {}

    /** The ids as a PostgreSQL array literal, for one binding. */
    public function characterArray(): string
    {
        return '{'.implode(',', array_map(fn (int $id): string => (string) $id, $this->characterIds)).'}';
    }
}
