<?php

namespace App\Actions\Rollups;

use App\Support\LootListing;
use App\Support\Rollup;
use App\Support\RollupRead;

class LootRuleSizes
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * The loot rules and group sizes the windows were read under (first reads of every source read for loot, skinning,
     * herbs, ore or a container), the busiest first. Personal loot is its own row here and in no other count. A rule and
     * size under `scarlet.min_characters` characters are left out.
     *
     * @return list<array{rule: string, size: int|null, windows: int}>
     */
    public function handle(int $limit = 8): array
    {
        $rows = $this->read->rows($this->read->shown(LootListing::ofSources($this->read->table(Rollup::LootWindowsDaily->value, null))
            ->selectRaw('loot_rule, group_size, sum(windows) as windows')
            ->groupBy('loot_rule', 'group_size')->orderByRaw('sum(windows) desc')->orderBy('loot_rule')->orderBy('group_size')->limit($limit)));

        return array_map(fn (array $row): array => ['rule' => (string) $row['loot_rule'], 'size' => $row['group_size'] === null ? null : (int) $row['group_size'], 'windows' => (int) $row['windows']], $rows);
    }
}
