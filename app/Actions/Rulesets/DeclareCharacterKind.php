<?php

namespace App\Actions\Rulesets;

use App\Models\Character;
use App\Models\RulesetKind;

class DeclareCharacterKind
{
    /**
     * Record the ruleset the site's owner confirmed for characters recorded before the Armory or a realm could name
     * it. The given characters, or with none every character that has no answer yet and whose sessions all sit
     * under rows of this kind (what the earlier hand marking of rows left). The Armory's answer still comes first.
     *
     * @param  list<int>  $characterIds
     * @return int characters declared
     */
    public function handle(RulesetKind $kind, array $characterIds): int
    {
        $characters = Character::query()->whereNull('armory_kind');
        if ($characterIds !== []) {
            return $characters->whereIn('id', $characterIds)->update(['declared_kind' => $kind->value]);
        }

        return $characters
            ->whereNull('declared_kind')
            ->whereExists(fn ($sessions) => $sessions->selectRaw('1')->from('sessions')->whereColumn('sessions.character_id', 'characters.id'))
            ->whereNotExists(fn ($others) => $others->selectRaw('1')->from('sessions')->join('rulesets', 'rulesets.id', '=', 'sessions.ruleset_id')
                ->whereColumn('sessions.character_id', 'characters.id')
                ->whereRaw('rulesets.kind is distinct from ?', [$kind->value]))
            ->update(['declared_kind' => $kind->value]);
    }
}
