<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The signature of an upload request (docs/API.md, Signing): Ed25519 over
 * `METHOD \n path \n time \n SHA-256(body) as hex`, sent as base64 in Scarlet-Signature. A client
 * binds the host by adding the site line, the host it dialled, and the Scarlet-Site header; the
 * signature of an older client has no site line and no host of its own.
 */
final class RequestSignature
{
    /** How long a signature is remembered: a request is valid for the clock window on either side of now. */
    private const int REMEMBER_SECONDS = 600;

    public static function message(string $method, string $path, int $time, string $body, ?string $site = null): string
    {
        $lines = [strtoupper($method), $path, (string) $time, hash('sha256', $body)];
        if ($site !== null) {
            $lines[] = $site;
        }

        return implode("\n", $lines);
    }

    /** The host of the request as the site line names it: lower case, with the port when it is not the scheme's default. */
    public static function site(Request $request): string
    {
        return strtolower($request->getHttpHost());
    }

    public static function valid(string $publicKey, string $signatureBase64, string $message): bool
    {
        $signature = base64_decode($signatureBase64, strict: true);
        if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($signature, $message, $publicKey);
    }

    /** True the first time a signature is seen on one host within the window, false for a replay. */
    public static function firstSeen(string $signatureBase64, string $site): bool
    {
        return Cache::store('durable')->add(self::replayKey($signatureBase64, $site), 1, self::REMEMBER_SECONDS);
    }

    private static function replayKey(string $signatureBase64, string $site): string
    {
        return 'upload-signature:'.hash('sha256', $site."\n".$signatureBase64);
    }
}
