<?php

namespace App\Http\Controllers\Web;

use App\Actions\Release\ShowAppDownload;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/** The download page of the desktop app: the newest installer, the addon zip alone and the releases page. Public. */
class DownloadController extends Controller
{
    public function __invoke(ShowAppDownload $download): View
    {
        return view('pages.download', ['app' => $download->handle()]);
    }
}
