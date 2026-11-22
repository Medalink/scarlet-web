<?php

namespace App\Actions\Upload;

use App\Actions\Ingest\DeleteFactRows;
use App\Jobs\RefreshRollups;
use App\Models\Character;
use App\Models\Install;
use App\Models\Part;
use App\Support\DecompressionLimit;
use App\Support\RefreshWindow;
use Illuminate\Support\Facades\DB;

class RemoveEverything
{
    public function __construct(private readonly DeleteFactRows $deleteRows) {}

    /**
     * Remove everything the install's owner uploaded: every part, every decoded row and the characters
     * themselves, which frees their names. Installs and their keys stay.
     *
     * @return array{characters_removed: int, parts_removed: int, rows_removed: int}
     */
    public function handle(Install $install): array
    {
        /** @var list<int> $characterIds */
        $characterIds = Character::query()->ownedBy($install)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $window = RefreshWindow::ofCharacters($characterIds);

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

        return ['characters_removed' => $characters, 'parts_removed' => $parts, 'rows_removed' => $rows];
    }
}
