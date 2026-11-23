<?php

namespace App\Support\ClassPage;

use App\Actions\Public\ClassPage\DruidBuilder;
use App\Actions\Public\ClassPage\HunterBuilder;
use App\Actions\Public\ClassPage\MageBuilder;
use App\Actions\Public\ClassPage\PaladinBuilder;
use App\Actions\Public\ClassPage\PriestBuilder;
use App\Actions\Public\ClassPage\RogueBuilder;
use App\Actions\Public\ClassPage\ShamanBuilder;
use App\Actions\Public\ClassPage\WarlockBuilder;
use App\Actions\Public\ClassPage\WarriorBuilder;
use App\Contracts\ClassBuilder;
use App\Support\PlayerClass;

/** The builder of each class's page: every class of the game has one. */
final class ClassBuilders
{
    /** @return class-string<ClassBuilder> */
    public static function for(PlayerClass $class): string
    {
        return match ($class) {
            PlayerClass::Hunter => HunterBuilder::class,
            PlayerClass::Paladin => PaladinBuilder::class,
            PlayerClass::Warrior => WarriorBuilder::class,
            PlayerClass::Priest => PriestBuilder::class,
            PlayerClass::Rogue => RogueBuilder::class,
            PlayerClass::Shaman => ShamanBuilder::class,
            PlayerClass::Warlock => WarlockBuilder::class,
            PlayerClass::Mage => MageBuilder::class,
            PlayerClass::Druid => DruidBuilder::class,
        };
    }
}
