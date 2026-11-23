<?php

namespace App\Actions\Ingest;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreImportedRecords
{
    private const int ROWS_PER_INSERT = 500;

    /** The widest value an integer (signed 32-bit) column of the table keeps. */
    private const int MAX_INTEGER = 2147483647;

    public function __construct(private readonly MarkPartsDecoded $markDecoded) {}

    /**
     * Replace the imported records of one UTC day of one character with a fresh decode. A character
     * is locked while this runs, as for a session. Safe to run twice. A row whose columns cannot
     * keep it is skipped with a note in the log, never fatal to the day.
     *
     * @param  iterable<array<string, mixed>>  $rows  `imported` rows of `scarlet-part decode --imports`, read a row at a time
     * @param  list<string>  $partIds  lower-case hex ids of the parts `$rows` came from, marked decoded
     */
    public function handle(int $characterId, int $dayStart, iterable $rows, array $partIds): void
    {
        DB::transaction(function () use ($characterId, $dayStart, $rows, $partIds): void {
            if (! DB::table('characters')->where('id', $characterId)->lockForUpdate()->exists()) {
                return;
            }

            DB::table('imported_records')->where('character_id', $characterId)->where('day_start', $dayStart)->delete();

            $records = [];
            foreach ($rows as $row) {
                if (count($records) >= self::ROWS_PER_INSERT) {
                    $this->insert($records);
                    $records = [];
                }
                $records[] = [
                    'character_id' => $characterId,
                    'day_start' => $dayStart,
                    'kind' => (string) $row['kind'],
                    'at' => max(0, intdiv((int) $row['at'], 1000)),
                    'source' => mb_substr((string) $row['source'], 0, 24),
                    'via' => isset($row['via']) && $row['via'] !== '' ? mb_substr((string) $row['via'], 0, 64) : null,
                    'item_id' => isset($row['item_id']) ? self::unsigned((int) $row['item_id']) : null,
                    'quantity' => isset($row['quantity']) ? self::unsigned((int) $row['quantity']) : null,
                    'copper' => (int) $row['copper'],
                ];
            }
            if ($records !== []) {
                $this->insert($records);
            }

            $this->markDecoded->handle($characterId, $dayStart, $partIds);
        });
    }

    /** @param  list<array<string, mixed>>  $chunk */
    private function insert(array $chunk): void
    {
        try {
            DB::transaction(fn (): bool => DB::table('imported_records')->insert($chunk));
        } catch (QueryException) {
            foreach ($chunk as $record) {
                try {
                    DB::transaction(fn (): bool => DB::table('imported_records')->insert($record));
                } catch (QueryException $e) {
                    Log::warning('Imported record skipped.', ['character_id' => $record['character_id'], 'day_start' => $record['day_start'], 'error' => $e->getMessage()]);
                }
            }
        }
    }

    /** The value inside what the table's integer columns keep. */
    private static function unsigned(int $value): int
    {
        return max(0, min($value, self::MAX_INTEGER));
    }
}
