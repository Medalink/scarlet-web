<?php

namespace App\Support;

/** The professions among the skills of `skill_ups`, by the skill's name. Every other skill is a weapon, armour or class skill. */
enum Profession: string
{
    case Alchemy = 'Alchemy';
    case Blacksmithing = 'Blacksmithing';
    case Enchanting = 'Enchanting';
    case Engineering = 'Engineering';
    case Herbalism = 'Herbalism';
    case Leatherworking = 'Leatherworking';
    case Mining = 'Mining';
    case Skinning = 'Skinning';
    case Tailoring = 'Tailoring';
    case Cooking = 'Cooking';
    case FirstAid = 'First Aid';
    case Fishing = 'Fishing';
    case Lockpicking = 'Lockpicking';
    case Poisons = 'Poisons';
}
