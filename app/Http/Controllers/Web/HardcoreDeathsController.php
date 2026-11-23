<?php

namespace App\Http\Controllers\Web;

use App\Actions\Public\ListRulesets;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/** /hardcore-deaths moved to the Deaths section of the hardcore ruleset's stats, or to /stats while there is none. */
class HardcoreDeathsController extends Controller
{
    public function __invoke(ListRulesets $rulesets): RedirectResponse
    {
        $hardcore = array_find($rulesets->handle(), fn (array $ruleset): bool => $ruleset['hardcore']);

        return redirect()->to($hardcore === null ? '/stats' : '/stats/'.$hardcore['slug'].'#deaths', 301);
    }
}
