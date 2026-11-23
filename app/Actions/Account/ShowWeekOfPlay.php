<?php

namespace App\Actions\Account;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ShowWeekOfPlay
{
    /**
     * What the characters gained since the start of the UTC week: the levels each one's counted sessions climbed and the
     * feat tiers each one reached.
     *
     * @param  list<int>  $characterIds
     * @return array{since: string, levels: array<int, int>, feats: list<array{character_id: int, feat: string, tier: int}>}
     */
    public function handle(array $characterIds): array
    {
        $since = Carbon::now('UTC')->startOfWeek();
        $levels = [];
        $feats = [];
        if ($characterIds !== []) {
            foreach (DB::table('sessions')->whereIn('character_id', $characterIds)->where('counted', true)->where('start_at', '>=', $since)->whereNotNull('level_end')
                ->groupBy('character_id')->selectRaw('character_id, max(level_end) - min(level_start) as gained')->get() as $row) {
                if ($row->gained > 0) {
                    $levels[(int) $row->character_id] = (int) $row->gained;
                }
            }
            foreach (DB::table('feat_events')->whereIn('character_id', $characterIds)->where('at', '>=', $since)->whereColumn('tier', '>', 'from_tier')
                ->orderByDesc('at')->get(['character_id', 'feat', 'tier']) as $row) {
                $feats[] = ['character_id' => (int) $row->character_id, 'feat' => (string) $row->feat, 'tier' => (int) $row->tier];
            }
        }

        return ['since' => $since->toDateString(), 'levels' => $levels, 'feats' => $feats];
    }
}
