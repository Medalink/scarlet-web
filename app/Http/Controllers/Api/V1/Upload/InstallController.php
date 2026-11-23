<?php

namespace App\Http\Controllers\Api\V1\Upload;

use App\Actions\Upload\IssueLinkCode;
use App\Actions\Upload\RegisterInstall;
use App\Actions\Upload\ShowInstall;
use App\Http\Controllers\Controller;
use App\Http\Requests\Upload\InstallRequest;
use App\Http\Requests\Upload\RegisterInstallRequest;
use App\Support\RequestSignature;
use Illuminate\Http\JsonResponse;

class InstallController extends Controller
{
    /** POST /api/v1/installs: register a public key (unsigned, rate limited per address). */
    public function store(RegisterInstallRequest $request, RegisterInstall $register): JsonResponse
    {
        $result = $register->handle($request->publicKey(), $request->label(), RequestSignature::site($request));
        $install = $result->install;

        return response()->json([
            'install' => [
                'id' => $install->id,
                'name' => $install->name,
                'created_at' => $install->created_at->utc()->format('Y-m-d\TH:i:s\Z'),
            ],
        ], $result->created ? 201 : 200);
    }

    /** GET /api/v1/installs/me */
    public function show(InstallRequest $request, ShowInstall $show): JsonResponse
    {
        return response()->json($show->handle($request->install()));
    }

    /** POST /api/v1/installs/link-code */
    public function linkCode(InstallRequest $request, IssueLinkCode $issue): JsonResponse
    {
        $result = $issue->handle($request->install());

        return response()->json([
            'code' => $result->code,
            'expires_at' => $result->expiresAt->utc()->format('Y-m-d\TH:i:s\Z'),
            'expires_in' => (int) config('scarlet.upload.link_code_minutes') * 60,
        ], 201);
    }
}
