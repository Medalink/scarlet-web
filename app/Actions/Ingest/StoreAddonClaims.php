<?php

namespace App\Actions\Ingest;

use App\Support\Database\BinaryValue;
use App\Support\Database\PartIds;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class StoreAddonClaims
{
    private const int ROWS_PER_INSERT = 100;

    private const string CLAIM_ROW = 'claim';

    private const string DAY_ROW = 'day';

    private const string LIFETIME = 'life';

    public function __construct(private readonly MarkPartsDecoded $markDecoded) {}

    /**
     * Replace what one UTC day of `counts` parts says about the claims of one character. The parts' rows in
     * `addon_claim_parts` are rewritten, a claim whose key is new for the character is inserted, a claim takes the
     * amounts of the carrying part whose id sorts highest, and a claim no part carries any more is deleted. Nothing
     * but the addon tables is read or written, so Scarlet's own rows cannot move. With no rows the day is forgotten.
     * A character is locked while this runs, as for a session. Safe to run twice.
     *
     * @param  list<array<string, mixed>>  $rows  `claim` rows and the closing `day` row of `scarlet-part decode --counts`
     * @param  list<string>  $partIds  lower-case hex ids of the `counts` parts `$rows` came from, marked decoded
     */
    public function handle(int $characterId, int $dayStart, array $rows, array $partIds): void
    {
        $day = $this->dayRow($rows);

        DB::transaction(function () use ($characterId, $dayStart, $rows, $partIds, $day): void {
            if (! DB::table('characters')->where('id', $characterId)->lockForUpdate()->exists()) {
                return;
            }

            $this->forgetPartRows($partIds);
            $this->carryClaims($characterId, $rows);
            $this->takeAmountsOfHighestPart($characterId);
            $this->deleteClaimsNoPartCarries($characterId);
            $this->recordDay($characterId, $dayStart, $day);
            $this->markDecoded->handle($characterId, $dayStart, $partIds);
        });

        $skipped = $day === null ? [] : array_filter([
            'code' => (int) $day['skipped_code'],
            'kind' => (int) $day['skipped_kind'],
            'line' => (int) $day['skipped_line'],
            'duplicate' => (int) $day['skipped_duplicate'],
        ]);
        if ($skipped !== []) {
            Log::info('Addon claim lines skipped.', ['character_id' => $characterId, 'day_key' => $dayStart] + $skipped);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private function dayRow(array $rows): ?array
    {
        foreach ($rows as $row) {
            if ($row['t'] === self::DAY_ROW) {
                return $row;
            }
        }
        if ($rows !== []) {
            throw new RuntimeException('scarlet-part decode --counts wrote claims and no closing day row.');
        }

        return null;
    }

    /** @param  list<string>  $partIds */
    private function forgetPartRows(array $partIds): void
    {
        if ($partIds !== []) {
            DB::table('addon_claim_parts')->whereRaw("part_id in (select decode(wanted, 'hex') from unnest(?::text[]) as wanted)", [PartIds::literal($partIds)])->delete();
        }
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function carryClaims(int $characterId, array $rows): void
    {
        $carried = [];
        foreach ($rows as $row) {
            if ($row['t'] !== self::CLAIM_ROW) {
                continue;
            }
            $amounts = $this->json($row['amounts']);
            $from = isset($row['from']) ? (int) $row['from'] : null;
            $carried[] = [
                'claim_id' => $this->claimId($characterId, $row, $amounts, $from),
                'part_id' => BinaryValue::bind((string) hex2bin((string) $row['part'])),
                'amounts' => $amounts,
                'from_at' => $from,
            ];
        }
        foreach (array_chunk($carried, self::ROWS_PER_INSERT) as $chunk) {
            DB::table('addon_claim_parts')->insert($chunk);
        }
    }

    /** @param  array<string, mixed>  $row */
    private function claimId(int $characterId, array $row, string $amounts, ?int $from): int
    {
        $key = [
            'character_id' => $characterId,
            'origin' => (string) $row['origin'],
            'store' => (string) $row['store'],
            'scope' => (string) $row['scope'],
            'window' => (string) $row['window'],
            'kind' => (string) $row['kind'],
            'to_at' => (int) $row['to'],
            'dims' => $this->json($row['dims']),
        ];
        $keyFrom = $key['window'] === self::LIFETIME ? ($from ?? 0) : 0;

        $id = DB::table('addon_claims')->where($key)->where('key_from', $keyFrom)->value('id');

        return (int) ($id ?? DB::table('addon_claims')->insertGetId($key + ['from_at' => $from, 'amounts' => $amounts]));
    }

    private function takeAmountsOfHighestPart(int $characterId): void
    {
        DB::update(
            <<<'SQL'
            update addon_claims as claim
            set amounts = highest.amounts, from_at = highest.from_at
            from (
                select distinct on (carrying.claim_id) carrying.claim_id, carrying.amounts, carrying.from_at
                from addon_claim_parts as carrying
                join addon_claims as owner on owner.id = carrying.claim_id
                where owner.character_id = ?
                order by carrying.claim_id, carrying.part_id desc
            ) as highest
            where claim.id = highest.claim_id
                and (claim.amounts is distinct from highest.amounts or claim.from_at is distinct from highest.from_at)
            SQL,
            [$characterId],
        );
    }

    private function deleteClaimsNoPartCarries(int $characterId): void
    {
        DB::table('addon_claims')
            ->where('character_id', $characterId)
            ->whereNotExists(fn ($parts) => $parts->selectRaw('1')->from('addon_claim_parts')->whereColumn('addon_claim_parts.claim_id', 'addon_claims.id'))
            ->delete();
    }

    /** @param  array<string, mixed>|null  $day */
    private function recordDay(int $characterId, int $dayStart, ?array $day): void
    {
        if ($day === null) {
            DB::table('addon_days')->where('character_id', $characterId)->where('day_key', $dayStart)->delete();

            return;
        }

        DB::table('addon_days')->upsert(
            [['character_id' => $characterId, 'day_key' => $dayStart, 'decoder_version' => (int) $day['decoder_version']]],
            ['character_id', 'day_key'],
            ['decoder_version'],
        );
    }

    private function json(mixed $object): string
    {
        return json_encode($object, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT);
    }
}
