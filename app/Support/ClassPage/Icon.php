<?php

namespace App\Support\ClassPage;

/**
 * A reference to a game icon by the spell or item it belongs to. A reference with no file on the art store draws an
 * empty square; where the game has no icon, the page object holds null instead of an Icon.
 */
final readonly class Icon
{
    public function __construct(public IconKind $kind, public int $id) {}

    public static function spell(int $id): self
    {
        return new self(IconKind::Spell, $id);
    }

    public static function item(int $id): self
    {
        return new self(IconKind::Item, $id);
    }
}
