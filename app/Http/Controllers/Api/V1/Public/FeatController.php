<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Feats\ShowFeat;
use App\Actions\Feats\ShowFeatCatalog;
use App\Http\Requests\Api\PublicRequest;
use Illuminate\Http\JsonResponse;

/** The feat catalog, and one feat with who holds the most of it. */
class FeatController extends PublicController
{
    public function index(PublicRequest $request, ShowFeatCatalog $catalog): JsonResponse
    {
        return $this->respond($catalog->handle());
    }

    public function show(PublicRequest $request, ShowFeat $feat, string $key): JsonResponse
    {
        $answer = $feat->handle($key);
        if ($answer === null) {
            $this->missing('The feat');
        }

        return $this->respond($answer);
    }
}
