<?php

namespace App\Http\Controllers\Api\V1\Upload;

use App\Actions\Upload\CompareParts;
use App\Actions\Upload\ReceiveManifest;
use App\Actions\Upload\RemoveEverything;
use App\Actions\Upload\RemoveGroup;
use App\Actions\Upload\RestoreGroup;
use App\Actions\Upload\StoreParts;
use App\Actions\Upload\UploadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Upload\InstallRequest;
use App\Http\Requests\Upload\ManifestRequest;
use App\Http\Requests\Upload\PartsRequest;
use App\Http\Requests\Upload\SyncRequest;
use App\Support\PartTool;
use Illuminate\Http\JsonResponse;

/** The signed upload endpoints under /api/v1/upload (docs/API.md). */
class UploadController extends Controller
{
    public function sync(SyncRequest $request, CompareParts $compare): JsonResponse
    {
        return response()->json($compare->handle($request->install(), $request->character(), $request->groups(), $request->count(), $request->digest()));
    }

    public function manifest(ManifestRequest $request, ReceiveManifest $receive, PartTool $tool): JsonResponse
    {
        return response()->json($receive->handle($request->install(), $request->manifest($tool)));
    }

    public function parts(PartsRequest $request, StoreParts $store): JsonResponse
    {
        return response()->json($store->handle($request->install(), $request->batch()));
    }

    public function status(InstallRequest $request, UploadStatus $status): JsonResponse
    {
        return response()->json($status->handle($request->install()));
    }

    public function restoreGroup(InstallRequest $request, RestoreGroup $restore): JsonResponse
    {
        return response()->json($restore->handle($request->install(), $request->group()));
    }

    public function removeGroup(InstallRequest $request, RemoveGroup $remove): JsonResponse
    {
        return response()->json($remove->handle($request->install(), $request->group()));
    }

    public function removeEverything(InstallRequest $request, RemoveEverything $remove): JsonResponse
    {
        return response()->json($remove->handle($request->install()));
    }
}
