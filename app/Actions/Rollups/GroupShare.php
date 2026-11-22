<?php

namespace App\Actions\Rollups;

use App\Support\GroupLootKind;
use App\Support\LootRule;
use App\Support\RateEstimate;
use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Support\Facades\DB;

class GroupShare
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Your share of group loot (docs/LOOT.md, Metrics: Share of group loot): under each rule that owes every member an
     * equal share and at each group size of 2 or more, every item the uploaders received while grouped under the rule
     * (`loot_received_daily`, whether or not a loot window is known for it, since a roll win and a skin read have none)
     * against every item their group mates received by the rule, a roll win or a master looter's pick (`group_loot`, kind
     * looted; an item the client pushed to a mate is left out, while an uploader's own list cannot tell a push or a
     * created item from a loot). It needs no dedupe: both sides are one uploader's view of its own group, so pooling
     * uploaders gives the mean share of a member. A rule and size under `scarlet.min_characters` uploaders on either side
     * are left out, the smallest group first.
     *
     * @return list<array{rule: string, size: int, yours: int, others: int, estimate: RateEstimate}>
     */
    public function handle(): array
    {
        $yours = $this->read->rows($this->read->shown(DB::table(Rollup::LootReceivedDaily->value)
            ->whereIn('loot_rule', LootRule::equalShare())
            ->selectRaw('loot_rule, group_size, sum(receipts) as received')->groupBy('loot_rule', 'group_size')));
        $others = $this->read->rows(DB::table('group_loot')
            ->where('counted', true)->where('kind', GroupLootKind::Looted->value)->whereIn('loot_rule', LootRule::equalShare())->where('group_size', '>=', 2)
            ->selectRaw('loot_rule, group_size, count(*) as received')->groupBy('loot_rule', 'group_size')
            ->havingRaw('count(distinct character_id) >= ?', [$this->read->threshold(null)]));

        $by = [];
        foreach ($yours as $row) {
            $by[$row['loot_rule'].'|'.$row['group_size']]['yours'] = (int) $row['received'];
        }
        foreach ($others as $row) {
            $by[$row['loot_rule'].'|'.$row['group_size']]['others'] = (int) $row['received'];
        }
        $rows = [];
        foreach ($by as $key => $counts) {
            if (! isset($counts['yours'], $counts['others'])) {
                continue;
            }
            [$rule, $size] = explode('|', $key);
            $rows[] = ['rule' => $rule, 'size' => (int) $size, 'yours' => $counts['yours'], 'others' => $counts['others'], 'estimate' => RateEstimate::of($counts['yours'], $counts['yours'] + $counts['others'], [], RateEstimate::UNIT_RECEIPT)];
        }
        usort($rows, fn (array $a, array $b): int => [$a['size'], $a['rule']] <=> [$b['size'], $b['rule']]);

        return $rows;
    }
}
