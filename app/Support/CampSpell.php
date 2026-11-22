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
