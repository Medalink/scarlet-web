<?php

namespace App\Support;

/** The stored form of a link code: SHA-256 of the code in capitals without the hyphen. */
final class LinkCodeHash
{
    public static function of(string $code): string
    {
        return hash('sha256', strtoupper(str_replace('-', '', $code)), binary: true);
    }
}
