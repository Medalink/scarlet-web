<?php

namespace App\Http\Controllers\Web;

use App\Actions\Account\AgeUploads;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\DevelopmentAgeUploadsRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;

/** POST /auth/development/age-uploads: makes the parts of one of the signed-in account's characters older. Exists only where the development sign-in does. */
class AuthDevelopmentAgeUploadsController extends Controller
{
    public function __invoke(DevelopmentAgeUploadsRequest $request, AgeUploads $age): RedirectResponse
    {
        $account = $request->user();
        abort_unless($account instanceof Account, 403);
        $age->handle($account, $request->character(), $request->hours());

        return redirect('/account/storage');
    }
}
