<?php

namespace App\Support\ClassPage;

/** One series of a mirror or stack chart: its name in the key and its colour. */
final readonly class Series
{
    public function __construct(public string $label, public Hue $hue) {}
}
