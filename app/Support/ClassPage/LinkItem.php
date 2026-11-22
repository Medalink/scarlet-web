<?php

namespace App\Support\ClassPage;

/** One side's entry of a link chart. */
final readonly class LinkItem
{
    public function __construct(public string $label, public ?Icon $icon = null, public Tone $tone = Tone::Own) {}
}
