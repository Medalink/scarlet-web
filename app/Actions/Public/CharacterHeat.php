<?php

namespace App\Actions\Public;

use App\Actions\Public\Character\HeatCells;
use App\Models\Character;
use App\Support\WorldLayer;

class CharacterHeat
{
    public function __construct(private readonly HeatCells $heat) {}

    /**
     * The cells the hero map of a character page draws, as the page would give them: null when the character is unknown or
     * private to this viewer.
     *
     * @return array{grid: list<int>, cells: list<int>}|null cells is one flat list: cell_i, cell_j and the points of each, in the order first walked
     */
    public function handle(string $name, ?int $viewerAccountId): ?array
    {
        $character = Character::query()->where('name_key', Character::keyFor($name))->first(['id', 'account_id', 'is_public']);
        if ($character === null || ! $character->visibleTo($viewerAccountId)) {
            return null;
        }

        $flat = [];
        foreach ($this->heat->handle($character->id)['cells'] as [$i, $j, $points]) {
            array_push($flat, $i, $j, $points);
        }

        return ['grid' => [WorldLayer::Paths->columns()], 'cells' => $flat];
    }
}
