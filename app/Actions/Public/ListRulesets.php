<?php

namespace App\Actions\Public;

use App\Models\Ruleset;

class ListRulesets
{
    /**
     * Every ruleset that has been seen, for filters and links. The ruleset with no recorded rules comes last. Two
     * rulesets that differ only in a preset or a game mode number share a label, so the later one gets a count.
     *
     * @return list<array{id: int, slug: string, label: string, hardcore: bool, war_mode: string, recorded: bool}>
     */
    public function handle(): array
    {
        $seen = [];
        $rows = [];
        foreach (Ruleset::query()->orderBy('id')->get() as $ruleset) {
            $label = $ruleset->label();
            $seen[$label] = ($seen[$label] ?? 0) + 1;
            $rows[] = [
                'id' => $ruleset->id,
                'slug' => $ruleset->slug(),
                'label' => $seen[$label] === 1 ? $label : $label.' ('.$seen[$label].')',
                'hardcore' => $ruleset->hardcore === true,
                'war_mode' => $ruleset->war_mode,
                'recorded' => $ruleset->slug() !== Ruleset::UNKNOWN_SLUG,
            ];
        }
        usort($rows, fn (array $a, array $b): int => [! $a['recorded'], $a['id']] <=> [! $b['recorded'], $b['id']]);

        return $rows;
    }
}
