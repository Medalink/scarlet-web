<?php

namespace App\Actions\Ingest;

use Illuminate\Support\Facades\DB;
use stdClass;

class AuditAccountCeilings
{
    private const float TOLERANCE = 1.025;

    private const array METRICS = ['casts', 'landed', 'items'];

    private const string LIFETIME = 'life';

    private const string SCOPE_ACCOUNT = 'a';

    public function __construct(private readonly SumScarletAndClaims $sums) {}

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
            $sum = $this->sums->handle($read['kind'], $characters, $read['from'], $read['to']);
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
}
