<?php

namespace App\Support;

/**
 * The spells of the campsite that `camp_stays` and `camp_benefits` rows name (docs/ROWS.md). The decoder keeps the
 * same ids and kinds in part/src/camp.rs.
 */
enum CampSpell: int
{
    case CampfireNearby = 1283391;
    case WelcomingCampfire = 1229739;
    case WelcomingCampfireSeated = 1289723;
    case CampTentRest = 1229487;
    case CampBenefits = 1229741;
    case CampChair = 1229519;
    case FirstAidKit = 1230124;
    case SharpeningWheel = 1230172;
    case Lodestone = 1230164;
    case ManaWell = 1230587;
    case IncenseCandle = 1229513;
    case FactionBanner = 1229718;
    case FishBowl = 1230098;
    case EnchantedLute = 1230653;
    case BasicCampfire = 1307227;
    case JourneymanCampfire = 1307252;
    case ExpertCampfire = 1307237;
    case CampTent = 1307230;
    case PlacedCampChair = 1307229;

    /** The label of a stay whose campfire the character did not place. */
    public const string FIRE_ALREADY_THERE = 'A fire already there';

    /**
     * The nine benefit buffs, in the order of the camp furniture table.
     *
     * @return list<self>
     */
    public static function benefits(): array
    {
        return array_values(array_filter(self::cases(), fn (self $spell): bool => $spell->kind() === CampSpellKind::Benefit));
    }

    /**
     * The campfire tiers a character can place, Basic first.
     *
     * @return list<self>
     */
    public static function campfires(): array
    {
        return array_values(array_filter(self::cases(), fn (self $spell): bool => $spell->kind() === CampSpellKind::Campfire));
    }

    /** What a benefit buff gives, as the game's tooltip says; null for a spell that is not one of the nine. */
    public function effect(): ?string
    {
        return match ($this) {
            self::CampChair => '+2% critical strike chance',
            self::FirstAidKit => '+56 Stamina',
            self::SharpeningWheel => '+34 Strength',
            self::Lodestone => '+90 melee attack power',
            self::ManaWell => '29 mana every 5 seconds',
            self::IncenseCandle => '+25 Intellect',
            self::FactionBanner => '+32 Spirit',
            self::FishBowl => '+8% to all stats',
            self::EnchantedLute => '+308 armor, +13 to all attributes, +22 to all resistances',
            default => null,
        };
    }

    public function kind(): CampSpellKind
    {
        return match ($this) {
            self::CampfireNearby => CampSpellKind::Near,
            self::WelcomingCampfire, self::WelcomingCampfireSeated => CampSpellKind::Sit,
            self::CampTentRest => CampSpellKind::Tent,
            self::CampBenefits => CampSpellKind::Benefits,
            self::CampChair, self::FirstAidKit, self::SharpeningWheel, self::Lodestone, self::ManaWell,
            self::IncenseCandle, self::FactionBanner, self::FishBowl, self::EnchantedLute => CampSpellKind::Benefit,
            self::BasicCampfire, self::JourneymanCampfire, self::ExpertCampfire => CampSpellKind::Campfire,
            self::CampTent => CampSpellKind::PlacedTent,
            self::PlacedCampChair => CampSpellKind::PlacedChair,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CampfireNearby => 'Campfire Nearby',
            self::WelcomingCampfire, self::WelcomingCampfireSeated => 'Welcoming Campfire',
            self::CampTentRest => 'Camp Tent',
            self::CampBenefits => 'Camp Benefits',
            self::CampChair, self::PlacedCampChair => 'Camp Chair',
            self::FirstAidKit => 'First Aid Kit',
            self::SharpeningWheel => 'Sharpening Wheel',
            self::Lodestone => 'Lodestone',
            self::ManaWell => 'Mana Well',
            self::IncenseCandle => 'Incense Candle',
            self::FactionBanner => 'Faction Banner',
            self::FishBowl => 'Fish Bowl',
            self::EnchantedLute => 'Enchanted Lute',
            self::BasicCampfire => 'Basic Campfire',
            self::JourneymanCampfire => 'Journeyman Campfire',
            self::ExpertCampfire => 'Expert Campfire',
            self::CampTent => 'Camp Tent',
        };
    }
}
