<?php

namespace App\Http\Controllers\Web;

use App\Actions\Account\MostWorkedProfession;
use App\Actions\Account\OwnCharacterIds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ScopeRequest;
use App\Support\Server;
use Illuminate\Http\RedirectResponse;

/** GET /account/professions: the signed-in account's page of the profession its characters work most, with the ruleset and server asked for. */
class AccountProfessionsController extends Controller
{
    public function __invoke(ScopeRequest $request, OwnCharacterIds $ids, MostWorkedProfession $most): RedirectResponse
    {
        $server = $request->serverFilter();
        $query = array_filter(['ruleset' => $request->rulesetSlug(), 'server' => $server === Server::All ? null : $server->value]);

        return redirect('/account/professions/'.$most->handle($ids->handle($request->account()))->slug().($query === [] ? '' : '?'.http_build_query($query)));
    }
}
