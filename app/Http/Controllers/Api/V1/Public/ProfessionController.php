<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Public\CraftingDashboard;
use App\Actions\Public\GatheringDashboard;
use App\Http\Requests\Api\PublicRequest;
use App\Support\Profession;
use Illuminate\Http\JsonResponse;

/** One profession's page of figures: the four gatherings and the eight crafts. */
class ProfessionController extends PublicController
{
    public function __invoke(PublicRequest $request, GatheringDashboard $gathering, CraftingDashboard $crafting, string $slug): JsonResponse
    {
        $profession = Profession::fromSlug($slug);
        if ($profession === null) {
            $this->missing('The profession');
        }
        $kind = $profession->gatherKind();
        $answer = $kind === null
            ? $crafting->handle($profession, $request->rulesetSlug(), $request->serverFilter())
            : $gathering->handle($kind, $request->rulesetSlug(), $request->serverFilter());
        if ($answer === null) {
            $this->missing('The ruleset');
        }
        $scope = $answer['scope'];
        unset($answer['scope']);

        return $this->respond($answer, [...$scope->describe(), 'min_characters' => (int) config('scarlet.min_characters')]);
    }
}
