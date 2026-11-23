<?php

namespace App\Actions\Rollups;

use App\Support\CountedLoot;
use App\Support\LootCounted;
use App\Support\LootListing;
use App\Support\LootRule;
use App\Support\RollupRead;

class LootRuleSizes
{
    public function __construct(private readonly RollupRead $read, private readonly CountedLoot $counted) {}

    /**
     * The loot rules and group sizes the windows were read under (first reads of every source read for loot, skinning,
     * herbs, ore or a container), the busiest first. Personal loot is its own row here and in no other count. A rule and
     * size under `scarlet.min_characters` characters are left out. The free-for-all windows of a source count as its arm
     * counts them (`FreeForAllArms`).
     *
     * @return list<array{rule: string, size: int|null, windows: int}>
     */
    public function handle(int $limit = 8): array
    {
        $rows = $this->read->rows($this->read->shown(LootListing::ofSources($this->read->table(LootCounted::Windows->value, null))
            ->selectRaw('loot_rule, group_size, sum(windows) as windows')
            ->groupBy('loot_rule', 'group_size')));
        $shift = $this->counted->windowShift(null, 'size');
        $sizes = array_map(fn (array $row): array => [
            'rule' => (string) $row['loot_rule'], 'size' => $row['group_size'] === null ? null : (int) $row['group_size'],
            'windows' => (int) $row['windows'] + ($row['loot_rule'] === LootRule::FreeForAll->value ? ($shift[$row['group_size']] ?? 0) : 0),
        ], $rows);
        usort($sizes, fn (array $a, array $b): int => [$b['windows'], $a['rule'], $a['size'] ?? PHP_INT_MAX] <=> [$a['windows'], $b['rule'], $b['size'] ?? PHP_INT_MAX]);

        return array_slice($sizes, 0, $limit);
    }
}
