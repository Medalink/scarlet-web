<?php

namespace App\Actions\Rollups;

use App\Support\GatherKind;
use App\Support\RollupRead;
use Illuminate\Support\Facades\DB;

class GatherTrips
{
    /** Trips older than this many days are not read: the figure reads rows, and the public API stops a statement after 10 seconds. */
    private const int DAYS = 90;

    public function __construct(private readonly RollupRead $read) {}

    /**
     * The median length and the median number of attempts of a trip of the last `DAYS` days: a run of attempts of one session in which each
     * follows the one before within 120 seconds (`gap` is null on the first). A trip lasts from its first cast to the end
     * of its last. Read from the rows, since no aggregate carries a median over runs. Null while fewer than
     * `config('scarlet.min_characters')` characters made a trip.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return array{median_seconds: float|null, median_attempts: float|null}
     */
    public function handle(GatherKind $kind, int|array|null $rulesetId = null): array
    {
        $ordered = DB::table('gathers')
            ->where('counted', true)
            ->where('kind', $kind->value)
            ->where('at', '>=', now()->startOfHour()->subDays(self::DAYS))
            ->when(is_int($rulesetId), fn ($query) => $query->where('ruleset_id', $rulesetId))
            ->when(is_array($rulesetId), fn ($query) => $query->whereIn('ruleset_id', $rulesetId))
            ->selectRaw('character_id, session_start, extract(epoch from at) as started, extract(epoch from at) + coalesce(seconds, 0) as ended')
            ->selectRaw('sum((gap is null)::int) over (partition by character_id, session_start order by at, seconds) as trip');
        $trips = DB::query()->fromSub($ordered, 'o')
            ->selectRaw('character_id, count(*) as attempts, max(ended) - min(started) as seconds')
            ->groupBy('character_id', 'session_start', 'trip');
        $query = DB::query()->fromSub($trips, 't')->selectRaw('count(distinct character_id) as characters, percentile_cont(0.5) within group (order by seconds) as median_seconds, percentile_cont(0.5) within group (order by attempts) as median_attempts');
        $row = $this->read->rows($query)[0] ?? null;
        if ($row === null || (int) $row['characters'] < (int) config('scarlet.min_characters')) {
            return ['median_seconds' => null, 'median_attempts' => null];
        }

        return [
            'median_seconds' => $row['median_seconds'] === null ? null : (float) $row['median_seconds'],
            'median_attempts' => $row['median_attempts'] === null ? null : (float) $row['median_attempts'],
        ];
    }
}
