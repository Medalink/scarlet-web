<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Sessions\ListSessions;
use App\Actions\Sessions\ShowSession;
use App\Http\Requests\Api\PublicRequest;
use Illuminate\Http\JsonResponse;

/** The sessions of a public character whose owner keeps its session pages on. Any other character answers 404, its owner included: a key does not tell the API whose character it is. */
class CharacterSessionsController extends PublicController
{
    public function index(PublicRequest $request, ListSessions $list, string $name): JsonResponse
    {
        $answer = $list->handle($name, $request->afterStart());
        if ($answer === null) {
            $this->missing('The character');
        }

        return $this->respond($answer);
    }

    public function show(PublicRequest $request, ShowSession $show, string $name, int $start): JsonResponse
    {
        $answer = $show->handle($name, $start, null);
        if ($answer === null) {
            $this->missing('The session');
        }

        return $this->respond($answer['recap'], ['economy' => $answer['economy']]);
    }
}
