<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\Search;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\SearchRequest;
use App\View\Pages\SearchPage;
use Illuminate\Contracts\View\View;

/** Search over public characters, creatures, items, zones and quests. Public. */
class SearchController extends Controller
{
    public function __invoke(SearchRequest $request, Search $search): View
    {
        $term = $request->term();
        $kind = $request->kind();
        $answer = $term === '' ? [] : $search->handle($term, $kind, $request->offset(), $kind === null ? SearchPage::PER_KIND : SearchPage::PAGE);

        return view('pages.search', [
            'term' => $request->shortTerm(),
            'd' => $term === '' ? null : SearchPage::data($answer, $term, $kind, $request->offset()),
        ]);
    }
}
