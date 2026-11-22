<?php

namespace App\Actions\Rollups;

use App\Support\Rollup;
use App\Support\RollupRead;
use App\Support\XpSource;
use Illuminate\Support\Facades\DB;

class RulesetSummaries
{
    /** The level the comparison table and chart race to. */
    public const int TARGET_LEVEL = 60;

    public function __construct(private readonly RollupRead $read) {}

    /**
     * One row per ruleset with what the comparison table shows, and each ruleset's levelling curve. A ruleset that
     * fewer than `config('scarlet.min_characters')` characters contributed to has null numbers; a curve point
     * under the same threshold is left out. `reached` counts the characters whose level is at least the target, seen
     * levelling or not; `median_to_target` only the ones whose level-up Scarlet recorded. Six grouped reads, whatever
     * the number of rulesets.
     *
     * @return array{rows: list<array<string, mixed>>, curves: array<int, list<array{level: int, seconds: float, characters: int}>>}
     *                                                                                                                               rows: ruleset_id, characters, seconds, deaths, xp, quest_xp, reached, median_to_target
     */
    public function handle(): array
    {
        $min = (int) config('scarlet.min_characters');

        $xp = $this->read->from(Rollup::XpDailyRuleset, null)
            ->selectRaw('ruleset_id, distinct_count(rollup(characters)) as characters, sum(xp) as xp')
            ->selectRaw('coalesce(sum(xp) filter (where source = ?), 0) as quest_xp', [XpSource::Quest->value])
            ->groupBy('ruleset_id')->get()->keyBy('ruleset_id');
        $played = $this->read->from(Rollup::PlayHourlyRuleset, null)
            ->selectRaw('ruleset_id, (sum(ms) / 1000.0)::float as seconds, distinct_count(rollup(characters)) as characters')
            ->groupBy('ruleset_id')->get()->keyBy('ruleset_id');
        $deaths = $this->read->from(Rollup::DeathsDailyRuleset, null)
            ->selectRaw('ruleset_id, sum(deaths) as deaths')
            ->groupBy('ruleset_id')->get()->keyBy('ruleset_id');
        $levels = $this->read->from(Rollup::LevelTimeRuleset, null)
            ->selectRaw('ruleset_id, level, distinct_count(rollup(characters)) as characters, approx_percentile(0.5, rollup(reach)) as median')
            ->groupBy('ruleset_id', 'level')->orderBy('ruleset_id')->orderBy('level')->get();

        $curves = [];
        $medians = [];
        foreach ($levels as $row) {
            $characters = (int) $row->characters;
            if ($characters < $min) {
                continue;
            }
            $curves[(int) $row->ruleset_id][] = ['level' => (int) $row->level, 'seconds' => (float) $row->median, 'characters' => $characters];
            if ((int) $row->level === self::TARGET_LEVEL) {
                $medians[(int) $row->ruleset_id] = (float) $row->median;
            }
        }
        $reached = $this->atTarget();

        $rows = [];
        foreach ($played as $id => $row) {
            $characters = (int) $row->characters;
            $shown = $characters >= $min;
            $rows[] = [
                'ruleset_id' => (int) $id,
                'characters' => $shown ? $characters : null,
                'seconds' => $shown ? (float) $row->seconds : null,
                'deaths' => $shown ? (int) ($deaths[$id]->deaths ?? 0) : null,
                'xp' => $shown ? (int) ($xp[$id]->xp ?? 0) : null,
                'quest_xp' => $shown ? (int) ($xp[$id]->quest_xp ?? 0) : null,
                'reached' => $shown ? ($reached[(int) $id] ?? 0) : null,
                'median_to_target' => $shown ? ($medians[(int) $id] ?? null) : null,
            ];
        }

        return ['rows' => $rows, 'curves' => $curves];
    }

    /** @return array<int, int> characters by ruleset whose level, as the game reports it, is at least the target */
    private function atTarget(): array
    {
        $highest = $this->read->table('sessions', null)
            ->where('counted', true)
            ->whereNotNull('level_end')
            ->groupBy('ruleset_id', 'character_id')
            ->selectRaw('ruleset_id, max(level_end) as top');
        $query = DB::query()->fromSub($highest, 'h')
            ->where('top', '>=', self::TARGET_LEVEL)
            ->selectRaw('ruleset_id, count(*) as characters')
            ->groupBy('ruleset_id');

        return array_map('intval', array_column($this->read->rows($query), 'characters', 'ruleset_id'));
    }
}
