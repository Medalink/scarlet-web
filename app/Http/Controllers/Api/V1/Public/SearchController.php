<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Public\Search;
use App\Http\Requests\Api\PublicRequest;
use App\Support\Cursor;
use App\Support\Exceptions\ApiException;
use App\Support\SearchKind;
use Illuminate\Http\JsonResponse;

/** Search over public characters, creatures, items, zones and quests. */
class SearchController extends PublicController
{
    public function __invoke(PublicRequest $request, Search $search): JsonResponse
    {
        $term = $request->term();
        if ($term === null) {
            throw ApiException::invalidRequest('The q parameter is required.', ['q' => ['Send the text to search for.']]);
        }
        $kind = $request->kind() === null ? null : SearchKind::tryFrom($request->kind());
        if ($request->kind() !== null && $kind === null) {
            throw ApiException::invalidRequest('The kind parameter is not valid.', ['kind' => ['Use characters, creatures, items, zones or quests.']]);
        }

        $offset = $request->offset();
        $limit = $request->limit();
        $answer = $search->handle($term, $kind, $offset, $limit);

        if ($kind === null) {
            return $this->respond(array_map(fn (array $group): array => ['count' => $group['count'], 'hits' => $group['hits']], $answer), ['q' => $term, 'per_kind' => $limit]);
        }
        [$hits, $next] = Cursor::page($answer[$kind->value]['hits'], $offset, $limit);

        return $this->respondPage($hits, $next, ['q' => $term, 'kind' => $kind->value, 'total' => $answer[$kind->value]['count']]);
    }
}
