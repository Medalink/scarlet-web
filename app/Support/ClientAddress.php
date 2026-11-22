<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The address a public limit is counted by: an IPv4 address as it is, an IPv6 address by its /64, so a whole
 * prefix cannot take a limit down address by address (docs/DESIGN.md, Security).
 */
final class ClientAddress
{
    public static function bucket(Request $request): string
    {
        $ip = (string) ($request->ip() ?? '');
        $packed = @inet_pton($ip);
        if ($packed === false || strlen($packed) === 4) {
            return $ip;
        }

        return bin2hex(substr($packed, 0, 8));
    }

    /** A bucket as a person reads it: an IPv4 address as it is, an IPv6 /64 in its usual notation (`2001:db8:1:2::/64`). */
    public static function shown(string $bucket): string
    {
        if (preg_match('/^[0-9a-f]{16}$/', $bucket) !== 1) {
            return $bucket;
        }
        $address = inet_ntop((string) hex2bin($bucket.str_repeat('0', 16)));

        return ($address === false ? $bucket : $address).'/64';
    }
}
