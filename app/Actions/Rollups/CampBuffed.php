<?php

namespace App\Actions\Rollups;

use App\Support\CampSpell;
use App\Support\Rollup;
use App\Support\RollupRead;

class CampBuffed
{
    public function __construct(private readonly RollupRead $read) {}

    /**
     * Play while Camp Benefits was held, against what the same seconds at the same levels give across all play of the
     * scope: the sum over levels of the seconds held at that level times the scope's rate there (experience or deaths
     * over played seconds). The rates are read whole, without the character threshold: they only enter the sum. A level
     * nobody played is left out and the sum is scaled up to all the seconds held. `seconds` counts every aura held, a
     * carried one included; `xp` and `deaths` are the sums of the rows the decoder filled (version 7 and later), and
     * each is set against the held time of the rows that have it, `xp_seconds` and `deaths_seconds`. Null while fewer
     * than `config('scarlet.min_characters')` characters held the aura.
     *
     * @param  int|list<int>|null  $rulesetId
     * @return array{seconds: int, xp_seconds: int, deaths_seconds: int, xp: int|null, deaths: int|null, expected_xp: float|null, expected_deaths: float|null}|null
     */
    public function handle(int|array|null $rulesetId = null): ?array
    {
        $held = $this->read->from(Rollup::CampBenefitsDailyRuleset, $rulesetId)->where('spell_id', CampSpell::CampBenefits->value);
        $total = $this->read->rows((clone $held)->selectRaw('coalesce(sum(seconds), 0) as seconds, coalesce(sum(xp_seconds), 0) as xp_seconds, coalesce(sum(deaths_seconds), 0) as deaths_seconds, sum(xp) as xp, sum(deaths) as deaths, distinct_count(rollup(characters)) as characters'))[0] ?? null;
        if ($total === null || (int) $total['characters'] < (int) config('scarlet.min_characters')) {
            return null;
        }

        $levels = $this->read->rows((clone $held)->selectRaw('level, coalesce(sum(xp_seconds), 0) as xp_seconds, coalesce(sum(deaths_seconds), 0) as deaths_seconds')->whereNotNull('level')->groupBy('level'));
        $played = $this->byLevel(Rollup::PlayHourlyRuleset, $rulesetId, 'level, (sum(ms) / 1000.0)::float as total');
        $xp = $this->byLevel(Rollup::XpDailyRuleset, $rulesetId, 'level, sum(xp) as total');
        $deaths = $this->byLevel(Rollup::DeathsDailyRuleset, $rulesetId, 'level, sum(deaths) as total');

        return [
            'seconds' => (int) $total['seconds'],
            'xp_seconds' => (int) $total['xp_seconds'],
            'deaths_seconds' => (int) $total['deaths_seconds'],
            'xp' => $total['xp'] === null ? null : (int) $total['xp'],
            'deaths' => $total['deaths'] === null ? null : (int) $total['deaths'],
            'expected_xp' => $this->expected($levels, 'xp_seconds', $xp, $played, (int) $total['xp_seconds']),
            'expected_deaths' => $this->expected($levels, 'deaths_seconds', $deaths, $played, (int) $total['deaths_seconds']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $levels  level, xp_seconds, deaths_seconds held
     * @param  array<int, int|float>  $amounts  the scope's total at each level
     * @param  array<int, int|float>  $played  the scope's played seconds at each level
     */
    private function expected(array $levels, string $weight, array $amounts, array $played, int $all): ?float
    {
        $sum = 0.0;
        $rated = 0;
        foreach ($levels as $row) {
            $level = (int) $row['level'];
            if (($played[$level] ?? 0) <= 0) {
                continue;
            }
            $sum += (int) $row[$weight] * ($amounts[$level] ?? 0) / $played[$level];
            $rated += (int) $row[$weight];
        }

        return $rated === 0 ? null : $sum * $all / $rated;
    }

    /**
     * @param  int|list<int>|null  $rulesetId
     * @param  literal-string  $select  `level` and the scope's `total` there
     * @return array<int, int|float> level => total
     */
    private function byLevel(Rollup $rollup, int|array|null $rulesetId, string $select): array
    {
        $rows = $this->read->rows($this->read->from($rollup, $rulesetId)->selectRaw($select)->whereNotNull('level')->groupBy('level'));
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['level']] = $row['total'] + 0;
        }

        return $out;
    }
}
