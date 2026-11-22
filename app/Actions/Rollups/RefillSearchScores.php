<?php

namespace App\Actions\Rollups;

use App\Support\LootCounted;
use App\Support\LootListing;
use App\Support\LootRead;
use App\Support\LootRule;
use App\Support\LootSourceKind;
use App\Support\Rollup;
use App\Support\RollupRead;
use App\Support\SearchKind;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class RefillSearchScores
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Replaces the rows of `search_scores`, in one transaction, with the number a result line shows for each creature
     * (kills), item (sources), object (windows opened), quest (turn-ins) and zone (seconds played), counted under the same character threshold.
     * Running it twice leaves the same rows, and two runs at once take turns. A kind whose rollup answers nothing
     * keeps the rows it has. A thing nobody has done anything with gets no row.
     *
     * @return array<string, int> the rows now held, by kind
     */
    public function handle(): array
    {
        $queries = [
            SearchKind::Creatures->value => $this->read->shown($this->read->from(Rollup::KillsDailyRuleset, null)->selectRaw('?, npc_id, sum(kills)', [SearchKind::Creatures->value])->groupBy('npc_id')),
            SearchKind::Items->value => $this->items(),
            SearchKind::Objects->value => $this->objects(),
            SearchKind::Quests->value => $this->read->shown($this->read->from(Rollup::QuestsDailyRuleset, null)->selectRaw('?, quest_id, sum(turn_ins)', [SearchKind::Quests->value])->groupBy('quest_id')),
            SearchKind::Zones->value => $this->read->shown($this->read->from(Rollup::PlayHourlyRuleset, null)->selectRaw('?, zone_id, sum(ms) / 1000.0', [SearchKind::Zones->value])->whereNotNull('zone_id')->groupBy('zone_id')),
        ];

        return DB::transaction(function () use ($queries): array {
            DB::statement('lock table search_scores in share row exclusive mode');
            $held = [];
            foreach ($queries as $kind => $query) {
                if ((clone $query)->exists()) {
                    DB::table('search_scores')->where('kind', $kind)->delete();
                    DB::table('search_scores')->insertUsing(['kind', 'id', 'score'], $query);
                }
                $held[$kind] = DB::table('search_scores')->where('kind', $kind)->count();
            }

            return $held;
        });
    }

    private function objects(): Builder
    {
        return $this->read->shown(LootRule::within(LootListing::ofObjects($this->read->table(LootCounted::Windows->value, null)), 'loot_rule', null)
            ->selectRaw('?, source_id, sum(windows)', [SearchKind::Objects->value])
            ->where('source_kind', LootSourceKind::Object->value)->groupBy('source_id'));
    }

    private function items(): Builder
    {
        return LootRead::known(DB::table(LootCounted::Items->value))
            ->selectRaw('?, item_id, count(distinct (source_kind, source_id))', [SearchKind::Items->value])
            ->groupBy('item_id')
            ->havingRaw('distinct_count(rollup(characters)) >= ?', [(int) config('scarlet.min_characters')]);
    }
}
