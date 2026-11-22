<?php

namespace App\Support\ClassPage;

/** The address of each icon a class page asks for. An icon with no file maps to null and draws as an empty square. */
final readonly class IconMap
{
    /** @param  array<string, string|null>  $urls  by `key()` */
    public function __construct(private array $urls = []) {}

    public function url(?Icon $icon): ?string
    {
        return $icon === null ? null : ($this->urls[self::key($icon)] ?? null);
    }

    public static function key(Icon $icon): string
    {
        return $icon->kind->name.':'.$icon->id;
    }
}
