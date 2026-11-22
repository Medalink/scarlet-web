<?php

use App\Support\FactTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The campsite aggregates (docs/DESIGN.md "Rollups"). The hourly one answers the totals, the lengths, the levels, the
 * hours of the week, the zones and the fires of the camps page, and its sketch of `seconds` the median visit. Like every
 * continuous aggregate, this cannot run inside a transaction.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    /** Characters that contributed, as a hyperloglog with 1024 buckets (about 3 percent error). */
    private const string HLL = 'hyperloglog(1024, character_id)';

    /** A stay is a visit when it began in the session: a carried one continues a stay of the session before. */
    private const string VISIT = 'not carried';

    /** Characters with a visit or a gain: the figures that count those are shown by this count, not by `HLL`. */
    private const string VISITORS = 'hyperloglog(1024, character_id) filter (where not carried)';

    public function up(): void
    {
        foreach ($this->views() as $name => $select) {
            DB::statement(sprintf('create materialized view %s with (timescaledb.continuous, timescaledb.materialized_only = true) as %s with no data', $name, $select));
            DB::statement(
                "select add_continuous_aggregate_policy(?::regclass, start_offset => null, end_offset => interval '1 hour', schedule_interval => interval '15 minutes')",
                [$name],
            );
        }
    }

    /** @return array<string, string> view name => select */
    private function views(): array
    {
        $hll = self::HLL;
        $visit = self::VISIT;
        $visitors = self::VISITORS;
        $loggedOut = FactTable::CAMP_ENDED_SESSION;

        return [
            'camp_stays_hourly_ruleset' => <<<SQL
                select time_bucket('1 hour', at) as bucket, ruleset_id, level, zone_id,
                       least(seconds / 30, 40) as step, campfire_spell_id,
                       count(*) as stays,
                       count(*) filter (where {$visit}) as visits,
                       sum(seconds) as seconds,
                       count(*) filter (where {$visit} and benefits) as earned,
                       count(*) filter (where {$visit} and sat_seconds > 0) as sat,
                       count(*) filter (where {$visit} and tent_seconds > 0) as tented,
                       count(*) filter (where {$visit} and tent_seconds >= 30) as tent_rests,
                       count(*) filter (where {$visit} and ended = '{$loggedOut}') as logged_out,
                       count(*) filter (where {$visit} and died_after) as died_after,
                       sum(afk_seconds) as afk_seconds,
                       sum(seconds) filter (where afk_seconds is not null) as afk_known_seconds,
                       count(*) filter (where crafts > 0) as crafted,
                       count(*) filter (where crafts is not null) as crafts_known,
                       count(*) filter (where {$visit} and placed_tent) as placed_tent,
                       count(*) filter (where {$visit} and placed_chair) as placed_chair,
                       tdigest(100, seconds::double precision) filter (where {$visit}) as visit_seconds,
                       {$visitors} as characters
                from camp_stays
                where counted
                group by 1, 2, 3, 4, 5, 6
                SQL,
            'camp_heat_daily_ruleset' => <<<SQL
                select time_bucket('1 day', at) as bucket, ruleset_id, map, x / 100 as cell_x, y / 100 as cell_y,
                       count(*) as visits, {$hll} as characters
                from camp_stays
                where counted and {$visit} and map is not null and x is not null and y is not null
                group by 1, 2, 3, 4, 5
                SQL,
            'camp_benefits_daily_ruleset' => <<<SQL
                select time_bucket('1 day', at) as bucket, ruleset_id, spell_id, level,
                       count(*) filter (where {$visit}) as gains,
                       sum(seconds) as seconds,
                       count(*) filter (where {$visit} and duration >= 7200) as two_hour_gains,
                       count(*) filter (where lost_to_death) as lost_to_death,
                       sum(seconds) filter (where xp is not null) as xp_seconds,
                       sum(xp) as xp,
                       sum(seconds) filter (where deaths is not null) as deaths_seconds,
                       sum(deaths) as deaths,
                       {$visitors} as gain_characters,
                       {$hll} as characters
                from camp_benefits
                where counted
                group by 1, 2, 3, 4
                SQL,
            'camp_stays_daily_character' => <<<SQL
                select time_bucket('1 day', at) as bucket, character_id, ruleset_id,
                       sum(seconds) as seconds, count(*) filter (where {$visit}) as visits
                from camp_stays
                where counted
                group by 1, 2, 3
                SQL,
        ];
    }
};
