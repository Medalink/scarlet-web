<?php

namespace App\Actions\Feats;

use App\Models\Character;
use Illuminate\Support\Facades\DB;

/** Stores the feat counts of a character in `character_feats`. Safe to run twice: the same rows result, and a row whose feat no longer applies or no longer exists is deleted. */
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
        foreach ($this->count->handle($character) as $feat => $value) {
            if ($value !== null) {
                $rows[] = ['character_id' => $characterId, 'feat' => $feat, 'value' => $value, 'updated_at' => now()];
            }
        }

        DB::transaction(function () use ($characterId, $rows): void {
            if ($rows !== []) {
                DB::table('character_feats')->upsert($rows, ['character_id', 'feat'], ['value', 'updated_at']);
            }
            DB::table('character_feats')->where('character_id', $characterId)->whereNotIn('feat', array_column($rows, 'feat'))->delete();
        });
    }
}
