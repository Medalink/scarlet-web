<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Public\WorldDensity;
use App\Actions\Public\WorldHeatCells;
use App\Http\Requests\Api\PublicRequest;
use App\Support\WorldGrid;
use Illuminate\Http\JsonResponse;

/** The heat of the world map: cells of the world grid with a level from 1 (few characters) to 4 (many). */
class MapController extends PublicController
{
    public function density(PublicRequest $request, WorldDensity $density): JsonResponse
    {
        $answer = $density->handle($request->rulesetSlug(), $request->layer(), $request->level(), $request->creatureId());
        if ($answer === null) {
            $this->missing('The ruleset');
        }

        return $this->respond($answer, ['layer' => $request->layer()->value, 'ruleset' => $request->rulesetSlug()]);
    }

    public function heat(PublicRequest $request, WorldHeatCells $cells): JsonResponse
    {
        $answer = $cells->handle($request->rulesetSlug(), $request->layer(), $request->creatureId());
        if ($answer === null) {
            $this->missing('The ruleset');
        }

        return $this->respond(['cells' => $answer], [
            'layer' => $request->layer()->value,
            'ruleset' => $request->rulesetSlug(),
            'grid' => ['columns' => WorldGrid::COLUMNS, 'rows' => WorldGrid::ROWS, 'cell' => 'i is the column and j the row, from the top left of the world'],
        ]);
    }
}
