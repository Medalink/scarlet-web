<?php

namespace App\Http\Controllers\Web;

use App\Actions\Admin\ClientVersions;
use App\Http\Controllers\Controller;
use App\View\Pages\Admin\ClientsPage;
use Illuminate\Contracts\View\View;

/** GET /admin/clients, which app builds, systems and addon versions the installs run. AdminOnly lets only an admin account reach it. */
class AdminClientsController extends Controller
{
    public function __invoke(ClientVersions $numbers): View
    {
        return view('admin.clients', ['d' => ClientsPage::data($numbers->handle())]);
    }
}
