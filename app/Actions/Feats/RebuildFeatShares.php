<?php

namespace App\Actions\Feats;

use App\Support\Feat;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Rewrites `feat_shares`: for each feat and tier, how many of the characters with a stored count of that feat and at least
 * `$hours` recorded hours hold the tier or a higher one. A feat nobody has a count for gets no row. A class feat is counted over
 * the characters it applies to, since only they have a row.
 */
class RebuildFeatShares
{
    public const int MIN_HOURS = 20;

    /** @return int rows written */
    public function handle(int $hours = self::MIN_HOURS): int
    {
        $seconds = $hours * 3600;
        $of = DB::table('character_feats as cf')->join('characters as c', 'c.id', '=', 'cf.character_id')
            ->where('c.recorded_seconds', '>=', $seconds)->groupBy('cf.feat')->selectRaw('cf.feat, count(*) as n')->pluck('n', 'feat');

        $tiers = [];
        $bindings = [];
        foreach (Feat::cases() as $feat) {
            foreach ($feat->thresholds() as $i => $threshold) {
                $tiers[] = '(?::text, ?::int, ?::float8)';
                array_push($bindings, $feat->value, $i + 1, $threshold);
            }
        }
        $counted = DB::table('character_feats as cf')->join('characters as c', 'c.id', '=', 'cf.character_id')
            ->where('c.recorded_seconds', '>=', $seconds)->select('cf.feat', 'cf.value', 'cf.character_id');
        $holders = DB::query()->fromRaw('(values '.implode(', ', $tiers).') as v(feat, tier, threshold)', $bindings)
            ->leftJoinSub($counted, 'cf', fn (JoinClause $join) => $join->on('cf.feat', '=', 'v.feat')->on('cf.value', '>=', 'v.threshold'))
            ->groupBy('v.feat', 'v.tier')
            ->selectRaw('v.feat, v.tier, count(cf.character_id) as holders')
            ->get();

        $now = now();
        $rows = [];
        foreach ($holders as $row) {
            $total = (int) ($of[(string) $row->feat] ?? 0);
            if ($total > 0) {
                $rows[] = ['feat' => (string) $row->feat, 'tier' => (int) $row->tier, 'share' => round(100 * (int) $row->holders / $total, 2), 'holders' => (int) $row->holders, 'of' => $total, 'updated_at' => $now];
            }
        }

        DB::transaction(function () use ($rows): void {
            DB::table('feat_shares')->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('feat_shares')->insert($chunk);
            }
        });

        return count($rows);
    }
}
