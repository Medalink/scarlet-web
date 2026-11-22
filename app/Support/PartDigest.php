<?php

namespace App\Support;

use App\Models\Install;
use App\Models\Part;

/**
 * What /upload/sync compares: the number of a character's parts in some groups that one install claims and the XOR
 * of their ids (docs/DESIGN.md, Upload protocol). The XOR is taken in PostgreSQL as four 64-bit words.
 */
final class PartDigest
{
    /** The XOR of no ids. */
    public const string EMPTY = '0000000000000000000000000000000000000000000000000000000000000000';

    /**
     * @param  list<string>  $groups
     * @return array{count: int, digest: string}
     */
    public static function of(Install $install, int $characterId, array $groups): array
    {
        $row = Part::query()
            ->where('character_id', $characterId)
            ->whereIn('group_key', $groups)
            ->claimedBy($install)
            ->selectRaw('count(*) as n')
            ->selectRaw("coalesce(bit_xor(('x' || encode(substr(id, 1, 8), 'hex'))::bit(64)::bigint), 0) as w1")
            ->selectRaw("coalesce(bit_xor(('x' || encode(substr(id, 9, 8), 'hex'))::bit(64)::bigint), 0) as w2")
            ->selectRaw("coalesce(bit_xor(('x' || encode(substr(id, 17, 8), 'hex'))::bit(64)::bigint), 0) as w3")
            ->selectRaw("coalesce(bit_xor(('x' || encode(substr(id, 25, 8), 'hex'))::bit(64)::bigint), 0) as w4")
            ->toBase()
            ->first();
        if ($row === null) {
            return ['count' => 0, 'digest' => self::EMPTY];
        }

        return [
            'count' => (int) $row->n,
            'digest' => bin2hex(pack('J4', (int) $row->w1, (int) $row->w2, (int) $row->w3, (int) $row->w4)),
        ];
    }
}
