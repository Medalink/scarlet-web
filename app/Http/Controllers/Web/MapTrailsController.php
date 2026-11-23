<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\MapSubject;
use App\Actions\Public\MapTrails;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\MapRequest;
use App\Http\Requests\Web\ScopeRequest;
use Illuminate\Http\JsonResponse;

/**
 * The trails a personal world map draws, read after the page paints: `/map/trails?character=Name-Surname` for one
 * character, visible exactly when its page is, and `/map/trails?scope=mine` for every character of the signed-in account.
 */
class MapTrailsController extends Controller
{
    public function __invoke(MapRequest $request, MapSubject $subject, MapTrails $trails): JsonResponse
    {
        $name = $request->character();
        abort_if(! $request->own() && $name === null, 404);
        $whose = $subject->handle($request->own() ? null : $name, $request->viewer());
        abort_if($whose === null, 404);
        $answer = $trails->handle($whose['ids']);

        $response = response()->json(['data' => ['grid' => $answer['grid'], 'cells' => $answer['cells'], 'flights' => $answer['flights']]])->header('X-Robots-Tag', 'noindex');

        return $request->own() ? $response->header('Cache-Control', ScopeRequest::PRIVATE_CACHE) : $response;
    }
}
