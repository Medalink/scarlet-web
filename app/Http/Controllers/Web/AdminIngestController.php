<?php

namespace App\Http\Controllers\Web;

use App\Actions\Admin\IngestPipeline;
use App\Http\Controllers\Controller;
use App\View\Pages\Admin\IngestPage;
use Illuminate\Contracts\View\View;

/** GET /admin/ingest, uploads, decodes and queues. AdminOnly lets only an admin account reach it. */
class AdminIngestController extends Controller
{
    public function __invoke(IngestPipeline $numbers): View
    {
        return view('admin.ingest', ['d' => IngestPage::data($numbers->handle())]);
    }
}
