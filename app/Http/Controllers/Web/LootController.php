<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\LootIndex;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\LootRequest;
use App\View\Pages\LootPage;
use Illuminate\Contracts\View\View;

/** The loot index: what was looted, from what, and how often. Public. */
class LootController extends Controller
{
    public function __invoke(LootRequest $request, LootIndex $index): View
    {
        return view('pages.loot', ['d' => LootPage::from($index->handle($request->filter(), $request->offset()))]);
    }
}
