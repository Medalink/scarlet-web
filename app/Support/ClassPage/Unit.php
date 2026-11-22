<?php

namespace App\Support\ClassPage;

use App\View\Format;

/** How a number on a class page is written: the page object holds numbers, the components format them with this. */
enum Unit
{
    case Count;
    case Short;
    case Decimal;
    case Percent;
    case PercentTenth;
    case Hours;

    /** 4812 → "4,812"; Short 254000 → "254k"; Decimal 22.7 → "22.7"; Percent 71 → "71%"; Hours 3.2 → "3.2" and 78.4 → "78". */
    public function say(int|float $value): string
    {
        return match ($this) {
            self::Count => Format::number(round($value)),
            self::Short => self::short($value),
            self::Decimal => Format::number($value, 1),
            self::Percent => Format::number(round($value)).'%',
            self::PercentTenth => Format::number($value, 1).'%',
            self::Hours => abs($value) < 10 ? Format::number($value, 1) : Format::number(round($value)),
        };
    }

    /** A rank row's value with the unit's suffix: Hours 78 → "78 h". */
    public function row(int|float $value): string
    {
        return $this === self::Hours ? $this->say($value).' h' : $this->say($value);
    }

    /** 1,200,000 → "1.2M"; 254,000 → "254k"; 840 → "840". */
    public static function short(int|float $value): string
    {
        $abs = abs($value);
        if ($abs >= 1_000_000) {
            return rtrim(rtrim(number_format($value / 1_000_000, 1, '.', ''), '0'), '.').'M';
        }
        if ($abs >= 1_000) {
            return Format::number(round($value / 1_000)).'k';
        }

        return Format::number(round($value));
    }

    /** A value that is already text stays; a number is written in this unit. */
    public function figure(int|float|string $value): string
    {
        return is_string($value) ? $value : $this->say($value);
    }
}
