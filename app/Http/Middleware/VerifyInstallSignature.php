<?php

namespace App\Http\Middleware;

use App\Models\Install;
use App\Support\Exceptions\ApiException;
use App\Support\RequestSignature;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a request through when an install signed it (docs/API.md, Signing): the headers are there, the
 * clock is within the window, the Ed25519 signature over method, path, time and body hash holds, and
 * the signature was not seen before. The install goes into the request as the `install` attribute.
 */
class VerifyInstallSignature
{
    public const string ATTRIBUTE = 'install';

    public const string HEADER_INSTALL = 'Scarlet-Install';

    public const string HEADER_TIME = 'Scarlet-Time';

    public const string HEADER_SIGNATURE = 'Scarlet-Signature';

    public function handle(Request $request, Closure $next): Response
    {
        $limit = (int) config('scarlet.upload.max_body_bytes');
        $declared = $request->headers->get('Content-Length');
        if ($declared !== null && (int) $declared > $limit) {
            throw ApiException::bodyTooLarge($limit);
        }

        $id = (string) $request->header(self::HEADER_INSTALL);
        $time = (string) $request->header(self::HEADER_TIME);
        $signature = (string) $request->header(self::HEADER_SIGNATURE);
        if ($id === '' || $signature === '' || preg_match('/^\d{1,12}$/', $time) !== 1) {
            throw ApiException::missingHeaders();
        }

        $window = (int) config('scarlet.upload.clock_window_seconds');
        if (abs(time() - (int) $time) > $window) {
            throw ApiException::clockSkew($window);
        }

        $install = Str::isUuid($id) ? Install::query()->find($id) : null;
        if ($install === null) {
            throw ApiException::unknownInstall();
        }
        if ($install->isRevoked()) {
            throw ApiException::installRevoked();
        }

        $body = $request->getContent();
        if (strlen($body) > $limit) {
            throw ApiException::bodyTooLarge($limit);
        }

        $message = RequestSignature::message($request->method(), $request->getPathInfo(), (int) $time, $body);
        if (! RequestSignature::valid($install->publicKey(), $signature, $message)) {
            throw ApiException::badSignature();
        }
        if (! RequestSignature::firstSeen($signature)) {
            throw ApiException::replayed();
        }

        $client = self::client($request);
        if ($install->last_seen_at === null || $install->last_seen_at->lt(now()->subMinutes(5)) || ($install->client === null && $client !== null)) {
            $install->forceFill(['last_seen_at' => now(), 'client' => $client])->save();
        }

        $request->attributes->set(self::ATTRIBUTE, $install);

        return $next($request);
    }

    /** The User-Agent as `installs.client` keeps it: printable ASCII, at most 96 characters, null when none was sent. */
    private static function client(Request $request): ?string
    {
        $agent = substr(trim((string) preg_replace('/[^\x20-\x7E]/', '', (string) $request->userAgent())), 0, 96);

        return $agent === '' ? null : $agent;
    }
}
