<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\Search;
use App\Actions\Public\SearchPages;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\SearchRequest;
use App\View\Pages\SearchSuggest;
use Illuminate\Http\JsonResponse;

/** The lines of the instant results panel under a search field. Public. */
class SearchSuggestController extends Controller
{
    public function __invoke(SearchRequest $request, Search $search, SearchPages $pages): JsonResponse
    {
        $term = $request->term();
        $answer = $term === '' ? [] : $search->handle($term, null, 0, SearchSuggest::PER_KIND, withCounts: false);
        $found = $term === '' ? [] : $pages->handle($term);

        return response()->json(SearchSuggest::data($answer, $found, $term))->header('X-Robots-Tag', 'noindex');
    }
}
