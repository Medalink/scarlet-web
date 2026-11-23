<?php

namespace App\Actions\Ingest;

use App\Support\DecodedSession;
use App\Support\FactTable;
use App\Support\WorldPlacement;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InsertFactRows
{
    /** The keys every decoder fact row starts with; the job fills the columns of the same names itself. */
    private const array COMMON = ['t', 'session', 'at', 'level', 'zone_id'];

    /** PostgreSQL allows 65535 bound values in one statement. */
    private const int MAX_BINDINGS = 60000;

    public function __construct(private readonly WorldPlacement $placement) {}

    /**
     * Insert the fact rows of one decoded session, with the columns every fact row carries.
     * The tables go in FactTable declaration order, the order DeleteFactRows walks, so concurrent
     * decodes of different sessions take every table's lock in the same order and cannot deadlock.
     *
     * @return int rows inserted
     */
    public function handle(int $characterId, int $sessionStart, int $rulesetId, bool $counted, DecodedSession $decoded): int
    {
        $inserted = 0;

        foreach (FactTable::cases() as $table) {
            if (! $decoded->hasRows($table)) {
                continue;
            }
            $rows = $decoded->rows($table);
            $renames = $table->renames();
            $skip = array_flip([...self::COMMON, ...$table->dropped()]);
            $instants = array_flip($table->instants());
            $width = 0;
            $batch = [];

            foreach ($rows as $row) {
                $out = [
                    'at' => FactTable::instant((int) $row['at']),
                    'character_id' => $characterId,
                    'session_start' => $sessionStart,
                    'ruleset_id' => $rulesetId,
                    'level' => $row['level'],
                    'zone_id' => $row['zone_id'],
                    'counted' => $counted,
                ];
                foreach ($row as $key => $value) {
                    if (isset($skip[$key])) {
                        continue;
                    }
                    $column = $renames[$key] ?? $key;
                    $out[$column] = $value !== null && isset($instants[$column]) ? FactTable::instant((int) $value) : $value;
                }
                if (WorldPlacement::places($table)) {
                    $world = $this->placement->forRow($table, $row);
                    $out['u'] = $world['u'] ?? null;
                    $out['v'] = $world['v'] ?? null;
                }
                if ($table === FactTable::Flights) {
                    $end = $this->placement->flightEnd($row);
                    $out['to_u'] = $end['u'] ?? null;
                    $out['to_v'] = $end['v'] ?? null;
                }
                $width = count($out);
                $batch[] = $out;

                if (count($batch) >= intdiv(self::MAX_BINDINGS, $width)) {
                    $inserted += $this->flush($table, $batch);
                    $batch = [];
                }
            }
            if ($batch !== []) {
                $inserted += $this->flush($table, $batch);
            }
        }

        return $inserted;
    }

    /**
     * Insert the batch in one statement; when that fails, insert the rows one by one inside a
     * savepoint each, so a row its columns cannot keep is skipped with a note in the log and the
     * rest of the batch, and the session's transaction, carries on.
     *
     * @param  list<array<string, mixed>>  $batch
     */
    private function flush(FactTable $table, array $batch): int
    {
        try {
            DB::table($table->value)->insert($batch);

            return count($batch);
        } catch (QueryException) {
            $inserted = 0;
            foreach ($batch as $row) {
                try {
                    DB::transaction(fn (): bool => DB::table($table->value)->insert($row));
                    $inserted++;
                } catch (QueryException $e) {
                    Log::warning('Fact row skipped.', ['table' => $table->value, 'error' => $e->getMessage()]);
                }
            }

            return $inserted;
        }
    }
}
