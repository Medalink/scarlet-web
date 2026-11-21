<?php

namespace App\Actions\Public\Character;

use App\Support\SkillKind;
use Illuminate\Support\Facades\DB;

class SkillRanks
{
    /** The cap of a skill whose rows carry no `max_rank`: the highest rank of the standard game. */
    public const int CAP = 300;

    /**
     * The highest rank a character reached in each named skill (`SK` rows; `SL` rows are skill lines learnt) and its cap.
     *
     * @return list<array{skill: string, rank: int, cap: int}>
     */
    public function handle(int $characterId): array
    {
        $rows = DB::table('skill_ups')->where('character_id', $characterId)->where('counted', true)->where('kind', SkillKind::Skill->value)
            ->whereNotNull('skill')->whereNotNull('skill_rank')->groupBy('skill')
            ->selectRaw('skill, max(skill_rank) as rank, max(max_rank) as cap')->orderBy('skill')->limit(100)->get();

        return array_values($rows->map(fn (object $row): array => [
            'skill' => (string) $row->skill, 'rank' => (int) $row->rank, 'cap' => max((int) $row->rank, (int) ($row->cap ?? self::CAP)),
        ])->all());
    }
}
