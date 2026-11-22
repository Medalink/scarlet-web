<?php

namespace App\Support;

/** The nine playable classes: the value is `characters.class` and the class name of the client's `S` line. */
enum PlayerClass: string
{
    case Warrior = 'WARRIOR';
    case Paladin = 'PALADIN';
    case Hunter = 'HUNTER';
    case Rogue = 'ROGUE';
    case Priest = 'PRIEST';
    case Shaman = 'SHAMAN';
    case Mage = 'MAGE';
    case Warlock = 'WARLOCK';
    case Druid = 'DRUID';

    /** The class's slug in a page address: `warlock`. */
    public function slug(): string
    {
        return strtolower($this->value);
    }

    /** The scene of the class page's head. */
    public function scene(): Scene
    {
        return Scene::from($this->slug());
    }

    /** The bit of the class in the client's `SkillRaceClassInfo` and `TalentTab` ClassMask columns. */
    public function bit(): int
    {
        return match ($this) {
            self::Warrior => 1,
            self::Paladin => 2,
            self::Hunter => 4,
            self::Rogue => 8,
            self::Priest => 16,
            self::Shaman => 64,
            self::Mage => 128,
            self::Warlock => 256,
            self::Druid => 1024,
        };
    }
}
