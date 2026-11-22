<?php

namespace App\Actions\Rollups;

use App\Support\LootRule;
use App\Support\RollOutcome;
use App\Support\RollType;
use App\Support\RollupRead;
use Illuminate\Support\Facades\DB;

class ItemRolls
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * The uploaders' own rolls of one item under the rules that roll (docs/LOOT.md, Metrics: Need share), split into need,
     * greed and pass, and how many of them were a roll that everyone passed. A roll another member made reaches every
     * uploader of the group, so it is counted by none of them. Null while fewer than `scarlet.min_characters` characters
     * rolled for the item, or none chose need, greed or pass.
     *
     * @return array{need: int, greed: int, pass: int, all_passed: int}|null
     */
    public function handle(int $itemId): ?array
    {
        $row = $this->read->rows(DB::table('loot_rolls')->where('counted', true)->where('mine', true)->where('item_id', $itemId)->whereIn('loot_rule', LootRule::rolled())
            ->selectRaw('count(*) filter (where roll_type = ?) as need, count(*) filter (where roll_type = ?) as greed, count(*) filter (where roll_type = ?) as pass, count(*) filter (where outcome = ?) as all_passed', [RollType::Need->value, RollType::Greed->value, RollType::Pass->value, RollOutcome::AllPassed->value])
            ->havingRaw('count(distinct character_id) >= ?', [$this->read->threshold(null)]))[0] ?? null;
        if ($row === null || (int) $row['need'] + (int) $row['greed'] + (int) $row['pass'] === 0) {
            return null;
        }

        return ['need' => (int) $row['need'], 'greed' => (int) $row['greed'], 'pass' => (int) $row['pass'], 'all_passed' => (int) $row['all_passed']];
    }
}
