<?php

namespace App\Actions\Upload;

use App\Actions\Ingest\AddonClaimSiblings;
use App\Actions\Ingest\DeleteFactRows;
use App\Jobs\ReconcileCharacterClaims;
use App\Jobs\RefreshRollups;
use App\Models\Character;
use App\Models\Install;
use App\Models\Part;
use App\Support\DecompressionLimit;
use App\Support\RefreshWindow;
use Illuminate\Support\Facades\DB;

class RemoveEverything
{
    public function __construct(
        private readonly DeleteFactRows $deleteRows,
        private readonly AddonClaimSiblings $siblings,
    ) {}

    /**
     * Remove everything the install's owner uploaded: every part, every decoded row and the characters
     * themselves, which frees their names. Installs and their keys stay. The addon claims of the characters linked to
     * the removed ones are reconciled again.
     *
     * @return array{characters_removed: int, parts_removed: int, rows_removed: int}
     */
    public function handle(Install $install): array
    {
        /** @var list<int> $characterIds */
        $characterIds = Character::query()->ownedBy($install)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $window = RefreshWindow::ofCharacters($characterIds);
        $linked = array_values(array_diff(array_merge(...array_map(fn (int $id): array => $this->siblings->handle($id), $characterIds)), $characterIds));

        [$characters, $parts, $rows] = DB::transaction(function () use ($characterIds): array {
            DecompressionLimit::lift();
            // Wait for a running decode of these characters first; both take the row lock before the fact rows.
            Character::query()->whereIn('id', $characterIds)->lockedInOrder()->pluck('id');
            $parts = Part::query()->whereIn('character_id', $characterIds)->count();
            $rows = $this->deleteRows->handle($characterIds);
            // Parts, sessions, pseudonyms and the install links go with the character.
            $characters = Character::query()->whereIn('id', $characterIds)->delete();

            return [$characters, $parts, $rows];
        });

        if ($window !== null) {
            RefreshRollups::dispatch($window['from'], $window['until']);
        }
        foreach (array_unique($linked) as $characterId) {
            ReconcileCharacterClaims::dispatch($characterId);
        }

        return ['characters_removed' => $characters, 'parts_removed' => $parts, 'rows_removed' => $rows];
    }
}
