<?php

namespace App\Actions\Account;

use App\Actions\Ingest\SumScarletAndClaims;
use App\Support\ClaimStatus;
use Illuminate\Support\Facades\DB;
use stdClass;

class ShowAccountReferences
{
    private const string LIFETIME = 'life';

    private const string SCOPE_ACCOUNT = 'a';

    public function __construct(private readonly SumScarletAndClaims $sums) {}

    /**
     * The account-scope claims the characters carry, each read once per store (docs/DESIGN.md, Addon counts, rule 7):
     * the latest one, with what Scarlet's rows plus the counted claims of the characters that carry it come to inside
     * its window. A read is left out until every character that carries it has decided its claims again. Owner only:
     * the sum reads only the characters given.
     *
     * @param  list<int>  $characterIds
     * @return list<array{origin: string, store: string, kind: string, from: int, to: int, amounts: array<string, int>, sum: array<string, int>}>
     */
    public function handle(array $characterIds): array
    {
        if ($characterIds === []) {
            return [];
        }
        $rows = DB::select(
            <<<'SQL'
            select c.character_id, c.origin, c.store, c.kind, c.dims::text as dims, coalesce(c.from_at, 0) as from_at, c.to_at,
                c.amounts::text as amounts, c.status,
                exists (select 1 from addon_days d where d.character_id = c.character_id and d.reconcile_version is null) as pending
            from addon_claims c
            where c.character_id = any(?::bigint[]) and c.scope = ? and c.window = ?
            order by c.origin, c.store, c.kind, c.dims::text, c.to_at desc, c.character_id
            SQL,
            ['{'.implode(',', $characterIds).'}', self::SCOPE_ACCOUNT, self::LIFETIME],
        );

        $latest = [];
        foreach ($rows as $row) {
            $key = $row->origin.'|'.$row->store.'|'.$row->kind.'|'.$row->dims;
            $latest[$key] ??= ['to' => (int) $row->to_at, 'rows' => []];
            if ((int) $row->to_at === $latest[$key]['to']) {
                $latest[$key]['rows'][] = $row;
            }
        }

        $references = [];
        foreach ($latest as $read) {
            $first = $read['rows'][0];
            $settled = true;
            foreach ($read['rows'] as $row) {
                $settled = $settled && ! $row->pending && $row->status === ClaimStatus::Reference->value;
            }
            if (! $settled) {
                continue;
            }
            $references[] = [
                'origin' => (string) $first->origin,
                'store' => (string) $first->store,
                'kind' => (string) $first->kind,
                'from' => (int) $first->from_at,
                'to' => (int) $first->to_at,
                'amounts' => $this->amounts((string) $first->amounts),
                'sum' => $this->sums->handle((string) $first->kind, array_map(fn (stdClass $row): int => (int) $row->character_id, $read['rows']), (int) $first->from_at, (int) $first->to_at),
            ];
        }

        return $references;
    }

    /** @return array<string, int> */
    private function amounts(string $json): array
    {
        /** @var array<string, int> */
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
