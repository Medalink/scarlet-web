<?php

namespace App\Support\ClassPage;

/** A module for one talent of the latest build, tagged with the points spent: "2 of 2". */
final readonly class TalentModule
{
    /** @param  list<Block>  $blocks */
    public function __construct(
        public string $name,
        public int $points,
        public int $max,
        public array $blocks,
        public ?Icon $icon = null,
        public ?string $note = null,
    ) {}
}
