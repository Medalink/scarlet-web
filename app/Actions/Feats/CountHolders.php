<?php

namespace App\Actions\Feats;

use App\Support\Feat;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * How many characters hold each tier of each feat, from the stored `character_feats` values. Raw SQL because the
 * query builder has no `VALUES` list: the thresholds of `Feat` are joined to the rows in one grouped query that the
 * `(feat, value)` index serves, where one count per feat and tier would be 375 queries.
 */
class CountHolders
{
    /**
     * @return array<string, array{0: int, 1: int, 2: int, 3: int, 4: int}> per feat key, the characters at or above copper, silver, gold, scarlet and legend
     */
    public function handle(): array
    {
        /** @var array<string, array{0: int, 1: int, 2: int, 3: int, 4: int}> */
        return Cache::remember(self::key(), (int) config('scarlet.feats.holders_seconds'), function (): array {
            $tiers = [];
            $bindings = [];
            foreach (Feat::cases() as $feat) {
                foreach ($feat->thresholds() as $i => $threshold) {
                    $tiers[] = '(?::text, ?::int, ?::float8)';
                    array_push($bindings, $feat->value, $i, $threshold);
                }
            }
            $rows = DB::query()->fromRaw('(values '.implode(', ', $tiers).') as v(feat, tier, threshold)', $bindings)
                ->leftJoin('character_feats as cf', fn (JoinClause $join) => $join->on('cf.feat', '=', 'v.feat')->on('cf.value', '>=', 'v.threshold'))
                ->groupBy('v.feat', 'v.tier')
                ->selectRaw('v.feat, v.tier, count(cf.character_id) as holders')
                ->get();
            $holders = [];
            foreach (Feat::cases() as $feat) {
                $holders[$feat->value] = [0, 0, 0, 0, 0];
            }
            foreach ($rows as $row) {
                $holders[(string) $row->feat][(int) $row->tier] = (int) $row->holders;
            }

            return $holders;
        });
    }

    private static function key(): string
    {
        return 'feat-holders';
    }
}
