<?php

namespace App\Support;

/** The addon a claim came from (`addon_claims.origin`, Scarlet's FORMAT.md, "Addon counts") and the name the pages give it. */
enum AddonOrigin: string
{
    case FishingLog = 'fl';
    case ForeverFishing = 'ff';
    case Mastercraft = 'mc';
    case RxpGuides = 'rxp';
    case ForeverDungeonJournal = 'fdj';

    public function label(): string
    {
        return match ($this) {
            self::RxpGuides => 'RXPGuides',
            self::ForeverDungeonJournal => 'ForeverDungeonJournal',
            self::FishingLog => 'The Fishing Log',
            self::ForeverFishing => 'ForeverFishing',
            self::Mastercraft => 'Mastercraft',
        };
    }
}
