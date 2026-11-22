<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Public\GatheringDashboard;
use App\Http\Requests\Api\PublicRequest;
use App\Support\Profession;
use Illuminate\Http\JsonResponse;

/** One profession's page of figures: the four gatherings today, each crafting profession when its page ships. */
class ProfessionController extends PublicController
{
    public function __invoke(PublicRequest $request, GatheringDashboard $gathering, string $slug): JsonResponse
    {
        $kind = Profession::fromSlug($slug)?->gatherKind();
        if ($kind === null) {
            $this->missing('The profession');
        }
        $answer = $gathering->handle($kind, $request->rulesetSlug(), $request->serverFilter());
        if ($answer === null) {
            $this->missing('The ruleset');
        }
        $scope = $answer['scope'];
        unset($answer['scope']);

        return $this->respond($answer, [...$scope->describe(), 'min_characters' => (int) config('scarlet.min_characters')]);
    }
}
