<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class Totals
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Headline numbers for one ruleset or all, from the aggregates only. A total is null while fewer than
     * `config('scarlet.min_characters')` characters contributed to it.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return array{characters: int|float|null, xp: int|float|null, played_seconds: int|float|null, kills: int|float|null, deaths: int|float|null, quests: int|float|null, copper_earned: int|float|null, copper_spent: int|float|null}
     */
    public function handle(int|array|null $rulesetId = null): array
    {
        $ms = $this->sum(Rollup::PlayHourlyRuleset, 'ms', $rulesetId);

        return [
            'characters' => $this->characters(Rollup::XpDailyRuleset, $rulesetId),
            'xp' => $this->sum(Rollup::XpDailyRuleset, 'xp', $rulesetId),
            'played_seconds' => $ms === null ? null : $ms / 1000,
            'kills' => $this->kills($rulesetId),
            'deaths' => $this->sum(Rollup::DeathsDailyRuleset, 'deaths', $rulesetId),
            'quests' => $this->sum(Rollup::QuestsDailyRuleset, 'turn_ins', $rulesetId),
            'copper_earned' => $this->sum(Rollup::MoneyDailyRuleset, 'earned', $rulesetId),
            'copper_spent' => $this->sum(Rollup::MoneyDailyRuleset, 'spent', $rulesetId),
        ];
    }

    /**
     * Every kill the sessions counted. The kills aggregate holds only kills of a creature with an id, so it leaves
     * out the rest, which are most of them in sessions imported from before the id was recorded.
     *
     * @param  int|list<int>|null  $rulesetId
     */
    private function kills(int|array|null $rulesetId): int|float|null
    {
        if ($this->characters(Rollup::PlayHourlyRuleset, $rulesetId) === null) {
            return null;
        }

        return DB::table('sessions')->where('counted', true)
            ->when(is_int($rulesetId), fn (Builder $query): Builder => $query->where('ruleset_id', $rulesetId))
            ->when(is_array($rulesetId), fn (Builder $query): Builder => $query->whereIn('ruleset_id', $rulesetId))
            ->sum('kills') + 0;
    }

    /** @param  int|list<int>|null  $rulesetId */
    private function characters(Rollup $rollup, int|array|null $rulesetId): ?int
    {
        $count = (int) $this->read->from($rollup, $rulesetId)->selectRaw('distinct_count(rollup(characters)) as characters')->value('characters');

        return $count < (int) config('scarlet.min_characters') ? null : $count;
    }

    /** @param  int|list<int>|null  $rulesetId */
    private function sum(Rollup $rollup, string $column, int|array|null $rulesetId): int|float|null
    {
        if ($this->characters($rollup, $rulesetId) === null) {
            return null;
        }

        return $this->read->from($rollup, $rulesetId)->sum($column) + 0;
    }
}
