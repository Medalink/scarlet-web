<?php

namespace App\Actions\Public;

use App\Models\Ruleset;
use App\Models\RulesetKind;

class ListRulesets
{
    /**
     * Every ruleset that has sessions filed under it, for filters and links: the rulesets of WoW Forever in their order,
     * then the one for characters whose kind is not known yet. A row from before kinds that is not merged yet is listed
     * too, after the others of its label.
     *
     * @return list<array{id: int, slug: string, name: string, label: string, kind: string|null, hardcore: bool, war_mode: string, recorded: bool}>
     */
    public function handle(): array
    {
        $order = array_flip(array_map(fn (RulesetKind $kind): string => $kind->value, RulesetKind::cases()));
        $rank = fn (array $row): array => [$row['kind'] === null, $row['kind'] === null ? 0 : $order[$row['kind']], $row['id']];
        $rows = [];
        $listed = Ruleset::query()->whereNull('merged_into')
            ->whereExists(fn ($sessions) => $sessions->selectRaw('1')->from('sessions')->whereColumn('sessions.ruleset_id', 'rulesets.id'))
            ->orderBy('id')->get();
        foreach ($listed as $ruleset) {
            $rows[] = [
                'id' => $ruleset->id,
                'slug' => $ruleset->slug(),
                'name' => $ruleset->label(),
                'label' => $ruleset->label(),
                'kind' => $ruleset->kind()?->value,
                'hardcore' => $ruleset->kind() === RulesetKind::Hardcore,
                'war_mode' => $ruleset->war_mode,
                'recorded' => $ruleset->kind() !== null,
            ];
        }
        usort($rows, fn (array $a, array $b): int => $rank($a) <=> $rank($b));

        return $rows;
    }
}
