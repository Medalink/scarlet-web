<?php

namespace App\Support\ClassPage;

/** A heading, its charts and an optional note under them for the design's stated limits ("estimated", "the top five"). */
final readonly class Module
{
    /** @param  list<Block>  $blocks */
    public function __construct(
        public string $heading,
        public array $blocks,
        public Span $span = Span::Third,
        public ?string $note = null,
        public ?Icon $icon = null,
    ) {}
}
