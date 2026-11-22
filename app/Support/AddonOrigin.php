<?php

namespace App\Support;

/** The addon a claim came from (`addon_claims.origin`, Scarlet's FORMAT.md, "Addon counts") and the name the pages give it. */
enum AddonOrigin: string
{
    case FishingLog = 'fl';
    case ForeverFishing = 'ff';
    case Mastercraft = 'mc';

    public function label(): string
    {
        return match ($this) {
            self::FishingLog => 'The Fishing Log',
            self::ForeverFishing => 'ForeverFishing',
            self::Mastercraft => 'Mastercraft',
        };
    }
}
