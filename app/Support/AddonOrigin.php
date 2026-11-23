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
    case NaowhForever = 'nf';

    public function label(): string
    {
        return match ($this) {
            self::RxpGuides => 'RXPGuides',
            self::ForeverDungeonJournal => 'ForeverDungeonJournal',
            self::NaowhForever => 'NaowhForever',
            self::FishingLog => 'The Fishing Log',
            self::ForeverFishing => 'ForeverFishing',
            self::Mastercraft => 'Mastercraft',
        };
    }
}
