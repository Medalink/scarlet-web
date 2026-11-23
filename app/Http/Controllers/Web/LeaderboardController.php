<?php

namespace App\Http\Controllers\Web;

use App\Actions\Account\ChooseScope;
use App\Actions\Account\YourStandings;
use App\Actions\Public\Leaderboard;
use App\Actions\Public\LeaderboardHall;
use App\Actions\Public\ListRulesets;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\LeaderboardRequest;
use App\Support\Board;
use App\Support\Server;
use App\View\Pages\CharacterStandings;
use App\View\Pages\LeaderboardHallPage;
use App\View\Pages\LeaderboardPage;
use Illuminate\Contracts\View\View;

/** The leaderboards of public characters. Public; a signed-in viewer's own characters are highlighted and can be set beside everyone. */
class LeaderboardController extends Controller
{
    public function index(LeaderboardRequest $request, LeaderboardHall $hall, ListRulesets $rulesets, ChooseScope $chooseScope, YourStandings $yours): View
    {
        $answer = $hall->handle($request->rulesetSlug(), $request->serverFilter());
        abort_if($answer === null, 404);

        return view('pages.leaderboards', ['d' => LeaderboardHallPage::data($answer, $rulesets->handle()), 'path' => '/leaderboards', ...$this->scoped($request, $chooseScope, $yours, '/leaderboards', null)]);
    }

    public function show(LeaderboardRequest $request, Leaderboard $leaderboard, ListRulesets $rulesets, ChooseScope $chooseScope, YourStandings $yours, string $board): View
    {
        $kind = Board::tryFrom($board);
        abort_if($kind === null, 404);

        $answer = $leaderboard->handle($kind, $request->rulesetSlug(), $request->serverFilter(), $request->characterClass(), $request->offset(), LeaderboardRequest::PAGE, $request->viewerAccountId());
        abort_if($answer === null, 404);

        return view('pages.leaderboard', ['d' => LeaderboardPage::data($answer, $rulesets->handle(), $request->characterClass(), $request->offset(), LeaderboardRequest::PAGE), 'path' => '/leaderboards/'.$kind->value, ...$this->scoped($request, $chooseScope, $yours, '/leaderboards/'.$kind->value, $kind)]);
    }

    /**
     * The scope switch and the places it asks for: one character's on You vs everyone, all the account's on Only you.
     *
     * @return array{scope: array<string, mixed>, places: list<array{name: string, rows: list<array<string, mixed>>}>}
     */
    private function scoped(LeaderboardRequest $request, ChooseScope $chooseScope, YourStandings $yours, string $path, ?Board $board): array
    {
        $query = array_filter(['ruleset' => $request->rulesetSlug(), 'server' => $request->serverFilter() === Server::All ? null : $request->serverFilter()->value, 'class' => $request->characterClass()]);
        $scope = $chooseScope->handle($request->viewer(), $request->choice(), $request->comparedName(), false, $query);
        $account = $request->viewer();
        $groups = match (true) {
            $account === null || $scope['choice'] === 'everyone' => [],
            $scope['choice'] === 'compare' => $scope['chosen'] === null ? [] : $yours->handle($account, $scope['chosen']['id'], $board),
            default => $yours->handle($account, null, $board),
        };

        return [
            'scope' => $scope,
            'places' => array_map(fn (array $group): array => ['name' => $group['name'], 'rows' => CharacterStandings::rows($group['standings'])], $groups),
        ];
    }
}
