<?php

namespace App\Actions\Sessions;

use App\Support\Feat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The `feats` of a session recap (CONTRACTS.md, "Session recap"). `earned` is each tier a feat reached in the recount that followed the session,
 * from `feat_events`; the events carry the session they were counted for and not the minute, so `at` is the end of the session. `near` is the three
 * feats with the largest share of the way to their next tier, as the character stands now.
 */
class ShowSessionFeats
{
    private const int NEAR = 3;

    /** @return array{earned: list<array{key: string, from: int, tier: int, at: int, value: int|float}>, near: list<array{key: string, tier: int, value: int|float, next: int, left: int|float}>} */
    public function handle(int $characterId, int $start, int $played, bool $economy): array
    {
        $values = DB::table('character_feats')->where('character_id', $characterId)->pluck('value', 'feat');
        $earned = [];
        foreach (DB::table('feat_events')->where('character_id', $characterId)->where('session_start', $start)->orderByDesc('tier')->orderBy('id')->get(['feat', 'tier', 'from_tier']) as $event) {
            $feat = Feat::tryFrom((string) $event->feat);
            if ($feat === null || ($feat->economy() && ! $economy)) {
                continue;
            }
            $earned[] = ['key' => $feat->value, 'from' => (int) $event->from_tier, 'tier' => (int) $event->tier, 'at' => $played, 'value' => $this->value($feat, $values[$feat->value] ?? 0)];
        }

        return ['earned' => $earned, 'near' => $this->near($values, $economy)];
    }

    /**
     * @param  Collection<string, mixed>  $values
     * @return list<array{key: string, tier: int, value: int|float, next: int, left: int|float}>
     */
    private function near(Collection $values, bool $economy): array
    {
        $open = [];
        foreach ($values as $key => $stored) {
            $feat = Feat::tryFrom((string) $key);
            if ($feat === null || ($feat->economy() && ! $economy)) {
                continue;
            }
            $value = $this->value($feat, $stored);
            $tier = $feat->tier($value);
            $next = $feat->thresholds()[$tier] ?? null;
            if ($next === null || $value <= 0) {
                continue;
            }
            $from = $feat->thresholds()[$tier - 1] ?? 0;
            $open[] = ['progress' => ($value - $from) / ($next - $from), 'row' => ['key' => $feat->value, 'tier' => $tier, 'value' => $value, 'next' => $next, 'left' => max(0, $next - $value)]];
        }
        usort($open, fn (array $a, array $b): int => [$b['progress'], $a['row']['key']] <=> [$a['progress'], $b['row']['key']]);

        return array_map(fn (array $item): array => $item['row'], array_slice($open, 0, self::NEAR));
    }

    private function value(Feat $feat, mixed $stored): int|float
    {
        return $feat->decimals() === 0 ? (int) floor((float) $stored) : round((float) $stored, $feat->decimals());
    }
}
