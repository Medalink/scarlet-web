<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * The signature of an upload request (docs/DESIGN.md, Security): Ed25519 over
 * `METHOD \n path \n time \n SHA-256(body) as hex`, sent as base64 in Scarlet-Signature.
 */
final class RequestSignature
{
    /** How long a signature is remembered: a request is valid for the clock window on either side of now. */
    private const int REMEMBER_SECONDS = 600;

    public static function message(string $method, string $path, int $time, string $body): string
    {
        return strtoupper($method)."\n".$path."\n".$time."\n".hash('sha256', $body);
    }

    public static function valid(string $publicKey, string $signatureBase64, string $message): bool
    {
        $signature = base64_decode($signatureBase64, strict: true);
        if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($signature, $message, $publicKey);
    }

    /** True the first time a signature is seen within the window, false for a replay. */
    public static function firstSeen(string $signatureBase64): bool
    {
        return Cache::add(self::replayKey($signatureBase64), 1, self::REMEMBER_SECONDS);
    }

    private static function replayKey(string $signatureBase64): string
    {
        return 'upload-signature:'.hash('sha256', $signatureBase64);
    }
}
