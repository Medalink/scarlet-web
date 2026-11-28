<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** The kinds of the `names` dictionary that pages read (docs/ROWS.md, `names`). The value is the decoder's `kind`. */
enum NameKind: string
{
    case Npc = 'npc';
    case Item = 'item';
    case Quest = 'quest';
    case Spell = 'spell';
    case Skill = 'skill';
    case Faction = 'faction';
    case TalentTab = 'talenttab';
    case Object = 'object';

    /** The name of this kind with the most votes for the id in `$idColumn`, to join with `leftJoinLateral` once per row of the outer query. */
    public function topName(string $idColumn): Builder
    {
        return DB::table('names as n')->select('n.name')->where('n.kind', $this->value)->whereColumn('n.id', $idColumn)->orderByDesc('n.votes')->orderBy('n.name')->limit(1);
    }
}
