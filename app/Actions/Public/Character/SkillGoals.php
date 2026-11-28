<?php

namespace App\Actions\Public\Character;

use App\Support\NameKind;
use App\Support\SkillKind;
use Illuminate\Support\Facades\DB;

class SkillGoals
{
    public const string PROFESSION = 'profession';

    public const string WEAPON = 'weapon';

    /** The skills the Scarlet app lists under Professions; every other skill is a weapon or defense skill. */
    public const array PROFESSIONS = [
        'Alchemy', 'Blacksmithing', 'Enchanting', 'Engineering', 'Herbalism', 'Jewelcrafting', 'Leatherworking', 'Mining', 'Skinning',
        'Tailoring', 'Inscription', 'Cooking', 'Fishing', 'First Aid', 'Archaeology', 'Riding', 'Lockpicking',
    ];

    private const string LANGUAGE_PREFIX = 'Language:';

    /**
     * The latest read of each skill line the character has a named skill for, with a ceiling above 1 and no language, as
     * the Skills of the app's Character Overview: its name, rank, ceiling and whether it is a profession or a weapon
     * and defense skill, by name.
     *
     * @return list<array{name: string, rank: int, max: int, kind: string}>
     */
    public function handle(int $characterId): array
    {
        $rows = DB::table('skill_ups as u')->where('u.character_id', $characterId)->where('u.counted', true)->where('u.kind', SkillKind::Line->value)->whereNotNull('u.skill_id')
            ->selectRaw('distinct on (u.skill_id) u.skill_rank, u.max_rank, (select n.name from names n where n.kind = ? and n.id = u.skill_id order by n.votes desc, n.name limit 1) as name', [NameKind::Skill->value])
            ->orderBy('u.skill_id')->orderByDesc('u.at')->get();

        $skills = [];
        foreach ($rows as $row) {
            $name = $row->name === null ? '' : (string) $row->name;
            $max = (int) $row->max_rank;
            if ($name === '' || $max <= 1 || str_starts_with($name, self::LANGUAGE_PREFIX)) {
                continue;
            }
            $skills[$name] = ['name' => $name, 'rank' => (int) $row->skill_rank, 'max' => $max, 'kind' => in_array($name, self::PROFESSIONS, true) ? self::PROFESSION : self::WEAPON];
        }
        ksort($skills);

        return array_values($skills);
    }
}
