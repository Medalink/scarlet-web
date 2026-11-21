<?php

namespace App\Support;

/**
 * The opaque `cursor` of a paginated public answer. It carries the position after the last row sent, so a client
 * passes `next_cursor` back unchanged. An unreadable cursor starts at the beginning.
 */
final class Cursor
{
    public const int DEFAULT_LIMIT = 25;

    public const int MAX_LIMIT = 100;

    public static function encode(int $offset): string
    {
        return rtrim(strtr(base64_encode('o'.$offset), '+/', '-_'), '=');
    }

    public static function offset(?string $cursor): int
    {
        if ($cursor === null || $cursor === '') {
            return 0;
        }
        $plain = base64_decode(strtr($cursor, '-_', '+/'), true);

        return is_string($plain) && preg_match('/^o(\d{1,9})$/', $plain, $match) === 1 ? (int) $match[1] : 0;
    }

    /**
     * One page out of rows read with a limit of `$limit + 1`: the rows to send and the cursor of the next page, if any.
     *
     * @param  list<mixed>  $rows
     * @return array{0: list<mixed>, 1: string|null}
     */
    public static function page(array $rows, int $offset, int $limit): array
    {
        if (count($rows) <= $limit) {
            return [$rows, null];
        }

        return [array_slice($rows, 0, $limit), self::encode($offset + $limit)];
    }
}
