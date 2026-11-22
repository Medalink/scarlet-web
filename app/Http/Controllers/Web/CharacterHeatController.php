<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\CharacterHeat;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CharacterRequest;
use Illuminate\Http\JsonResponse;

/** The cells of a character's hero map, read after the page paints. Visible exactly when the character's page is. */
class CharacterHeatController extends Controller
{
    public function __invoke(CharacterRequest $request, CharacterHeat $heat, string $name): JsonResponse
    {
        $answer = $heat->handle($name, $request->viewerAccountId());
        abort_if($answer === null, 404);

        return response()->json(['data' => $answer])->header('X-Robots-Tag', 'noindex');
    }
}
