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
}
