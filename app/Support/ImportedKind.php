<?php

namespace App\Support;

/** The kinds of an `imported_records` row (docs/DESIGN.md, Imported history). The value is the decoder's `kind`. */
enum ImportedKind: string
{
    case Gold = 'gold';
    case Sale = 'sale';
    case Buy = 'buy';
    case Other = 'other';
}
