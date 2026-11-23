<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\LootGroups;
use App\Actions\Public\LootIndex;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\LootGroupsRequest;
use App\Http\Requests\Web\LootRequest;
use App\View\Pages\LootGroupsPage;
use App\View\Pages\LootPage;
use Illuminate\Contracts\View\View;

/** The loot index: what was looted, from what, and how often; with `?view=groups`, what loot rules and group size change. Public. */
class LootController extends Controller
{
    public function __invoke(LootRequest $request, LootGroupsRequest $groupsRequest, LootIndex $index, LootGroups $groups): View
    {
        if ($request->groups()) {
            return view('pages.loot-groups', ['d' => LootGroupsPage::from($groups->handle($groupsRequest->source(), $groupsRequest->ruleA(), $groupsRequest->ruleB(), $groupsRequest->marks()))]);
        }

        return view('pages.loot', ['d' => LootPage::from($index->handle($request->filter(), $request->offset()))]);
    }
}
