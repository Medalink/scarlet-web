<?php

namespace App\Http\Controllers\Web;

use App\Actions\Release\ShowAppDownload;
use App\Actions\Release\ShowReleaseNotes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ReleaseNotesRequest;
use App\View\Pages\ReleaseNotesPage;
use Illuminate\Contracts\View\View;

/** What Scarlet is, the download of the desktop app (the newest installer, the addon zip alone, the releases page) and the release notes. Public. */
class DownloadController extends Controller
{
    public function __invoke(ReleaseNotesRequest $request, ShowAppDownload $download, ShowReleaseNotes $releases): View
    {
        return view('pages.pitch', [
            'app' => $download->handle(),
            'notes' => ReleaseNotesPage::data($releases->handle(), $request->version(), $request->kind()),
        ]);
    }
}
