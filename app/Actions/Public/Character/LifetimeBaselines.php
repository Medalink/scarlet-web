<?php

namespace App\Actions\Public\Character;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LifetimeBaselines
{
    /** How far back a lock line can be and still hold: the longest lock is a week, an extended one a few more. */
    private const int LOCK_LOOKBACK_DAYS = 30;

    /** Lock lines read for one character. */
    private const int LOCK_LINES = 500;

    /**
     * What the client said about the character's whole life in its latest reads: quests completed, titles known and
     * zones explored, each null while no read of it is stored, and the instance locks that still hold. Each lock is
     * the latest line for its instance, difficulty and kind, shown while its reset is ahead and it is locked or
     * extended. Null when there is nothing to show.
     *
     * @return array{quests: int|null, titles: int|null, zones: int|null, locks: list<array{name: string|null, raid: int, done: int|null, total: int|null, extended: bool, resets_at: string}>}|null
     */
    public function handle(int $characterId, ?Carbon $now = null): ?array
    {
        $now ??= Carbon::now('UTC');
        $totals = DB::table('characters')->where('id', $characterId)->first(['quests_completed', 'titles_known', 'zones_explored']);
        $locks = $this->locks($characterId, $now);
        $quests = $totals?->quests_completed === null ? null : (int) $totals->quests_completed;
        $titles = $totals?->titles_known === null ? null : (int) $totals->titles_known;
        $zones = $totals?->zones_explored === null ? null : (int) $totals->zones_explored;
        if ($quests === null && $titles === null && $zones === null && $locks === []) {
            return null;
        }

        return ['quests' => $quests, 'titles' => $titles, 'zones' => $zones, 'locks' => $locks];
    }

    /** @return list<array{name: string|null, raid: int, done: int|null, total: int|null, extended: bool, resets_at: string}> */
    private function locks(int $characterId, Carbon $now): array
    {
        $rows = DB::table('instance_locks')->where('character_id', $characterId)->where('at', '>', $now->copy()->subDays(self::LOCK_LOOKBACK_DAYS))
            ->orderByDesc('at')->limit(self::LOCK_LINES)
            ->get(['at', 'name', 'instance_id', 'difficulty_id', 'reset_seconds', 'locked', 'extended', 'raid', 'done', 'total']);
        $latest = [];
        foreach ($rows as $row) {
            $key = ($row->instance_id ?? '').'|'.($row->difficulty_id ?? '').'|'.$row->raid.'|'.($row->instance_id === null ? (string) $row->name : '');
            $latest[$key] ??= $row;
        }
        $locks = [];
        foreach ($latest as $row) {
            if ($row->reset_seconds === null || (! $row->locked && ! $row->extended)) {
                continue;
            }
            $resets = Carbon::parse((string) $row->at, 'UTC')->addSeconds((int) $row->reset_seconds);
            if ($resets->lessThanOrEqualTo($now)) {
                continue;
            }
            $locks[] = [
                'name' => $row->name, 'raid' => (int) $row->raid,
                'done' => $row->done === null ? null : (int) $row->done, 'total' => $row->total === null ? null : (int) $row->total,
                'extended' => (bool) $row->extended, 'resets_at' => $resets->utc()->toIso8601ZuluString(),
            ];
        }
        usort($locks, fn (array $a, array $b): int => [$a['resets_at'], (string) $a['name']] <=> [$b['resets_at'], (string) $b['name']]);

        return $locks;
    }
}
