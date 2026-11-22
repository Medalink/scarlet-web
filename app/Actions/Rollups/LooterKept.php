<?php

namespace App\Actions\Rollups;

use App\Support\LootGroupScope;
use App\Support\LootSourceRef;
use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class LooterKept
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * What the looter kept (docs/LOOT.md, Loot rules and group size): by loot rule and group size, the corpses read for
     * loot, the items those corpses held and the items the looter received from them (`loot_receipts_daily`, a receipt
     * whose source window is known). A receipt is the uploader's own row, as is its window, so nothing is counted twice.
     * `other` is true for a size that no source seen under it was also seen under another size of the same rule, so a
     * difference from another size may be a difference of creatures. Personal loot is its own row. A rule and size
     * under `scarlet.min_characters` characters are left out; the smallest group first, then the most corpses.
     *
     * @return list<array{rule: string, size: int, corpses: int, items: int, received: int, other: bool}>
     */
    public function handle(?LootSourceRef $source): array
    {
        $threshold = $this->read->threshold(null);
        $windows = $this->read->rows(LootGroupScope::of(DB::table(Rollup::LootWindowsCharacterDaily->value), $source, personal: true)
            ->selectRaw('loot_rule, group_size, sum(windows) as windows')->groupBy('loot_rule', 'group_size')
            ->havingRaw('count(distinct character_id) >= ?', [$threshold]));
        $items = $this->totals(Rollup::LootItemsDaily, false, $source);
        $received = $this->totals(Rollup::LootReceiptsDaily, true, $source);

        $by = [];
        foreach ($windows as $row) {
            $size = LootGroupScope::size($row['group_size']);
            if ($size === null) {
                continue;
            }
            $key = $row['loot_rule'].'|'.$size;
            $by[$key] ??= ['rule' => (string) $row['loot_rule'], 'size' => $size, 'corpses' => 0, 'items' => 0, 'received' => 0, 'other' => false];
            $by[$key]['corpses'] += (int) $row['windows'];
        }
        foreach ([['items', $items], ['received', $received]] as [$field, $rows]) {
            foreach ($rows as $row) {
                $size = LootGroupScope::size($row['group_size']);
                $key = $row['loot_rule'].'|'.$size;
                if (isset($by[$key])) {
                    $by[$key][$field] += (int) $row['total'];
                }
            }
        }
        $shared = $this->shared($source);
        $sizes = [];
        foreach ($by as $row) {
            $sizes[$row['rule']] = ($sizes[$row['rule']] ?? 0) + 1;
        }
        $rows = array_values(array_map(fn (array $row): array => ['other' => ($sizes[$row['rule']] ?? 0) > 1 && ! isset($shared[$row['rule'].'|'.$row['size']])] + $row, $by));
        usort($rows, fn (array $a, array $b): int => [$a['size'], $b['corpses'], $a['rule']] <=> [$b['size'], $a['corpses'], $b['rule']]);

        return $rows;
    }

    /**
     * @return list<array<string, mixed>> loot_rule, group_size, total
     */
    private function totals(Rollup $rollup, bool $receipts, ?LootSourceRef $source): array
    {
        return $this->read->rows(LootGroupScope::of(DB::table($rollup->value), $source, personal: true)
            ->selectRaw($receipts ? 'loot_rule, group_size, sum(receipts) as total' : 'loot_rule, group_size, sum(windows) as total')->groupBy('loot_rule', 'group_size'));
    }

    /**
     * The rules and sizes that share a source with another size of the same rule.
     *
     * @return array<string, true>
     */
    private function shared(?LootSourceRef $source): array
    {
        $per = LootGroupScope::of(DB::table(Rollup::LootWindowsCharacterDaily->value), $source, personal: true)
            ->whereNotNull('group_size')->selectRaw('source_kind, source_id, loot_rule, case when group_size < 1 then 1 else group_size end as size')
            ->groupBy('source_kind', 'source_id', 'loot_rule', 'size');
        $several = DB::query()->fromSub($per, 's')->select('s.source_kind', 's.source_id', 's.loot_rule')->groupBy('s.source_kind', 's.source_id', 's.loot_rule')->havingRaw('count(*) >= 2');
        $rows = $this->read->rows(DB::query()->fromSub($per, 'p')
            ->joinSub($several, 'm', fn (JoinClause $join): JoinClause => $join->on('m.source_kind', '=', 'p.source_kind')->on('m.source_id', '=', 'p.source_id')->on('m.loot_rule', '=', 'p.loot_rule'))
            ->select('p.loot_rule', 'p.size')->distinct());
        $keys = [];
        foreach ($rows as $row) {
            $keys[$row['loot_rule'].'|'.$row['size']] = true;
        }

        return $keys;
    }
}
