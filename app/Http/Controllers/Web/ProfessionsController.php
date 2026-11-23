<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\ListRulesets;
use App\Actions\Public\ResolveScope;
use App\Actions\Rollups\Professions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ScopeRequest;
use App\View\Pages\Filters;
use App\View\Pages\ProfessionsIndex;
use Illuminate\Contracts\View\View;

/** The professions with a page, side by side. Public. */
class ProfessionsController extends Controller
{
    public function __invoke(ScopeRequest $request, ResolveScope $scopes, Professions $professions, ListRulesets $rulesets): View
    {
        $scope = $scopes->handle($request->rulesetSlug(), $request->serverFilter());
        abort_if($scope === null, 404);

        return view('pages.professions', [
            'd' => ProfessionsIndex::data($professions->handle($scope->ids)),
            'filters' => Filters::onPage($scope, $rulesets->handle(), '/professions'),
        ]);
    }
}
