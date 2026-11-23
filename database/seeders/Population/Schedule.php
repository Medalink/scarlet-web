<?php

namespace Database\Seeders\Population;

/**
 * When one character plays and how far it gets. A leveling window of `firstSeenDays` days takes the character from
 * level 1 to its level, on the share of the xp curve its hours cover; the days after the window go on at that level.
 * Weekends and evenings are busier. Sessions are fixed by the character's name and first evening alone, so a later
 * run adds the days that have passed and never changes a session that is stored.
 */
final class Schedule
{
    private const int DAY = 86400;

    /** @var list<array{start: int, seconds: int, startLevel: int, endLevel: int, last: bool, final: int, played: int}> */
    public array $sessions = [];

    /** The epoch second of the first session of a character that has none yet. */
    public static function firstStart(CharacterPlan $plan, int $now): int
    {
        $hour = Rng::for('first:'.$plan->name)->interval(17, 22);

        return intdiv($now, self::DAY) * self::DAY - $plan->firstSeenDays * self::DAY + $hour * 3600;
    }

    public static function build(CharacterPlan $plan, int $anchor, int $now): self
    {
        $rng = Rng::for('schedule:'.$plan->name);
        $rate = match ($plan->persona) {
            Persona::Hardcore => $rng->float(0.8, 1.0),
            Persona::Leveler => $rng->float(0.55, 0.8),
            default => $rng->float(0.35, 0.6),
        };
        $firstDay = intdiv($anchor, self::DAY) * self::DAY;
        $today = intdiv($now, self::DAY) * self::DAY;
        $ends = $plan->persona === Persona::Hardcore && Rng::for('hardcore-death:'.$plan->name)->chance(60);
        $starts = [$anchor];
        $window = 1;

        for ($day = 1; $firstDay + $day * self::DAY < $today; $day++) {
            $cruising = $day >= $plan->firstSeenDays;
            $midnight = $firstDay + $day * self::DAY;
            $weekend = in_array(gmdate('N', $midnight), ['6', '7'], true);
            $percent = (int) min(95, round($rate * 100 * ($weekend ? 1.5 : 0.85) * ($cruising ? 0.6 : 1)));
            $plays = $rng->chance($percent);
            $second = $weekend && $rng->chance(35);
            $evening = $rng->chance(70);
            $hour = $evening ? $rng->interval(17, 22) : $rng->interval(9, 15);
            $minute = $rng->interval(0, 3) * 15;

            if (! $plays || ($cruising && $ends)) {
                continue;
            }
            $starts[] = $midnight + $hour * 3600 + $minute * 60;
            $window += $cruising ? 0 : 1;

            if ($second) {
                $starts[] = $midnight + ($evening ? 10 : 20) * 3600 + $minute * 60;
                $window += $cruising ? 0 : 1;
            }
        }
        sort($starts);

        return self::levelled($plan, array_values(array_unique($starts)), $window);
    }

    /**
     * @param  list<int>  $starts  every session in time order; the first `$window` are the leveling window's
     */
    private static function levelled(CharacterPlan $plan, array $starts, int $window): self
    {
        $schedule = new self;
        $rng = Rng::for('hours:'.$plan->name);
        $hours = array_map(fn (): float => $rng->float(...$plan->persona->sessionHours()), $starts);
        $work = array_sum(array_slice($hours, 0, $window));
        $totalNeed = self::needBefore($plan->level);
        $level = 1;
        $done = 0.0;
        $played = 0;

        foreach ($starts as $at => $start) {
            $inWindow = $at < $window;
            $startLevel = $level;
            $last = $at === $window - 1;
            $done += $inWindow ? $hours[$at] : 0;

            while ($inWindow && $level < $plan->level && ($last || self::needBefore($level + 1) <= $done / $work * $totalNeed)) {
                $level++;
            }
            $seconds = (int) ($hours[$at] * 3600);
            $schedule->sessions[] = [
                'start' => $start,
                'seconds' => $seconds,
                'startLevel' => $startLevel,
                'endLevel' => $level,
                'last' => $last,
                'final' => $window - 1,
                'played' => $played,
            ];
            $played += $seconds;
        }

        return $schedule;
    }

    /** The xp the curve asks for to reach a level from level one. */
    private static function needBefore(int $level): int
    {
        $sum = 0;

        for ($at = 1; $at < $level; $at++) {
            $sum += CharacterPlan::need($at);
        }

        return $sum;
    }
}
