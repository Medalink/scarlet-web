<?php

namespace App\Support;

/** The decoder's `skill_ups.kind`: a named skill that went up, or a skill line learnt by id (docs/ROWS.md). */
enum SkillKind: string
{
    case Skill = 'SK';
    case Line = 'SL';
}
