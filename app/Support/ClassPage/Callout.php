<?php

namespace App\Support\ClassPage;

/** A label on a prop of the head's scene, placed by the prop's `key`: its name, a line about it and the character's own share. */
final readonly class Callout
{
    public function __construct(public string $key, public string $title, public string $text, public ?string $note = null, public bool $left = false) {}
}
