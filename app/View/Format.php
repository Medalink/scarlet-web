<?php

namespace App\View;

use Illuminate\Support\HtmlString;

/**
 * Every number, duration and amount of money the pages show is formatted here, so a figure reads the same
 * on every page and in every chart. The formats are the comps': `1,284`, `6d 4h`, `61h 20m`, `31g40s`.
 * Pure functions: no state, no I/O.
 */
final class Format
{
    /** The marker for a bucket that too few characters contributed to. */
    public const TOO_FEW = 'Too few characters';

    /** 1284 → "1,284"; with decimals, 23.8 → "23.8". */
    public static function number(int|float $value, int $decimals = 0): string
    {
        return number_format($value, $decimals);
    }

    /** 1 → "1 death", 22 → "22 deaths"; an irregular plural is given. */
    public static function count(int|float|string $value, string $singular, ?string $plural = null): string
    {
        $number = (float) $value;

        return self::number($number).' '.($number === 1.0 ? $singular : ($plural ?? $singular.'s'));
    }

    /** A share of 1 as a percentage: 0.412 → "41.2%" with one decimal. */
    public static function percent(int|float $share, int $decimals = 0): string
    {
        return number_format($share * 100, $decimals).'%';
    }

    /** Seconds as the comps write a played time: "42m", "1h 14m", "9h", "6d 4h". */
    public static function duration(int|float $seconds): string
    {
        $minutes = (int) round($seconds / 60);
        if ($minutes < 60) {
            return $minutes.'m';
        }
        if ($minutes < 1440) {
            $rest = $minutes % 60;

            return intdiv($minutes, 60).'h'.($rest === 0 ? '' : ' '.str_pad((string) $rest, 2, '0', STR_PAD_LEFT).'m');
        }

        return intdiv($minutes, 1440).'d '.intdiv($minutes % 1440, 60).'h';
    }

    /** Seconds as hours and minutes, with no days: "61h 20m", "96h"; without minutes, rounded to the hour: "61h". */
    public static function hours(int|float $seconds, bool $minutes = true): string
    {
        if (! $minutes) {
            return self::number(round($seconds / 3600)).'h';
        }

        $total = (int) round($seconds / 60);
        $rest = $total % 60;

        return intdiv($total, 60).'h'.($rest === 0 ? '' : ' '.str_pad((string) $rest, 2, '0', STR_PAD_LEFT).'m');
    }

    /** Seconds down to the minute, for boards that rank by time: "3d 19h 02m". */
    public static function exact(int|float $seconds): string
    {
        $minutes = (int) round($seconds / 60);

        return intdiv($minutes, 1440).'d '
            .str_pad((string) intdiv($minutes % 1440, 60), 2, '0', STR_PAD_LEFT).'h '
            .str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).'m';
    }

    /**
     * Copper as gold, silver and copper with the coin letters, in a `.money` span.
     * 314000 → 31g40s, 2140 → 21s40c, 1800 → 18s. Signed amounts carry + or −.
     */
    public static function money(int|float $copper, bool $signed = false): HtmlString
    {
        $coin = fn (string $letter, string $amount): string => $amount.'<i class="'.$letter.'">'.$letter.'</i>';
        $value = abs((int) round($copper));
        $gold = intdiv($value, 10000);
        $silver = intdiv($value % 10000, 100);
        $bronze = $value % 100;

        $parts = [];
        if ($gold > 0) {
            $parts[] = $coin('g', self::number($gold));
            $parts[] = $coin('s', str_pad((string) $silver, 2, '0', STR_PAD_LEFT));
        }
        if ($gold === 0 && $silver > 0) {
            $parts[] = $coin('s', (string) $silver);
        }
        if ($gold === 0 && ($bronze > 0 || $silver === 0)) {
            $parts[] = $coin('c', $silver > 0 ? str_pad((string) $bronze, 2, '0', STR_PAD_LEFT) : (string) $bronze);
        }

        return new HtmlString('<span class="money">'.self::sign($copper, $signed).implode('', $parts).'</span>');
    }

    /** Copper as plain text, "+9g 52s": a space between the coins. */
    public static function moneyText(int|float $copper, bool $signed = false): string
    {
        $plain = preg_replace('/<[^>]+>/', '', (string) self::money($copper, $signed)) ?? '';

        return preg_replace('/([gs])(?=\d)/', '$1 ', $plain) ?? $plain;
    }

    /** The races whose client file name differs from the name a player knows. */
    private const array RACES = ['Scourge' => 'Undead', 'Undead' => 'Undead', 'NightElf' => 'Night Elf', 'BloodElf' => 'Blood Elf'];

    /** A class file name as a player writes it: WARRIOR → "Warrior", DEATHKNIGHT → "Death Knight". */
    public static function className(?string $file): string
    {
        if ($file === null || $file === '') {
            return '';
        }

        return match (strtoupper($file)) {
            'DEATHKNIGHT' => 'Death Knight',
            'DEMONHUNTER' => 'Demon Hunter',
            default => ucfirst(strtolower($file)),
        };
    }

    /** A race file name as a player writes it: Scourge → "Undead", NightElf → "Night Elf". A name that is already spaced stays. */
    public static function raceName(?string $file): string
    {
        if ($file === null || $file === '') {
            return '';
        }

        return self::RACES[$file] ?? trim((string) preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $file));
    }

    /** "Level 12 Orc Warrior" from the parts that are known. */
    public static function character(?int $level, ?string $race, ?string $class): string
    {
        return trim(implode(' ', array_filter([$level === null ? '' : 'Level '.$level, self::raceName($race), self::className($class)])));
    }

    /** A creature's level as the game shows it: -1 is a boss, whose level the client hides. */
    public static function creatureLevel(?int $low, ?int $high): string
    {
        if ($low === null) {
            return '';
        }
        if ($low < 0 || ($high !== null && $high < 0)) {
            return 'Boss';
        }

        return $high === null || $low === $high ? 'Level '.$low : 'Level '.$low.' to '.$high;
    }

    /** Level ranges and counts that read "Levels 18 to 27". */
    public static function range(int $from, int $to): string
    {
        return $from === $to ? 'Level '.$from : 'Levels '.$from.' to '.$to;
    }

    /**
     * A readout line as the charts take it: a bold lead and the rest of the sentence.
     *
     * @return array{0: string, 1: string}
     */
    public static function line(string $lead, string $rest = ''): array
    {
        return [$lead, $rest];
    }

    private static function sign(int|float $copper, bool $signed): string
    {
        if (! $signed) {
            return '';
        }

        return $copper < 0 ? '−' : '+';
    }
}
