<?php

namespace App\Actions\Feats\Counts;

use App\Actions\Public\Character\SkillRanks;
use App\Models\Character;
use App\Support\Feat;
use App\Support\Profession;
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
        $held = $this->heldRanks($id);
        $crafts = DB::table('crafts')->where('character_id', $id)->where('counted', true)->whereNotNull('item_id')
            ->selectRaw('coalesce(sum(coalesce(quantity, 1)), 0) as crafted, count(distinct item_id) as repertoire')->first();

        return [
            Feat::Artisan->value => array_sum($held),
            Feat::Master->value => count(array_filter($held, fn (int $rank): bool => $rank >= Profession::SKILL_CAP)),
            Feat::Crafter->value => (int) ($crafts->crafted ?? 0),
            Feat::Repertoire->value => (int) ($crafts->repertoire ?? 0),
        ];
    }

    /** @return list<int> */
    private function heldRanks(int $characterId): array
    {
        $ranks = [];
        foreach ($this->skillRanks->handle($characterId) as $skill) {
            $ranks[$skill['skill']] = min($skill['rank'], Profession::SKILL_CAP);
        }
        $primary = array_map(fn (Profession $profession): int => $ranks[$profession->value] ?? 0, Profession::PRIMARY);
        rsort($primary);

        return [
            ...array_slice($primary, 0, Profession::PRIMARY_HELD),
            ...array_map(fn (Profession $profession): int => $ranks[$profession->value] ?? 0, Profession::SECONDARY),
        ];
    }
}
