<?php

namespace App\Actions\Public;

use App\Models\Ruleset;

class ListRulesets
{
    /**
     * Every ruleset that has been seen, for filters and links.
     *
     * @return list<array{id: int, slug: string, label: string, hardcore: bool, war_mode: string}>
     */
    public function handle(): array
    {
        return array_values(Ruleset::query()->orderBy('id')->get()->map(fn (Ruleset $ruleset): array => [
            'id' => $ruleset->id,
            'slug' => $ruleset->slug(),
            'label' => $ruleset->label(),
            'hardcore' => $ruleset->hardcore === true,
            'war_mode' => $ruleset->war_mode,
        ])->all());
    }
}
