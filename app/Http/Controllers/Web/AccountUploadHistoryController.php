<?php

namespace App\Http\Controllers\Web;

use App\Actions\Account\ListUploads;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\UploadHistoryRequest;
use App\Models\Account;
use Illuminate\Contracts\View\View as ViewContract;

/** GET /account/storage/history: every upload of the account, newest first, a page at a time. */
class AccountUploadHistoryController extends Controller
{
    public function __invoke(UploadHistoryRequest $request, ListUploads $uploads): ViewContract
    {
        $account = $request->user();
        abort_unless($account instanceof Account, 403);
        $page = $request->page();

        return view('account.uploads', ['page' => $page, 'history' => $uploads->handle($account, null, $page)]);
    }
}
