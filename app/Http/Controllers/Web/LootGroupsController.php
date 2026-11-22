<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\LootGroups;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\LootGroupsRequest;
use App\View\Pages\LootGroupsPage;
use Illuminate\Contracts\View\View;

/** What loot rules and group size change in what a corpse holds, who keeps it and how fast a group kills. Public. */
class LootGroupsController extends Controller
{
    public function __invoke(LootGroupsRequest $request, LootGroups $groups): View
    {
        return view('pages.loot-groups', ['d' => LootGroupsPage::from($groups->handle($request->source(), $request->ruleA(), $request->ruleB()))]);
    }
}
