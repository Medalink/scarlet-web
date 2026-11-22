<?php

namespace App\Actions\Ingest;

use Illuminate\Support\Facades\DB;

class InvalidateReconciles
{
    public function __construct(private readonly AddonClaimSiblings $siblings) {}

    /**
     * Mark every addon day of the characters in `$characterIds`, whether or not they hold claims, and of the characters
     * linked to them that hold claims as not reconciled: their `reconcile_version` becomes null (docs/DESIGN.md,
     * Reconcile). A character with no claims has days too (a day whose lines were all skipped), and the claims it
     * receives next must not sit beside a version stamped before them. A write that can change
     * what a claim counts calls this inside its own transaction, so the verdicts a page reads are never stamped current
     * beside rows or claims they were not decided on; the queued reconcile is the fast path and the scheduled outdated
     * pass the guarantee. Call it after the transaction's character locks and before its writes: the day rows are
     * locked lowest character first, the order every caller uses, so two writers of one linked set wait for each other
     * instead of deadlocking.
     *
     * @param  list<int>  $characterIds
     * @return list<int> the characters whose days were marked, lowest first
     */
    public function handle(array $characterIds): array
    {
        $ids = array_map(intval(...), $characterIds);
        foreach ($characterIds as $characterId) {
            $ids = [...$ids, ...$this->siblings->handle($characterId)];
        }
        $ids = array_values(array_unique($ids));
        sort($ids);
        if ($ids === []) {
            return [];
        }

        DB::table('addon_days')->whereIn('character_id', $ids)->orderBy('character_id')->orderBy('day_key')->lockForUpdate()->pluck('day_key');
        DB::table('addon_days')->whereIn('character_id', $ids)->whereNotNull('reconcile_version')->update(['reconcile_version' => null]);

        return $ids;
    }
}
