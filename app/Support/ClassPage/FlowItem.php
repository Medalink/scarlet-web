<?php

namespace App\Support\ClassPage;

/** One end of a flow: a source on the left or a use on the right, as wide as its count. */
final readonly class FlowItem
{
    public function __construct(public string $label, public int|float $count, public ?Icon $icon = null, public Tone $tone = Tone::Own) {}
}
