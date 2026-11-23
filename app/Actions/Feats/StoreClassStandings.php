<?php

namespace App\Actions\Feats;

use App\Actions\Public\ClassPage\ReadClassParts;
use App\Models\Character;
use App\Support\ClassPage\ClassRead;
use App\Support\ClassPage\Drinking;
use App\Support\ClassPage\StandingFigure;
use Illuminate\Support\Facades\DB;

/**
 * Stores what a class page shares with other pages, in one transaction: the figures it compares with the class in
 * `class_standings` (one row for each figure of the character's class builder, and the time spent drinking for every
 * class that has a builder, at the character's level now) and the band of figures `/c/{name}` shows (`class_bands`). A
 * figure the character's rows cannot answer has no row. Safe to run twice. A character whose class has no page keeps no
 * rows. The character's row is locked before its rows are read, as a decode and a group removal lock it, so what is
 * stored was true when the lock was taken.
 */
class StoreClassStandings
{
    public function __construct(private readonly ReadClassParts $read, private readonly StoreClassBand $band) {}

    public function handle(int $characterId): void
    {
        DB::transaction(function () use ($characterId): void {
            $character = Character::query()->whereKey($characterId)->lockedInOrder()->first();
            if ($character === null) {
                return;
            }
            $read = $this->read->handle($character);
            $now = now();
            $ruleset = DB::table('sessions')->where('character_id', $characterId)->orderByDesc('session_start')->value('ruleset_id');
            $rows = $read === null ? [] : array_map(fn (StandingFigure $figure): array => [
                'character_id' => $characterId,
                'class' => $read->class->value,
                'level' => (int) $character->level,
                'ruleset_id' => $ruleset,
                'figure' => $figure->key,
                'value' => $figure->value,
                'counted_at' => $now,
            ], $this->figures($read));

            if ($rows !== []) {
                DB::table('class_standings')->upsert($rows, ['character_id', 'figure'], ['class', 'level', 'ruleset_id', 'value', 'counted_at']);
            }
            DB::table('class_standings')->where('character_id', $characterId)->whereNotIn('figure', array_column($rows, 'figure'))->delete();
            $this->band->handle($characterId, $read, $now);
        });
    }

    /** @return list<StandingFigure> the builder's figures and the drinking one every class stores, whatever its builder draws */
    private function figures(ClassRead $read): array
    {
        $figures = [];
        foreach ($read->parts->standings as $figure) {
            $figures[$figure->key] = $figure;
        }
        $drinking = Drinking::figure($read->rows);
        if ($drinking !== null) {
            $figures[$drinking->key] ??= $drinking;
        }

        return array_values($figures);
    }
}
