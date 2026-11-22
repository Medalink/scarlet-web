<?php

namespace App\Support\ClassPage;

/**
 * A feat as the feat wall reads it from the stored rows: its tier (0 none, 1 copper to 5 legend), the line that says how
 * far it is and the share of each of the five tier segments in percent. `glyph` is the feat's `<x-icon>` name, drawn
 * when the feat has no game icon.
 */
final readonly class FeatCard
{
    /** @param  list<int>  $track */
    public function __construct(public string $name, public int $tier, public string $detail, public array $track, public string $glyph, public ?Icon $icon = null) {}
}
