<?php

namespace App\Actions\Ingest;

use Illuminate\Support\Facades\DB;

class StoreImportedRecords
{
    private const int ROWS_PER_INSERT = 500;

    public function __construct(private readonly MarkPartsDecoded $markDecoded) {}

    /**
     * Replace the imported records of one UTC day of one character with a fresh decode. A character
     * is locked while this runs, as for a session. Safe to run twice.
     *
     * @param  list<array<string, mixed>>  $rows  `imported` rows of `scarlet-part decode --imports`
     * @param  list<string>  $partIds  lower-case hex ids of the parts `$rows` came from, marked decoded
     */
    public function handle(int $characterId, int $dayStart, array $rows, array $partIds): void
    {
        DB::transaction(function () use ($characterId, $dayStart, $rows, $partIds): void {
            if (! DB::table('characters')->where('id', $characterId)->lockForUpdate()->exists()) {
                return;
            }

            DB::table('imported_records')->where('character_id', $characterId)->where('day_start', $dayStart)->delete();

            $records = [];
            foreach ($rows as $row) {
                $records[] = [
                    'character_id' => $characterId,
                    'day_start' => $dayStart,
                    'kind' => (string) $row['kind'],
                    'at' => intdiv((int) $row['at'], 1000),
                    'source' => (string) $row['source'],
                    'via' => isset($row['via']) && $row['via'] !== '' ? (string) $row['via'] : null,
                    'item_id' => isset($row['item_id']) ? (int) $row['item_id'] : null,
                    'quantity' => isset($row['quantity']) ? (int) $row['quantity'] : null,
                    'copper' => (int) $row['copper'],
                ];
            }
            foreach (array_chunk($records, self::ROWS_PER_INSERT) as $chunk) {
                DB::table('imported_records')->insert($chunk);
            }

            $this->markDecoded->handle($characterId, $dayStart, $partIds);
        });
    }
}
