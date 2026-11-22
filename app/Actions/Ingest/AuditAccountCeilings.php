<?php

namespace App\Actions\Ingest;

use App\Support\AddonKind;
use App\Support\ClaimStatus;
use App\Support\GatherKind;
use Illuminate\Support\Facades\DB;
use stdClass;

class AuditAccountCeilings
{
    private const float TOLERANCE = 1.025;

    private const array METRICS = ['casts', 'landed', 'items'];

    private const string LIFETIME = 'life';

    private const string SCOPE_ACCOUNT = 'a';

    /**
     * Check 8 of docs/DESIGN.md, Addon counts, Checks (rule 7): for each store that keeps an account total, its latest
     * read against Scarlet's rows plus the counted claim amounts of the characters that carry it, inside the read's
     * window. A sum more than 2.5 percent above the read is a note, never a violation: a character also played on
     * another PC, or with the addon off, honestly exceeds it. With a character, only the stores that character carries.
     *
     * @return list<string> one note per metric above the ceiling
     */
    public function handle(?int $characterId): array
    {
        $notes = [];
        foreach ($this->latest() as $read) {
            $characters = $this->carriers($read);
            if ($characterId !== null && ! in_array($characterId, $characters, true)) {
                continue;
            }
            $sum = $this->sum($read, $characters);
            foreach (self::METRICS as $metric) {
                $ceiling = (float) ($read['amounts'][$metric] ?? 0);
                if (isset($read['amounts'][$metric]) && $sum[$metric] > $ceiling * self::TOLERANCE) {
                    $notes[] = "{$read['origin']} store '{$read['store']}' {$read['kind']} {$metric}: Scarlet and its counted claims add up to {$sum[$metric]} and the account's latest read says {$read['amounts'][$metric]}";
                }
            }
        }

        return $notes;
    }

    /** @return list<array{origin: string, store: string, kind: string, dims: string, from: int, to: int, amounts: array<string, int|float>}> */
    private function latest(): array
    {
        return array_values(array_map(fn (stdClass $row): array => [
            'origin' => (string) $row->origin,
            'store' => (string) $row->store,
            'kind' => (string) $row->kind,
            'dims' => (string) $row->dims,
            'from' => (int) $row->from_at,
            'to' => (int) $row->to_at,
            'amounts' => $this->amounts((string) $row->amounts),
        ], DB::select(
            <<<'SQL'
            select distinct on (origin, store, kind, dims) origin, store, kind, dims::text as dims, coalesce(from_at, 0) as from_at, to_at, amounts::text as amounts
            from addon_claims
            where scope = ? and addon_claims.window = ?
            order by origin, store, kind, dims, to_at desc, id
            SQL,
            [self::SCOPE_ACCOUNT, self::LIFETIME],
        )));
    }

    /** @return array<string, int|float> */
    private function amounts(string $json): array
    {
        /** @var array<string, int|float> */
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array{origin: string, store: string, kind: string, dims: string, from: int, to: int, amounts: array<string, int|float>}  $read
     * @return list<int>
     */
    private function carriers(array $read): array
    {
        return array_values(array_map(fn (stdClass $row): int => (int) $row->character_id, DB::select(
            <<<'SQL'
            select distinct character_id from addon_claims
            where scope = ? and addon_claims.window = ? and origin = ? and store = ? and kind = ? and dims = ?::jsonb and to_at = ?
            order by character_id
            SQL,
            [self::SCOPE_ACCOUNT, self::LIFETIME, $read['origin'], $read['store'], $read['kind'], $read['dims'], $read['to']],
        )));
    }

    /**
     * @param  array{origin: string, store: string, kind: string, dims: string, from: int, to: int, amounts: array<string, int|float>}  $read
     * @param  list<int>  $characters
     * @return array<string, int|float>
     */
    private function sum(array $read, array $characters): array
    {
        $sum = ['casts' => 0, 'landed' => 0, 'items' => 0];
        if (AddonKind::tryFrom($read['kind'])?->hasRows() !== true) {
            return $sum;
        }
        $fish = GatherKind::Fish->value;
        $gathers = DB::table('gathers')->whereIn('character_id', $characters)->where('kind', $fish)
            ->whereRaw('at between to_timestamp(?) and to_timestamp(?)', [$read['from'], $read['to']])
            ->selectRaw('count(*) as casts, count(*) filter (where looted) as landed')->first();
        $sum['casts'] = (int) ($gathers->casts ?? 0);
        $sum['landed'] = (int) ($gathers->landed ?? 0);
        $sum['items'] = (int) DB::table('gather_items')->whereIn('character_id', $characters)->where('kind', $fish)->whereNull('container_item_id')
            ->whereRaw('at between to_timestamp(?) and to_timestamp(?)', [$read['from'], $read['to']])->sum('quantity');

        $counted = DB::select(
            <<<'SQL'
            select a.metric, sum((a.value #>> '{}')::numeric) as total
            from addon_claims c cross join lateral jsonb_each(c.counted) as a(metric, value)
            where c.character_id = any(?::bigint[]) and c.kind = ? and c.status in (?, ?) and c.to_at between ? and ?
            group by a.metric
            SQL,
            ['{'.implode(',', $characters).'}', $read['kind'], ...ClaimStatus::adding(), $read['from'], $read['to']],
        );
        foreach ($counted as $row) {
            if (isset($sum[(string) $row->metric])) {
                $sum[(string) $row->metric] += (int) $row->total;
            }
        }

        return $sum;
    }
}
