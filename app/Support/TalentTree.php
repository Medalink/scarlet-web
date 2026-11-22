<?php

namespace App\Support;

/**
 * The 27 talent trees: the value is the tree key. A session plays the tree of its first talent read's spec tab
 * (docs/DESIGN.md "Art and game layer").
 */
enum TalentTree: string
{
    case WarriorArms = 'warrior-arms';
    case WarriorFury = 'warrior-fury';
    case WarriorProtection = 'warrior-protection';
    case PaladinHoly = 'paladin-holy';
    case PaladinProtection = 'paladin-protection';
    case PaladinRetribution = 'paladin-retribution';
    case HunterBeastMastery = 'hunter-beast-mastery';
    case HunterMarksmanship = 'hunter-marksmanship';
    case HunterSurvival = 'hunter-survival';
    case RogueAssassination = 'rogue-assassination';
    case RogueCombat = 'rogue-combat';
    case RogueSubtlety = 'rogue-subtlety';
    case PriestDiscipline = 'priest-discipline';
    case PriestHoly = 'priest-holy';
    case PriestShadow = 'priest-shadow';
    case ShamanElemental = 'shaman-elemental';
    case ShamanEnhancement = 'shaman-enhancement';
    case ShamanRestoration = 'shaman-restoration';
    case MageArcane = 'mage-arcane';
    case MageFire = 'mage-fire';
    case MageFrost = 'mage-frost';
    case WarlockAffliction = 'warlock-affliction';
    case WarlockDemonology = 'warlock-demonology';
    case WarlockDestruction = 'warlock-destruction';
    case DruidBalance = 'druid-balance';
    case DruidFeral = 'druid-feral';
    case DruidRestoration = 'druid-restoration';

    /** The class the tree belongs to. */
    public function class(): PlayerClass
    {
        return match ($this) {
            self::WarriorArms => PlayerClass::Warrior,
            self::WarriorFury => PlayerClass::Warrior,
            self::WarriorProtection => PlayerClass::Warrior,
            self::PaladinHoly => PlayerClass::Paladin,
            self::PaladinProtection => PlayerClass::Paladin,
            self::PaladinRetribution => PlayerClass::Paladin,
            self::HunterBeastMastery => PlayerClass::Hunter,
            self::HunterMarksmanship => PlayerClass::Hunter,
            self::HunterSurvival => PlayerClass::Hunter,
            self::RogueAssassination => PlayerClass::Rogue,
            self::RogueCombat => PlayerClass::Rogue,
            self::RogueSubtlety => PlayerClass::Rogue,
            self::PriestDiscipline => PlayerClass::Priest,
            self::PriestHoly => PlayerClass::Priest,
            self::PriestShadow => PlayerClass::Priest,
            self::ShamanElemental => PlayerClass::Shaman,
            self::ShamanEnhancement => PlayerClass::Shaman,
            self::ShamanRestoration => PlayerClass::Shaman,
            self::MageArcane => PlayerClass::Mage,
            self::MageFire => PlayerClass::Mage,
            self::MageFrost => PlayerClass::Mage,
            self::WarlockAffliction => PlayerClass::Warlock,
            self::WarlockDemonology => PlayerClass::Warlock,
            self::WarlockDestruction => PlayerClass::Warlock,
            self::DruidBalance => PlayerClass::Druid,
            self::DruidFeral => PlayerClass::Druid,
            self::DruidRestoration => PlayerClass::Druid,
        };
    }

    /** @return list<string> the tab names the client may show; the first is the label */
    public function names(): array
    {
        return match ($this) {
            self::WarriorArms => ['Arms'],
            self::WarriorFury => ['Fury'],
            self::WarriorProtection => ['Protection'],
            self::PaladinHoly => ['Holy'],
            self::PaladinProtection => ['Protection'],
            self::PaladinRetribution => ['Retribution'],
            self::HunterBeastMastery => ['Beast Mastery'],
            self::HunterMarksmanship => ['Marksmanship'],
            self::HunterSurvival => ['Survival'],
            self::RogueAssassination => ['Assassination'],
            self::RogueCombat => ['Combat'],
            self::RogueSubtlety => ['Subtlety'],
            self::PriestDiscipline => ['Discipline'],
            self::PriestHoly => ['Holy'],
            self::PriestShadow => ['Shadow'],
            self::ShamanElemental => ['Elemental', 'Elemental Combat'],
            self::ShamanEnhancement => ['Enhancement'],
            self::ShamanRestoration => ['Restoration'],
            self::MageArcane => ['Arcane'],
            self::MageFire => ['Fire'],
            self::MageFrost => ['Frost'],
            self::WarlockAffliction => ['Affliction'],
            self::WarlockDemonology => ['Demonology'],
            self::WarlockDestruction => ['Destruction'],
            self::DruidBalance => ['Balance'],
            self::DruidFeral => ['Feral', 'Feral Combat'],
            self::DruidRestoration => ['Restoration'],
        };
    }

    /** @return list<Role> */
    public function roles(): array
    {
        return match ($this) {
            self::WarriorArms => [Role::Melee],
            self::WarriorFury => [Role::Melee],
            self::WarriorProtection => [Role::Tank],
            self::PaladinHoly => [Role::Healer],
            self::PaladinProtection => [Role::Tank],
            self::PaladinRetribution => [Role::Melee],
            self::HunterBeastMastery => [Role::Ranged],
            self::HunterMarksmanship => [Role::Ranged],
            self::HunterSurvival => [Role::Ranged],
            self::RogueAssassination => [Role::Melee],
            self::RogueCombat => [Role::Melee],
            self::RogueSubtlety => [Role::Melee],
            self::PriestDiscipline => [Role::Healer],
            self::PriestHoly => [Role::Healer],
            self::PriestShadow => [Role::Ranged],
            self::ShamanElemental => [Role::Ranged],
            self::ShamanEnhancement => [Role::Melee],
            self::ShamanRestoration => [Role::Healer],
            self::MageArcane => [Role::Ranged],
            self::MageFire => [Role::Ranged],
            self::MageFrost => [Role::Ranged],
            self::WarlockAffliction => [Role::Ranged],
            self::WarlockDemonology => [Role::Ranged],
            self::WarlockDestruction => [Role::Ranged],
            self::DruidBalance => [Role::Ranged],
            self::DruidFeral => [Role::Tank, Role::Melee],
            self::DruidRestoration => [Role::Healer],
        };
    }

    /** The tree of `$class` whose tab name is `$tabName`, ignoring case; null when none is. */
    public static function of(PlayerClass $class, string $tabName): ?self
    {
        $name = mb_strtolower($tabName);
        foreach (self::cases() as $tree) {
            if ($tree->class() !== $class) {
                continue;
            }
            foreach ($tree->names() as $tab) {
                if (mb_strtolower($tab) === $name) {
                    return $tree;
                }
            }
        }

        return null;
    }
}
