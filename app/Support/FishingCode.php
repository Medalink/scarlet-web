<?php

namespace App\Support;

/**
 * The code of a `fishing_lines` row (docs/DESIGN.md, The Fishing Log): a `J` line of Scarlet's FORMAT.md, or `fl.C`
 * for a catch of the addon stream (ADDONS.md). The value is the decoder's `code`.
 */
enum FishingCode: string
{
    case Profile = 'JP';
    case PersonalRecord = 'JR';
    case Journal = 'JJ';
    case CaughtIn = 'JZ';
    case Session = 'JH';
    case SessionWater = 'JW';
    case BountyProgress = 'JB';
    case BountyResult = 'JX';
    case BountyStanding = 'JY';
    case Find = 'JF';
    case Frenzy = 'JQ';
    case HigherLower = 'JG';
    case SpotRecord = 'JK';
    case Spot = 'JN';
    case FishName = 'JI';
    case BountyBoard = 'JO';
    case BountyTarget = 'JT';
    case Angler = 'JA';
    case AnglerFaction = 'JE';
    case FrenzyReport = 'JD';
    case School = 'JU';
    case SurveyItem = 'JV';
    case SurveyWater = 'JC';
    case UncoveredZone = 'JM';
    case CatchSkill = 'JS';
    case SkillUps = 'JL';
    case Champion = 'JCH';
    case Catch = 'fl.C';
}
