<?php

namespace App\Support\ClassPage\Common;

/** The longest run of levels that one state held for at least half of, written as the plain line under a stack chart. */
final class Leader
{
    public const float SHARE = 0.5;

    public const int LEVELS = 2;

    /**
     * @param  array<int, string|null>  $leading  the state that held at least half of each level, null for none
     * @param  string  $template  `%s` is the state, then the first and the last level of the run
     */
    public static function note(array $leading, string $template): ?string
    {
        $best = $run = null;
        foreach ($leading + [PHP_INT_MAX => null] as $level => $leader) {
            if ($run !== null && $leader === $run[0] && $level === $run[2] + 1) {
                $run[2] = $level;

                continue;
            }
            if ($run !== null && $run[2] - $run[1] + 1 >= self::LEVELS && ($best === null || $run[2] - $run[1] > $best[2] - $best[1])) {
                $best = $run;
            }
            $run = $leader === null ? null : [$leader, $level, $level];
        }

        return $best === null ? null : sprintf($template, $best[0], $best[1], $best[2]);
    }

    /**
     * @param  list<string>  $names  one for each share
     * @param  list<int|float>  $shares  milliseconds, in the order of the names
     */
    public static function of(array $names, array $shares, int|float $whole): ?string
    {
        foreach ($names as $at => $name) {
            if ($whole > 0 && $shares[$at] / $whole >= self::SHARE) {
                return $name;
            }
        }

        return null;
    }
}
