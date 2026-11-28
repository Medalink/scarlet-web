<?php

namespace App\View;

use App\Support\PlayerNameShape;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Every number, duration and amount of money the pages show is formatted here, so a figure reads the same
 * on every page and in every chart: `1,284`, `40s`, `57m`, `21h 48m`, `3d 2h`, `31g 40s`.
 * Pure functions: no state, no I/O.
 */
final class Format
{
    /** The marker for a bucket that too few characters contributed to. */
    public const TOO_FEW = 'Too few characters';

    public const int MILE = 1760;

    public const int HALF_MILE = 880;

    /** 1284 → "1,284"; with decimals, 23.8 → "23.8". */
    /** 1 → "1st", 2,140 → "2,140th". */
    public static function ordinal(int $n): string
    {
        $suffix = in_array($n % 100, [11, 12, 13], true) ? 'th' : ([1 => 'st', 2 => 'nd', 3 => 'rd'][$n % 10] ?? 'th');

        return self::number($n).$suffix;
    }

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

    /**
     * Yards under half a mile (880) stay yards, anything longer is miles: 300 → 300 yd, 16,400 → 9.3 mi.
     *
     * @return array{value: string, unit: string}
     */
    public static function distance(int|float $yards, int $decimals = 1): array
    {
        return $yards < self::HALF_MILE
            ? ['value' => self::number($yards), 'unit' => 'yd']
            : ['value' => self::number($yards / self::MILE, $decimals), 'unit' => 'mi'];
    }

    public static function distanceText(int|float $yards, int $decimals = 1): string
    {
        $distance = self::distance($yards, $decimals);

        return $distance['value'].' '.$distance['unit'];
    }

    /** A share of 1 as a percentage: 0.412 → "41.2%" with one decimal. */
    public static function percent(int|float $share, int $decimals = 0): string
    {
        return number_format($share * 100, $decimals).'%';
    }

    /** Seconds as one played time: "40s", "57m", "21h 48m", "3d 2h"; a zero unit is left out ("1h", "3d"). */
    public static function duration(int|float $seconds): string
    {
        $whole = (int) round(abs($seconds));
        if ($whole < 60) {
            return $whole.'s';
        }

        $minutes = (int) round($whole / 60);
        if ($minutes < 60) {
            return $minutes.'m';
        }
        if ($minutes < 1440) {
            return self::units(intdiv($minutes, 60), 'h', $minutes % 60, 'm');
        }

        return self::units(intdiv($minutes, 1440), 'd', intdiv($minutes % 1440, 60), 'h');
    }

    /** Seconds as a duration; without minutes the time is rounded to the hour first: "21h", "3d 2h", "4d". */
    public static function hours(int|float $seconds, bool $minutes = true): string
    {
        if ($minutes) {
            return self::duration($seconds);
        }

        $hours = (int) round(abs($seconds) / 3600);

        return $hours < 24 ? $hours.'h' : self::units(intdiv($hours, 24), 'd', $hours % 24, 'h');
    }

    /** A time that boards rank by; it reads like every other duration. */
    public static function exact(int|float $seconds): string
    {
        return self::duration($seconds);
    }

    /**
     * Copper as gold, silver and copper with the coin letters, in a `.money` span; a zero unit after the first is left out.
     * 314000 → 31g 40s, 50000 → 5g, 2140 → 21s 40c, 1800 → 18s, 129 → 1s 29c, 0 → 0c. Signed amounts carry + or −.
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
        }
        if ($silver > 0) {
            $parts[] = $coin('s', (string) $silver);
        }
        if ($gold === 0 && $bronze > 0) {
            $parts[] = $coin('c', (string) $bronze);
        }
        if ($parts === []) {
            $parts[] = $coin('c', '0');
        }

        return new HtmlString('<span class="money">'.self::sign($copper, $signed).implode('', $parts).'</span>');
    }

    /** Copper as plain text, "+9g 52s": a space between the coins. */
    public static function moneyText(int|float $copper, bool $signed = false): string
    {
        $plain = preg_replace('/<[^>]+>/', '', (string) self::money($copper, $signed)) ?? '';

        return preg_replace('/([gs])(?=\d)/', '$1 ', $plain) ?? $plain;
    }

    /** A byte count as "812 B", "14.2 KB", "3.4 MB" (1,024 to the step). */
    public static function bytes(int|float $bytes): string
    {
        if ($bytes < 1024) {
            return self::number($bytes).' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes;
        $unit = -1;
        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return number_format($value, $value < 10 ? 1 : 0).' '.$units[$unit];
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

    /** A character's first name and surname as the pages show them: "Name-Surname" → "Name Surname". Links and the API keep the stored name. */
    public static function characterName(string $name): string
    {
        return Str::replaceFirst('-', ' ', $name);
    }

    /** A killer as the pages show it: a player's "First-Last" as a character name, a creature or anything else as it is. */
    public static function killerName(string $killer): string
    {
        return preg_match(PlayerNameShape::NAME, $killer) === 1 ? self::characterName($killer) : $killer;
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

    private static function units(int $major, string $majorUnit, int $minor, string $minorUnit): string
    {
        return $major.$majorUnit.($minor === 0 ? '' : ' '.$minor.$minorUnit);
    }

    private static function sign(int|float $copper, bool $signed): string
    {
        if (! $signed) {
            return '';
        }

        return $copper < 0 ? '−' : '+';
    }
}
