<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\Search;
use App\Actions\Public\SearchPages;
use App\Actions\Public\SearchStart;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\SearchRequest;
use App\View\Pages\SearchPage;
use Illuminate\Contracts\View\View;

/** Search over public characters, creatures, items, zones, quests and the site's own pages. Public. */
class SearchController extends Controller
{
    public function __invoke(SearchRequest $request, Search $search, SearchPages $pages, SearchStart $start): View
    {
        $term = $request->term();
        $kind = $request->kind();
        $data = null;
        if ($term !== '') {
            $answer = $search->handle($term, $kind, $request->offset(), $kind === null ? SearchPage::PER_KIND : SearchPage::PAGE);
            $data = SearchPage::data($answer, $term, $kind, $request->offset(), $kind === null ? $pages->handle($term) : []);
        }

        return view('pages.search', [
            'term' => $request->shortTerm(),
            'd' => $data,
            'start' => $data === null || $data['empty'] ? SearchPage::start($start->handle()) : [],
        ]);
    }
}
