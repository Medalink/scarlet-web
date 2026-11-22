<?php

namespace App\Actions\Feats;

use App\Actions\Public\ClassPage\ReadClassParts;
use App\Models\Character;
use App\Support\ClassPage\StandingFigure;
use Illuminate\Support\Facades\DB;

/**
 * Stores the figures a class page compares with the class in `class_standings`: one row for each figure of the
 * character's class builder, at the character's level now. Safe to run twice. A character whose class has no page
 * keeps no rows.
 */
class StoreClassStandings
{
    public function __construct(private readonly ReadClassParts $read) {}

    public function handle(int $characterId): void
    {
        $character = Character::query()->find($characterId);
        if ($character === null) {
            return;
        }
        $read = $this->read->handle($character);
        $ruleset = DB::table('sessions')->where('character_id', $characterId)->orderByDesc('session_start')->value('ruleset_id');
        $now = now();
        $rows = $read === null ? [] : array_map(fn (StandingFigure $figure): array => [
            'character_id' => $characterId,
            'class' => $read->class->value,
            'level' => (int) $character->level,
            'ruleset_id' => $ruleset,
            'figure' => $figure->key,
            'value' => $figure->value,
            'counted_at' => $now,
        ], $read->parts->standings);

        DB::transaction(function () use ($characterId, $rows): void {
            if ($rows !== []) {
                DB::table('class_standings')->upsert($rows, ['character_id', 'figure'], ['class', 'level', 'ruleset_id', 'value', 'counted_at']);
            }
            DB::table('class_standings')->where('character_id', $characterId)->whereNotIn('figure', array_column($rows, 'figure'))->delete();
        });
    }
}
