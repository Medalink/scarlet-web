<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\Leaderboard;
use App\Actions\Public\ListRulesets;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\LeaderboardRequest;
use App\Support\Board;
use App\View\Pages\LeaderboardPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/** The leaderboards of public characters. Public; a signed-in viewer's own characters are highlighted. */
class LeaderboardController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect(LeaderboardPage::link(Board::FastestTo60));
    }

    public function show(LeaderboardRequest $request, Leaderboard $leaderboard, ListRulesets $rulesets, string $board): View
    {
        $kind = Board::tryFrom($board);
        abort_if($kind === null, 404);

        $answer = $leaderboard->handle($kind, $request->rulesetSlug(), $request->serverFilter(), $request->characterClass(), $request->offset(), LeaderboardRequest::PAGE, $request->viewerAccountId());
        abort_if($answer === null, 404);

        return view('pages.leaderboard', ['d' => LeaderboardPage::data($answer, $rulesets->handle(), $request->characterClass(), $request->offset(), LeaderboardRequest::PAGE)]);
    }
}
