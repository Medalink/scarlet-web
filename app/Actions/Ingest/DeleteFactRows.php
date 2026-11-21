<?php

namespace App\Actions\Ingest;

use App\Support\FactTable;
use App\Support\SharingGroup;
use Illuminate\Support\Facades\DB;

class DeleteFactRows
{
    /**
     * Delete decoded rows of some characters: those of one sharing group, or of every table; of one
     * session, or of every session. The rollups learn of it from TimescaleDB's invalidation log.
     *
     * @param  list<int>  $characterIds
     * @param  string|null  $group  null for every table
     * @param  int|null  $sessionStart  null for every session
     */
    public function handle(array $characterIds, ?string $group = null, ?int $sessionStart = null): int
    {
        $deleted = 0;
        $tables = $group === null ? FactTable::cases() : FactTable::inGroup($group);

        foreach ($tables as $table) {
            $query = DB::table($table->value)->whereIn('character_id', $characterIds);
            if ($sessionStart !== null) {
                $query->where('session_start', $sessionStart);
            }
            if ($group === SharingGroup::ECONOMY && $table === FactTable::MoneyChanges) {
                // The opening balance comes from the `S` line, which is core's.
                $query->where('src', FactTable::MONEY_LINE);
            }
            $deleted += $query->delete();
        }

        if ($group === SharingGroup::SOCIAL && $sessionStart === null) {
            $deleted += DB::table('pseudonyms')->whereIn('character_id', $characterIds)->delete();
        }
        if ($group === null && $sessionStart === null) {
            $deleted += DB::table('character_levels')->whereIn('character_id', $characterIds)->delete();
        }

        return $deleted;
    }
}
