<?php

namespace App\Http\Controllers\Api\V1\Upload;

use App\Actions\Feats\MarkInstallAccountFeatsSeen;
use App\Actions\Feats\MarkInstallFeatsSeen;
use App\Actions\Feats\ShowInstallAccountFeats;
use App\Actions\Feats\ShowInstallFeats;
use App\Http\Controllers\Controller;
use App\Http\Requests\Upload\FeatsSeenRequest;
use App\Http\Requests\Upload\InstallRequest;
use App\Support\FeatCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class InstallFeatController extends Controller
{
    /** GET /api/v1/installs/me/feats */
    public function show(InstallRequest $request, ShowInstallFeats $show, ShowInstallAccountFeats $account): JsonResponse
    {
        $install = $request->install();

        return response()->json(['catalog' => FeatCatalog::VERSION, 'characters' => $show->handle($install), 'account' => $account->handle($install)]);
    }

    /** POST /api/v1/installs/me/feats/seen */
    public function seen(FeatsSeenRequest $request, MarkInstallFeatsSeen $mark, MarkInstallAccountFeatsSeen $markAccount): Response
    {
        if ($request->forAccount()) {
            $markAccount->handle($request->install(), $request->shown());

            return response()->noContent();
        }
        $mark->handle($request->install(), $request->character(), $request->shown());

        return response()->noContent();
    }
}
