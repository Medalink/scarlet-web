<?php

namespace App\Http\Controllers\Web;

use App\Actions\Feats\ShowFeatPage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\FeatsRequest;
use App\View\Pages\FeatDetailPage;
use Illuminate\Contracts\View\View;

/** One feat: its ladder, the characters with the most of it and the ones who reached a tier of it last. */
class FeatController extends Controller
{
    public function __invoke(FeatsRequest $request, ShowFeatPage $page, string $key): View
    {
        $answer = $page->handle($key, $request->viewerAccountId(), $request->as());
        abort_if($answer === null, 404);

        return view('pages.feat', ['d' => FeatDetailPage::data($answer)]);
    }
}
