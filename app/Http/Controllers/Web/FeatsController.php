<?php

namespace App\Http\Controllers\Web;

use App\Actions\Feats\ShowFeatsHub;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\FeatsRequest;
use App\View\Pages\FeatsPage;
use Illuminate\Contracts\View\View;

/** The feats hub: the catalog with the share at each tier, and for a signed-in owner the wall of one of their characters. */
class FeatsController extends Controller
{
    public function __invoke(FeatsRequest $request, ShowFeatsHub $hub): View
    {
        $answer = $hub->handle($request->viewerAccountId(), $request->scope(), $request->as());

        return view('pages.feats', ['d' => FeatsPage::data($answer, $request->cut())]);
    }
}
