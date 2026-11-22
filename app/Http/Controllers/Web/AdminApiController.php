<?php

namespace App\Http\Controllers\Web;

use App\Actions\Admin\ApiUsage;
use App\Http\Controllers\Controller;
use App\View\Pages\Admin\ApiPage;
use Illuminate\Contracts\View\View;

/** GET /admin/api, who calls the API: addresses, accounts, installs and characters. AdminOnly lets only an admin account reach it. */
class AdminApiController extends Controller
{
    public function __invoke(ApiUsage $numbers): View
    {
        return view('admin.api', ['d' => ApiPage::data($numbers->handle())]);
    }
}
