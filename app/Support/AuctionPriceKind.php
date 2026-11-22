<?php

namespace App\Support;

/** The `kind` of an `auction_prices` row, which is the first field of the `AQ` line. */
enum AuctionPriceKind: string
{
    case Commodity = 'c';
    case Item = 'i';
    case Browse = 'b';

    /** @return list<string> the kinds of a search, which show the lowest price on sale */
    public static function searches(): array
    {
        return [self::Commodity->value, self::Item->value];
    }
}
