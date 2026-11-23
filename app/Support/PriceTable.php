<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * An AHledger price table (`GET /v1/pricetable/{market}`): a header `AHL1|<market slug>|<scan unix time>|<rows>`, then
 * one row per item, `item:median:minBuyout:quantity:median7d:median30d:range30Low:range30High:soldPerDayTimesTen`, in
 * copper. The units sold a day are empty until a market has enough hours of scans. A table of another version, another
 * market or a row count that does not match its header reads as null, so a change of format stores nothing rather
 * than half a scan.
 */
final readonly class PriceTable
{
    private const string VERSION = 'AHL1';

    private const string ROW = '/^\d+(:\d+){7}:\d*$/';

    /** @param  list<array{item_id: int, median: int, min_buyout: int, quantity: int, median_7d: int, median_30d: int, sold_x10: int|null}>  $rows */
    private function __construct(public Carbon $scannedAt, public array $rows) {}

    public static function parse(string $body, string $slug): ?self
    {
        $lines = preg_split('/\r?\n/', trim($body)) ?: [];
        $header = explode('|', (string) array_shift($lines));
        if (count($header) !== 4 || $header[0] !== self::VERSION || $header[1] !== $slug || ! ctype_digit($header[2]) || ! ctype_digit($header[3])) {
            return null;
        }
        $rows = [];
        foreach ($lines as $line) {
            if (preg_match(self::ROW, $line) !== 1) {
                return null;
            }
            $f = explode(':', $line);
            $rows[] = [
                'item_id' => (int) $f[0],
                'median' => (int) $f[1],
                'min_buyout' => (int) $f[2],
                'quantity' => (int) $f[3],
                'median_7d' => (int) $f[4],
                'median_30d' => (int) $f[5],
                'sold_x10' => $f[8] === '' ? null : (int) $f[8],
            ];
        }
        if (count($rows) !== (int) $header[3]) {
            return null;
        }

        return new self(Carbon::createFromTimestampUTC((int) $header[2]), $rows);
    }
}
