<?php

namespace App\Actions\Feats\Counts;

use App\Actions\Public\Character\SkillRanks;
use App\Models\Character;
use App\Support\Feat;
use App\Support\Profession;
use App\Support\SkillKind;
use Illuminate\Support\Facades\DB;

/** The skill feats: professions and their ranks, skill-ups and crafts. */
class SkillCounts
{
    public function __construct(private readonly SkillRanks $skillRanks) {}

    /**
     * One count per skill feat, keyed by the enum value; null when the feat does not apply to the character.
     *
     * @return array<string, int|float|null>
     */
    public function handle(Character $character): array
    {
        $id = (int) $character->id;
        $ranks = array_map(fn (array $skill): int => $skill['rank'], array_filter(
            $this->skillRanks->handle($id),
            fn (array $skill): bool => Profession::tryFrom($skill['skill']) !== null,
        ));
        $crafts = DB::table('crafts')->where('character_id', $id)->where('counted', true)->whereNotNull('item_id')
            ->selectRaw('coalesce(sum(coalesce(quantity, 1)), 0) as crafted, count(distinct item_id) as repertoire')->first();

        return [
            Feat::Artisan->value => array_sum($ranks),
            Feat::Master->value => count(array_filter($ranks, fn (int $rank): bool => $rank >= SkillRanks::CAP)),
            Feat::Practised->value => DB::table('skill_ups')->where('character_id', $id)->where('counted', true)->where('kind', SkillKind::Skill->value)->count(),
            Feat::Crafter->value => (int) ($crafts->crafted ?? 0),
            Feat::Repertoire->value => (int) ($crafts->repertoire ?? 0),
        ];
    }
}
