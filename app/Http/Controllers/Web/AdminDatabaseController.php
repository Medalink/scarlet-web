<?php

namespace App\Http\Controllers\Web;

use App\Actions\Admin\DatabaseHealth;
use App\Http\Controllers\Controller;
use App\View\Pages\Admin\DatabasePage;
use Illuminate\Contracts\View\View;

/** GET /admin/database, sizes, compression, jobs and health of PostgreSQL. AdminOnly lets only an admin account reach it. */
class AdminDatabaseController extends Controller
{
    public function __invoke(DatabaseHealth $numbers): View
    {
        return view('admin.database', ['d' => DatabasePage::data($numbers->handle())]);
    }
}
