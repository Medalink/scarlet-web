<?php

namespace App\Support\Database;

use RuntimeException;

/**
 * Raw bytes in and out of a `bytea` column. PostgreSQL's PDO driver returns a stream and refuses
 * a string with bytes that are not valid text (SQLSTATE 22021), so bytes are bound as a stream,
 * which Laravel sends as PDO::PARAM_LOB.
 */
final class BinaryValue
{
    public static function read(mixed $value): ?string
    {
        if (! is_resource($value)) {
            return is_string($value) ? $value : null;
        }

        rewind($value);
        $bytes = stream_get_contents($value);

        return $bytes === false ? null : $bytes;
    }

    /** @return resource */
    public static function bind(string $bytes): mixed
    {
        $stream = fopen('php://memory', 'r+b');
        if ($stream === false) {
            throw new RuntimeException('The php://memory stream cannot be opened.');
        }

        fwrite($stream, $bytes);
        rewind($stream);

        return $stream;
    }
}
