<?php

namespace App\Actions\Public;

use App\Models\Ruleset;
use App\Models\RulesetKind;

class ListRulesets
{
    /**
     * Every ruleset that has been seen, for filters and links: the identified ones in the order of the rulesets,
     * then the ones no checked signal names yet, and last the one whose sessions recorded no rules at all. Two
     * rows of one label are told apart by a count, "(2)".
     *
     * @return list<array{id: int, slug: string, label: string, kind: string|null, hardcore: bool, war_mode: string, recorded: bool}>
     */
    public function handle(): array
    {
        $order = array_flip(array_map(fn (RulesetKind $kind): string => $kind->value, RulesetKind::cases()));
        $rank = fn (array $row): array => [$row['kind'] === null, $row['recorded'] ? 0 : 1, $row['kind'] === null ? 0 : $order[$row['kind']], $row['id']];
        $rows = [];
        foreach (Ruleset::query()->orderBy('id')->get() as $ruleset) {
            $rows[] = [
                'id' => $ruleset->id,
                'slug' => $ruleset->slug(),
                'label' => $ruleset->label(),
                'kind' => $ruleset->kind()?->value,
                'hardcore' => $ruleset->hardcore === true,
                'war_mode' => $ruleset->war_mode,
                'recorded' => $ruleset->slug() !== Ruleset::UNKNOWN_SLUG,
            ];
        }
        usort($rows, fn (array $a, array $b): int => $rank($a) <=> $rank($b));

        $seen = [];

        return array_map(function (array $row) use (&$seen): array {
            $count = $seen[$row['label']] = ($seen[$row['label']] ?? 0) + 1;

            return $count === 1 ? $row : [...$row, 'label' => $row['label'].' ('.$count.')'];
        }, $rows);
    }
}
