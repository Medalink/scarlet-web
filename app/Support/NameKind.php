<?php

namespace App\Support;

/** The kinds of the `names` dictionary that pages read (docs/ROWS.md, `names`). The value is the decoder's `kind`. */
enum NameKind: string
{
    case Npc = 'npc';
    case Item = 'item';
    case Quest = 'quest';
    case Spell = 'spell';
    case Skill = 'skill';
    case TalentTab = 'talenttab';
}
