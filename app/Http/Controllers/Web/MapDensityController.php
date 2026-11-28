<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\WorldDensity;
use App\Http\Controllers\Api\V1\Public\MapController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PublicRequest;
use Illuminate\Http\JsonResponse;

/**
 * The heat the world map reads, as the page's own address: `/map/density` answers what `/public/map/density` does with
 * no key, cached like a page, and is kept out of search results.
 */
class MapDensityController extends Controller
{
    public function __invoke(PublicRequest $request, WorldDensity $density, MapController $heat): JsonResponse
    {
        return $heat->density($request, $density)->header('X-Robots-Tag', 'noindex');
    }
}
