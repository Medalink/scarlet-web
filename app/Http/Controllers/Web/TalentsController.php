<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\ListRulesets;
use App\Actions\Public\ResolveScope;
use App\Actions\Public\TalentSpec;
use App\Actions\Public\TalentSpecs;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ScopeRequest;
use App\Http\Requests\Web\TalentSpecRequest;
use App\View\Pages\Filters;
use App\View\Pages\TalentSpecPage;
use Illuminate\Contracts\View\View;

/** The talent pages: every spec with its characters at /talents, one spec at /talents/{class}/{spec}. Both are public. */
class TalentsController extends Controller
{
    public function index(ScopeRequest $request, ResolveScope $scopes, TalentSpecs $specs, ListRulesets $rulesets): View
    {
        $scope = $scopes->handle($request->rulesetSlug(), $request->serverFilter());
        abort_if($scope === null, 404);

        return view('pages.talents', [
            'd' => TalentSpecPage::index($specs->handle($scope)),
            'filters' => Filters::onPage($scope, $rulesets->handle(), '/talents'),
        ]);
    }

    public function show(TalentSpecRequest $request, ResolveScope $scopes, TalentSpec $spec, ListRulesets $rulesets, string $class, string $name): View
    {
        $scope = $scopes->handle($request->rulesetSlug(), $request->serverFilter());
        abort_if($scope === null, 404);
        $answer = $spec->handle($class, $name, $scope, $request->bracket());
        abort_if($answer === null, 404);

        $path = '/talents/'.$class.'/'.$name;
        $keep = $request->bracket()->value === 'all' ? [] : ['level' => $request->bracket()->value];

        return view('pages.talent-spec', [
            'd' => TalentSpecPage::data($answer, $path, $scope),
            'filters' => Filters::onPage($scope, $rulesets->handle(), $path, $keep),
        ]);
    }
}
