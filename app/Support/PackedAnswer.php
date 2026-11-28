<?php

namespace App\Support;

/**
 * An answer body as the origin keeps it in the cache: gzipped, and not kept at all when the packed body is over
 * `MAX_BYTES`, so the whole densest map level (150,000 cells, about 540 KB packed) fits and nothing larger fills the cache.
 */
final class PackedAnswer
{
    public const int MAX_BYTES = 1048576;

    private const string GZIP_MAGIC = "\x1f\x8b";

    /** The packed body, or null when it is over `MAX_BYTES`. */
    public static function pack(string $content): ?string
    {
        $packed = gzencode($content, 1);

        return is_string($packed) && strlen($packed) <= self::MAX_BYTES ? $packed : null;
    }

    /** The body `pack()` made, or null when `$kept` is not one (nothing kept, or an entry of another format). */
    public static function unpack(mixed $kept): ?string
    {
        if (! is_string($kept) || ! str_starts_with($kept, self::GZIP_MAGIC)) {
            return null;
        }
        $body = gzdecode($kept);

        return is_string($body) ? $body : null;
    }
}
