<?php

namespace App\Actions\Feats;

use App\Support\Feat;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Runs the feat count of a character and notes what it changed. A feat that holds a higher tier afterwards than before gets one
 * `feat_events` row (the tier it holds now and the tier it held), and `characters.feat_score` is set from the tiers held. Only the
 * refresh calls it, so a catalog change, which stores counts on its own, writes no event.
 */
class RecordFeatTiers
{
    /** @param Closure(): void $count stores the counts of the character */
    public function handle(int $characterId, Closure $count): void
    {
        $before = $this->held($characterId);
        $count();
        $after = $this->held($characterId);

        $session = DB::table('sessions')->where('character_id', $characterId)->max('session_start');
        $now = now();
        $events = [];
        $score = 0;
        foreach ($after as $key => $tier) {
            if (Feat::from($key)->scores()) {
                $score += $tier;
            }
            $from = $before[$key] ?? 0;
            if ($tier > $from) {
                $events[] = ['character_id' => $characterId, 'feat' => $key, 'tier' => $tier, 'from_tier' => $from, 'session_start' => $session === null ? null : (int) $session, 'at' => $now];
            }
        }

        DB::transaction(function () use ($characterId, $events, $score): void {
            if ($events !== []) {
                DB::table('feat_events')->insert($events);
            }
            DB::table('characters')->where('id', $characterId)->update(['feat_score' => $score]);
        });
    }

    /** @return array<string, int> the tier each stored feat of the character holds, by feat key */
    private function held(int $characterId): array
    {
        $held = [];
        foreach (DB::table('character_feats')->where('character_id', $characterId)->pluck('value', 'feat') as $key => $value) {
            $feat = Feat::tryFrom((string) $key);
            if ($feat !== null) {
                $held[$feat->value] = $feat->held((float) $value);
            }
        }

        return $held;
    }
}
