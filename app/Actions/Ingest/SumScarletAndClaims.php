<?php

namespace App\Actions\Ingest;

use App\Support\AddonKind;
use App\Support\ClaimStatus;
use App\Support\GatherKind;
use Illuminate\Support\Facades\DB;

class SumScarletAndClaims
{
    private const array METRICS = ['casts', 'landed', 'items'];

    /**
     * What Scarlet's own rows of the kind plus the counted amounts of the characters' claims that add come to inside
     * `[from, to]` (docs/DESIGN.md, Addon counts, rule 7). A kind with no source of Scarlet rows adds up to 0.
     *
     * @param  list<int>  $characters
     * @return array{casts: int, landed: int, items: int}
     */
    public function handle(string $kind, array $characters, int $from, int $to): array
    {
        $sum = ['casts' => 0, 'landed' => 0, 'items' => 0];
        if ($characters === [] || AddonKind::tryFrom($kind)?->hasRows() !== true) {
            return $sum;
        }
        $fish = GatherKind::Fish->value;
        $gathers = DB::table('gathers')->whereIn('character_id', $characters)->where('kind', $fish)
            ->whereRaw('at between to_timestamp(?) and to_timestamp(?)', [$from, $to])
            ->selectRaw('count(*) as casts, count(*) filter (where looted) as landed')->first();
        $sum['casts'] = (int) ($gathers->casts ?? 0);
        $sum['landed'] = (int) ($gathers->landed ?? 0);
        $sum['items'] = (int) DB::table('gather_items')->whereIn('character_id', $characters)->where('kind', $fish)->whereNull('container_item_id')
            ->whereRaw('at between to_timestamp(?) and to_timestamp(?)', [$from, $to])->sum('quantity');

        $counted = DB::select(
            <<<'SQL'
            select a.metric, sum((a.value #>> '{}')::numeric) as total
            from addon_claims c cross join lateral jsonb_each(c.counted) as a(metric, value)
            where c.character_id = any(?::bigint[]) and c.kind = ? and c.status in (?, ?) and c.to_at between ? and ?
            group by a.metric
            SQL,
            ['{'.implode(',', $characters).'}', $kind, ...ClaimStatus::adding(), $from, $to],
        );
        foreach ($counted as $row) {
            $metric = (string) $row->metric;
            if (in_array($metric, self::METRICS, true)) {
                $sum[$metric] += (int) $row->total;
            }
        }

        return $sum;
    }
}
