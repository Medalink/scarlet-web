<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\RulesetComparison;
use App\Actions\Rollups\RulesetSummaries;
use App\Http\Controllers\Controller;
use App\View\Pages\RulesetsPage;
use Illuminate\Contracts\View\View;

/** The rulesets side by side. Public. */
class RulesetsController extends Controller
{
    public function __invoke(RulesetComparison $comparison): View
    {
        return view('pages.rulesets', ['d' => RulesetsPage::data($comparison->handle()), 'target' => RulesetSummaries::TARGET_LEVEL]);
    }
}
