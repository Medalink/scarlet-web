<?php

namespace App\Actions\Public\Character;

use App\Models\Character;
use App\Support\CreatureClass;
use App\Support\Feat;
use App\Support\Profession;
use App\Support\Rollup;
use Illuminate\Support\Facades\DB;

class Feats
{
    /**
     * The feats of a character, computed from its totals: highest tier first, then in the order of `Feat`. Unbroken is
     * only for a character recorded from level 1, and is left out when its first death has no level. `value` of Veteran
     * is whole hours and of Wayfarer one decimal, both rounded down, so a value never shows a tier it has not reached.
     *
     * @param  array{kills: int, quests: int, distance: float}  $totals  the answer of SessionTotals, which has more keys
     * @param  array{nodes: int, fish: int, jumps: int, swam: int}  $records  the answer of Records, which has more keys
     * @param  list<array{skill: string, rank: int, cap: int}>  $skills  the answer of SkillRanks
     * @param  list<array<string, mixed>>  $dungeons  the dungeons of the overview, each with its `runs`
     * @return list<array{key: string, name: string, value: int|float, tier: int, tier_name: string|null, threshold: int|null, next: int|null}>
     */
    public function handle(Character $character, array $totals, array $records, array $skills, array $dungeons): array
    {
        $seconds = $character->played_known ? $character->played_seconds : $character->recorded_seconds;
        $counts = [
            Feat::Summit->value => $character->level === null ? null : (int) $character->level,
            Feat::Slayer->value => $totals['kills'],
            Feat::Questing->value => $totals['quests'],
            Feat::Delver->value => (int) array_sum(array_map(fn (array $dungeon): int => (int) $dungeon['runs'], $dungeons)),
            Feat::Explorer->value => $this->zones($character->id),
            Feat::Veteran->value => (int) floor((int) $seconds / 3600),
            Feat::Devoted->value => $this->daysPlayed($character->id),
            Feat::Wayfarer->value => floor($totals['distance'] * 10) / 10,
            Feat::Jumper->value => $records['jumps'],
            Feat::Swimmer->value => $records['swam'],
            Feat::Unbroken->value => $character->whole_life ? $this->unbroken($character) : null,
            Feat::Artisan->value => $this->professionRanks($skills),
            Feat::Crafter->value => $this->itemsCrafted($character->id),
            Feat::Angler->value => $records['fish'],
            Feat::Gatherer->value => $records['nodes'],
            Feat::Tracker->value => $this->raresFound($character->id),
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
    private function professionRanks(array $skills): int
    {
        $ranks = array_map(fn (array $skill): int => $skill['rank'], array_filter($skills, fn (array $skill): bool => Profession::tryFrom($skill['skill']) !== null));

        return array_sum($ranks);
    }

    private function daysPlayed(int $characterId): int
    {
        return (int) DB::table(Rollup::PlayHourlyCharacter->value)->where('character_id', $characterId)
            ->where('ms', '>', 0)
            ->selectRaw("count(distinct (bucket at time zone 'UTC')::date) as days")
            ->value('days');
    }

    private function itemsCrafted(int $characterId): int
    {
        return (int) DB::table('crafts')->where('character_id', $characterId)->where('counted', true)->whereNotNull('item_id')
            ->selectRaw('coalesce(sum(coalesce(quantity, 1)), 0) as made')->value('made');
    }

    private function raresFound(int $characterId): int
    {
        return DB::table('creature_sightings')->where('character_id', $characterId)->where('counted', true)
            ->whereIn('classification', [CreatureClass::Rare->value, CreatureClass::RareElite->value])
            ->whereNotNull('npc_id')->distinct()->count('npc_id');
    }
}
