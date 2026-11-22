<?php

namespace App\Actions\Rollups;

use App\Support\Profession;
use App\Support\RollupRead;
use Illuminate\Support\Facades\DB;

class CraftSittings
{
    /** Crafts older than this many days are not read: the figure reads rows, and the public API stops a statement after 10 seconds. */
    private const int DAYS = 90;

    public function __construct(private readonly RollupRead $read) {}

    /**
     * The median number of crafts and the median length of a sitting of the last `DAYS` days: a run of crafts of one
     * profession in one session in which each follows the one before within 120 seconds (`sitting_gap` is null on the
     * first). A sitting lasts from its first craft to its last. Read from the rows, since no aggregate carries a median
     * over runs. Null while fewer than `config('scarlet.min_characters')` characters sat.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return array{median_crafts: float|null, median_seconds: float|null}
     */
    public function handle(Profession $profession, int|array|null $rulesetId = null): array
    {
        $ordered = DB::table('crafts')
            ->where('counted', true)
            ->where('profession_id', $profession->skillLine())
            ->where('at', '>=', now()->startOfHour()->subDays(self::DAYS))
            ->when(is_int($rulesetId), fn ($query) => $query->where('ruleset_id', $rulesetId))
            ->when(is_array($rulesetId), fn ($query) => $query->whereIn('ruleset_id', $rulesetId))
            ->selectRaw('character_id, session_start, extract(epoch from at) as crafted')
            ->selectRaw('sum((sitting_gap is null)::int) over (partition by character_id, session_start order by at, (sitting_gap is null) desc) as sitting');
        $sittings = DB::query()->fromSub($ordered, 'o')
            ->selectRaw('character_id, count(*) as crafts, max(crafted) - min(crafted) as seconds')
            ->groupBy('character_id', 'session_start', 'sitting');
        $query = DB::query()->fromSub($sittings, 's')->selectRaw('count(distinct character_id) as characters, percentile_cont(0.5) within group (order by crafts) as median_crafts, percentile_cont(0.5) within group (order by seconds) as median_seconds');
        $row = $this->read->rows($query)[0] ?? null;
        if ($row === null || (int) $row['characters'] < (int) config('scarlet.min_characters')) {
            return ['median_crafts' => null, 'median_seconds' => null];
        }

        return [
            'median_crafts' => $row['median_crafts'] === null ? null : (float) $row['median_crafts'],
            'median_seconds' => $row['median_seconds'] === null ? null : (float) $row['median_seconds'],
        ];
    }
}
