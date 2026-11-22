<?php

namespace App\Actions\Public\Character;

use App\Models\Character;
use App\Support\Feat;
use App\Support\Profession;
use Illuminate\Support\Facades\DB;

class Feats
{
    /**
     * The feats of a character, computed from its totals: highest tier first, then in the order of `Feat`. Unbroken is only
     * for a character recorded from level 1, and is left out when its first death has no level. `value` of Veteran is whole
     * hours and of Wayfarer one decimal, both rounded down, so a value never shows a tier it has not reached.
     *
     * @param  array{kills: int, quests: int, distance: float}  $totals  the answer of SessionTotals, which has more keys
     * @param  array{nodes: int, fish: int}  $records  the answer of Records, which has more keys
     * @param  list<array{skill: string, rank: int, cap: int}>  $skills  the answer of SkillRanks
     * @param  list<array<string, mixed>>  $dungeons  the dungeons of the overview, each with its `runs`
     * @return list<array{key: string, name: string, value: int|float, tier: int, tier_name: string|null, threshold: int|null, next: int|null}>
     */
    public function handle(Character $character, array $totals, array $records, array $skills, array $dungeons): array
    {
        $seconds = $character->played_known ? $character->played_seconds : $character->recorded_seconds;
        $counts = [
            Feat::Summit->value => (int) $character->level,
            Feat::Slayer->value => $totals['kills'],
            Feat::Questing->value => $totals['quests'],
            Feat::Delver->value => (int) array_sum(array_map(fn (array $dungeon): int => (int) $dungeon['runs'], $dungeons)),
            Feat::Explorer->value => $this->zones($character->id),
            Feat::Veteran->value => (int) floor((int) $seconds / 3600),
            Feat::Wayfarer->value => floor($totals['distance'] * 10) / 10,
            Feat::Unbroken->value => $character->whole_life ? $this->unbroken($character) : null,
            Feat::Artisan->value => $this->artisan($skills),
            Feat::Angler->value => $records['fish'],
            Feat::Gatherer->value => $records['nodes'],
        ];
        $rows = [];
        foreach (Feat::cases() as $feat) {
            $value = $counts[$feat->value];
            if ($value !== null) {
                $rows[] = $this->row($feat, $value);
            }
        }
        usort($rows, fn (array $a, array $b): int => $b['tier'] <=> $a['tier']);

        return $rows;
    }

    /** @return array{key: string, name: string, value: int|float, tier: int, tier_name: string|null, threshold: int|null, next: int|null} */
    private function row(Feat $feat, int|float $value): array
    {
        $tier = $feat->tier($value);
        $thresholds = $feat->thresholds();

        return [
            'key' => $feat->value, 'name' => $feat->name, 'value' => $value, 'tier' => $tier,
            'tier_name' => Feat::TIERS[$tier - 1] ?? null,
            'threshold' => $thresholds[$tier - 1] ?? null,
            'next' => $thresholds[$tier] ?? null,
        ];
    }

    private function zones(int $characterId): int
    {
        return DB::table('play_spans')->where('character_id', $characterId)->where('counted', true)->whereNotNull('zone_id')->distinct()->count('zone_id');
    }

    /** The level the character died at first, or its level when it never died; null when the first death has no level. */
    private function unbroken(Character $character): ?int
    {
        $death = DB::table('deaths')->where('character_id', $character->id)->orderBy('at')->first(['level']);
        if ($death === null) {
            return (int) $character->level;
        }

        return $death->level === null ? null : (int) $death->level;
    }

    /**
     * @param  list<array{skill: string, rank: int, cap: int}>  $skills
     */
    private function artisan(array $skills): int
    {
        $ranks = array_map(fn (array $skill): int => $skill['rank'], array_filter($skills, fn (array $skill): bool => Profession::tryFrom($skill['skill']) !== null));

        return $ranks === [] ? 0 : max($ranks);
    }
}
