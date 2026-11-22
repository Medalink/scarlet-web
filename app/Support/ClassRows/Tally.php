<?php

namespace App\Support\ClassRows;

/** The `key` of a `class_tallies` row (docs/ROWS.md, `class_tallies`). */
enum Tally: string
{
    case KillBlow = 'kill_blow';
    case FightKills = 'fight_kills';
    case FightFirst = 'fight_first';
    case DeathAfter = 'death_after';
    case DeathAfterAura = 'death_after_aura';
    case CastUnder = 'cast_under';
    case ShiftForm = 'shift_form';
    case ShiftStance = 'shift_stance';
    case ShiftAspect = 'shift_aspect';
    case ShiftPalAura = 'shift_pal_aura';
    case ShiftSeal = 'shift_seal';
    case OpenFinish = 'open_finish';
    case StealthOpen = 'stealth_open';
    case SealFate = 'seal_fate';
    case TotemFate = 'totem_fate';
    case FightTotems = 'fight_totems';
    case ResOffer = 'res_offer';
    case ResTaken = 'res_taken';
}
