<?php

namespace App\Actions\Feats;

use App\Models\Character;
use App\Support\Feat;
use Illuminate\Support\Facades\DB;

/**
 * Stores the feat counts of a character in `character_feats` and sets `characters.feat_score` from them: the tiers held on the
 * general feats that are not gold feats. Safe to run twice: the same rows result, and a row whose feat no longer applies or no longer exists is deleted.
 */
class StoreCharacterFeats
{
    public function __construct(private readonly CountFeats $count) {}

    public function handle(int $characterId): void
    {
        $character = Character::query()->find($characterId);
        if ($character === null) {
            return;
        }
        $rows = [];
        $score = 0;
        foreach ($this->count->handle($character) as $feat => $value) {
            if ($value === null) {
                continue;
            }
            $rows[] = ['character_id' => $characterId, 'feat' => $feat, 'value' => $value, 'updated_at' => now()];
            $known = Feat::tryFrom((string) $feat);
            if ($known?->scores()) {
                $score += $known->held((float) $value);
            }
        }

        DB::transaction(function () use ($characterId, $rows, $score): void {
            if ($rows !== []) {
                DB::table('character_feats')->upsert($rows, ['character_id', 'feat'], ['value', 'updated_at']);
            }
            DB::table('character_feats')->where('character_id', $characterId)->whereNotIn('feat', array_column($rows, 'feat'))->delete();
            DB::table('characters')->where('id', $characterId)->update(['feat_score' => $score]);
        });
    }
}
