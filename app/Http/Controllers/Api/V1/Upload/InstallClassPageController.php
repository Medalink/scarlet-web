<?php

namespace App\Http\Controllers\Api\V1\Upload;

use App\Actions\Public\ClassPage\ShowInstallClassPages;
use App\Http\Controllers\Controller;
use App\Http\Requests\Upload\ClassPageRequest;
use Illuminate\Http\JsonResponse;

class InstallClassPageController extends Controller
{
    /** GET /api/v1/installs/me/class-page */
    public function show(ClassPageRequest $request, ShowInstallClassPages $show): JsonResponse
    {
        return response()->json(['characters' => $show->handle($request->install(), $request->character())]);
    }
}
