<?php

namespace App\Support;

/**
 * The possession proof of a link redemption (docs/API.md, "POST /installs/link-code"): an Ed25519
 * signature by the install's own key over `scarlet-link-redeem \n CODE`, sent back as base64 in the
 * confirm form's `redeem`. Knowing the code string is not enough to produce it.
 */
final class LinkRedeem
{
    public const string LINE = 'scarlet-link-redeem';

    public static function message(string $code): string
    {
        return self::LINE."\n".$code;
    }

    public static function valid(string $publicKey, string $token, string $code): bool
    {
        return RequestSignature::valid($publicKey, $token, self::message($code));
    }
}
