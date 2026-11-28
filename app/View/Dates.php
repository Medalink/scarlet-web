<?php

namespace App\View;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/**
 * Every date a person reads is written here, in the forms of the Scarlet date contract: a moment ("3 hours ago",
 * "Nov 22") or a calendar day ("Nov 22", "Sat Nov 22", "Nov 22 8:19 PM"), month first, no comma, the year only when
 * it is not this year, times on a 12-hour clock. Public pages are cached per URL, so the server writes a <time> with the ISO value, the short
 * UTC form as its fallback and the exact UTC value as its title, and resources/js/local-time.js rewrites each one in the
 * reader's time zone, refreshes the relative ones and sets the exact local value as the hover text. `text()` writes the
 * same forms as plain UTC text for the places no element fits: chart axes and readouts, descriptions, attributes.
 * A day key ("2026-11-22", a UTC day bucket) stays that day in every time zone. Pure functions: no state, no I/O.
 */
final class Dates
{
    public static function moment(CarbonInterface|string|int $at): HtmlString
    {
        return self::tag(DateForm::Moment, $at);
    }

    public static function day(CarbonInterface|string|int $at): HtmlString
    {
        return self::tag(DateForm::Day, $at);
    }

    public static function weekday(CarbonInterface|string|int $at): HtmlString
    {
        return self::tag(DateForm::Weekday, $at);
    }

    public static function clock(CarbonInterface|string|int $at, bool $seconds = false): HtmlString
    {
        return self::tag($seconds ? DateForm::ClockSeconds : DateForm::Clock, $at);
    }

    public static function dayTime(CarbonInterface|string|int $at): HtmlString
    {
        return self::tag(DateForm::DayTime, $at);
    }

    /** @param  list<string|Htmlable>  $parts */
    public static function implode(string $glue, array $parts): HtmlString
    {
        return self::join(...array_merge(...array_map(fn (string|Htmlable $part, int $i): array => $i === 0 ? [$part] : [$glue, $part], $parts, array_keys($parts))));
    }

    /** "Nov 22 to Nov 28", "Dec 30 2025 to Jan 2". */
    public static function range(CarbonInterface|string|int $from, CarbonInterface|string|int $to): HtmlString
    {
        return new HtmlString(self::tag(DateForm::Day, $from).' to '.self::tag(DateForm::Day, $to));
    }

    /** Text and dates in one line: strings are escaped, a date or other HtmlString goes in as it is. */
    public static function join(string|Htmlable ...$parts): HtmlString
    {
        return new HtmlString(implode('', array_map(fn (string|Htmlable $part): string => $part instanceof Htmlable ? $part->toHtml() : e($part), $parts)));
    }

    public static function text(CarbonInterface|string|int $at, DateForm $form = DateForm::Day): string
    {
        $date = self::utc($at);
        $year = $date->year === Carbon::now('UTC')->year ? '' : ' Y';

        return match ($form) {
            DateForm::Moment, DateForm::Day => $date->format('M j'.$year),
            DateForm::Weekday => $date->format('D M j'.$year),
            DateForm::Clock => $date->format('g:i A'),
            DateForm::ClockSeconds => $date->format('g:i:s A'),
            DateForm::DayTime => $date->format('M j'.$year.' g:i A'),
        };
    }

    /** An hour of the day on the 12-hour clock: 19 → "7:00 PM". */
    public static function hour(int $hour): string
    {
        return ($hour % 12 === 0 ? 12 : $hour % 12).':00 '.($hour < 12 ? 'AM' : 'PM');
    }

    /** An hour of the week, Monday 00:00 UTC first, in a span the browser rewrites in the reader's zone: "Sat 10:00 PM UTC" without script. */
    public static function weekHour(int $index): HtmlString
    {
        return new HtmlString('<span data-week-hour="'.$index.'">'.['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][intdiv($index, 24)].' '.self::hour($index % 24).' UTC</span>');
    }

    /** An axis mark for an hour of the day: 0 → "12 AM", 15 → "3 PM". */
    public static function hourMark(int $hour): string
    {
        return ($hour % 12 === 0 ? 12 : $hour % 12).' '.($hour < 12 ? 'AM' : 'PM');
    }

    /** A line of text and dates as plain text, for a description or an attribute: the fallback text of each date. */
    public static function plain(string|Htmlable $line): string
    {
        return html_entity_decode(strip_tags($line instanceof Htmlable ? $line->toHtml() : $line), ENT_QUOTES);
    }

    /** The hover text of a date: "Sat Nov 22 2025 8:19:47 PM", or "Sat Nov 22 2025" for a day key. */
    public static function exact(CarbonInterface|string|int $at): string
    {
        return self::utc($at)->format(self::isDayKey($at) ? 'D M j Y' : 'D M j Y g:i:s A');
    }

    private static function tag(DateForm $form, CarbonInterface|string|int $at): HtmlString
    {
        $date = self::utc($at);
        $value = self::isDayKey($at) ? $date->toDateString() : $date->toIso8601String();
        $title = self::exact($at).(self::isDayKey($at) ? '' : ' UTC');

        return new HtmlString('<time datetime="'.e($value).'" data-when="'.$form->value.'" title="'.e($title).'">'.e(self::text($at, $form)).'</time>');
    }

    private static function isDayKey(CarbonInterface|string|int $at): bool
    {
        return is_string($at) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $at) === 1;
    }

    private static function utc(CarbonInterface|string|int $at): Carbon
    {
        if (is_int($at)) {
            return Carbon::createFromTimestampUTC($at);
        }
        if (is_string($at)) {
            return Carbon::parse($at, 'UTC')->utc();
        }

        return Carbon::instance($at)->utc();
    }
}
