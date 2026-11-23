<?php

namespace App\Actions\Ingest;

use App\Support\AddonKind;
use App\Support\ClaimStatus;
use App\Support\GatherKind;
use Illuminate\Support\Facades\DB;

class SumScarletAndClaims
{
    /**
     * What Scarlet's own rows of the kind plus the counted amounts of the characters' claims that add come to inside
     * `[from, to]` (docs/DESIGN.md, Addon counts, rule 7). Fishing is casts, landed and items; herb and ore are
     * attempts, the characters' `gathers` rows of that kind; any other kind (`kill`, `run`, `xp`) has no figure here
     * and no metric, so it adds up to an empty array.
     *
     * @param  list<int>  $characters
     * @return array<string, int>
     */
    public function handle(string $kind, array $characters, int $from, int $to): array
    {
        $addon = AddonKind::tryFrom($kind);
        $sum = match ($addon) {
            AddonKind::Fishing => ['casts' => 0, 'landed' => 0, 'items' => 0],
            AddonKind::Herb, AddonKind::Ore => ['attempts' => 0],
            default => [],
        };
        if ($characters === [] || $sum === []) {
            return $sum;
        }
        $sum = $addon === AddonKind::Fishing ? $this->fishing($characters, $from, $to) : $this->attempts(GatherKind::from($kind), $characters, $from, $to);

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
            if (array_key_exists($metric, $sum)) {
                $sum[$metric] += (int) $row->total;
            }
        }

        return $sum;
    }

    /**
     * @param  list<int>  $characters
     * @return array{casts: int, landed: int, items: int}
     */
    private function fishing(array $characters, int $from, int $to): array
    {
        $fish = GatherKind::Fish->value;
        $gathers = DB::table('gathers')->whereIn('character_id', $characters)->where('kind', $fish)
            ->whereRaw('at between to_timestamp(?) and to_timestamp(?)', [$from, $to])
            ->selectRaw('count(*) as casts, count(*) filter (where looted) as landed')->first();

        return [
            'casts' => (int) ($gathers->casts ?? 0),
            'landed' => (int) ($gathers->landed ?? 0),
            'items' => (int) DB::table('gather_items')->whereIn('character_id', $characters)->where('kind', $fish)->whereNull('container_item_id')
                ->whereRaw('at between to_timestamp(?) and to_timestamp(?)', [$from, $to])->sum('quantity'),
        ];
    }

    /**
     * @param  list<int>  $characters
     * @return array{attempts: int}
     */
    private function attempts(GatherKind $kind, array $characters, int $from, int $to): array
    {
        return ['attempts' => DB::table('gathers')->whereIn('character_id', $characters)->where('kind', $kind->value)
            ->whereRaw('at between to_timestamp(?) and to_timestamp(?)', [$from, $to])->count()];
    }
}
