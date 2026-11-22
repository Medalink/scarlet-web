<?php

namespace App\Support\ClassPage;

use InvalidArgumentException;

/** The colour of one series of a chart: a `#rrggbb` hex colour or a CSS variable such as `var(--class)`. */
final readonly class Hue
{
    public string $css;

    public function __construct(string $css)
    {
        if (preg_match('/^(#[0-9a-f]{6}|var\(--[a-z-]+\))$/i', $css) !== 1) {
            throw new InvalidArgumentException("A hue is a #rrggbb colour or var(--name), not {$css}.");
        }
        $this->css = $css;
    }

    public static function ofClass(): self
    {
        return new self('var(--class)');
    }
}
