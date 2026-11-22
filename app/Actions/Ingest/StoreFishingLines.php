<?php

namespace App\Actions\Ingest;

use App\Support\FishingCode;
use Illuminate\Support\Facades\DB;

class StoreFishingLines
{
    private const int ROWS_PER_INSERT = 500;

    private const string PSEUDONYM_ROW = 'pn';

    public function __construct(private readonly MarkPartsDecoded $markDecoded) {}

    /**
     * Replace the fishing lines of one part key of one character with a fresh decode: a UTC day of `fishing` parts, or
     * a session whose `addons` parts hold catches. The pseudonyms a fishing part explains join the character's
     * `pseudonyms`, as a social part's do. A character is locked while this runs, as for a session. Safe to run twice.
     *
     * @param  list<array<string, mixed>>  $rows  rows of `scarlet-part decode --fishing`
     * @param  list<string>  $partIds  lower-case hex ids of the `fishing` parts `$rows` came from, marked decoded
     */
    public function handle(int $characterId, int $sessionStart, array $rows, array $partIds): void
    {
        DB::transaction(function () use ($characterId, $sessionStart, $rows, $partIds): void {
            if (! DB::table('characters')->where('id', $characterId)->lockForUpdate()->exists()) {
                return;
            }

            DB::table('fishing_lines')->where('character_id', $characterId)->where('session_start', $sessionStart)->delete();

            $lines = [];
            $pseudonyms = [];
            foreach ($rows as $row) {
                if ($row['t'] === self::PSEUDONYM_ROW) {
                    $pseudonyms[(string) $row['pseudonym']] = [
                        'character_id' => $characterId,
                        'pseudonym' => (string) $row['pseudonym'],
                        'name' => mb_substr((string) $row['name'], 0, 64),
                        'updated_at' => now(),
                    ];

                    continue;
                }
                $lines[] = [
                    'character_id' => $characterId,
                    'session_start' => $sessionStart,
                    'code' => FishingCode::from((string) $row['code'])->value,
                    'at' => intdiv((int) $row['at'], 1000),
                    'fields' => json_encode(array_diff_key($row, ['t' => true, 'code' => true, 'at' => true]), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
                ];
            }
            foreach (array_chunk($lines, self::ROWS_PER_INSERT) as $chunk) {
                DB::table('fishing_lines')->insert($chunk);
            }
            foreach (array_chunk(array_values($pseudonyms), self::ROWS_PER_INSERT) as $chunk) {
                DB::table('pseudonyms')->upsert($chunk, ['character_id', 'pseudonym'], ['name', 'updated_at']);
            }

            $this->markDecoded->handle($characterId, $sessionStart, $partIds);
        });
    }
}
