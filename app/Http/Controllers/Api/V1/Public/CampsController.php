<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Public\CampsDashboard;
use App\Http\Requests\Api\PublicRequest;
use Illuminate\Http\JsonResponse;

/** How characters use campfires. */
class CampsController extends PublicController
{
    public function __invoke(PublicRequest $request, CampsDashboard $camps): JsonResponse
    {
        $answer = $camps->handle($request->rulesetSlug(), $request->serverFilter());
        if ($answer === null) {
            $this->missing('The ruleset');
        }
        $scope = $answer['scope'];
        unset($answer['scope']);

        return $this->respond($answer, [...$scope->describe(), 'min_characters' => (int) config('scarlet.min_characters')]);
    }
}
