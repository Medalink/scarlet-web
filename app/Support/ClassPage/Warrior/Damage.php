<?php

namespace App\Support\ClassPage\Warrior;

use App\Support\ClassPage\Hue;
use App\Support\ClassPage\Mirror;
use App\Support\ClassPage\MirrorPoint;
use App\Support\ClassPage\Module;
use App\Support\ClassPage\Series;
use App\Support\ClassPage\Span;
use App\Support\ClassPage\Unit;
use App\Support\ClassRows\ClassRows;
use App\Support\ClassRows\Meter;

/** Damage dealt and damage taken in a fight, by level, from the client's damage meter. */
final readonly class Damage
{
    public function __construct(private ClassRows $rows) {}

    /** The damage taken in a fight on average, 0 when no fight was reported. */
    public function takenPerFight(): int|float
    {
        $fights = $this->rows->meters()->fights(Meter::Taken)['fights'];

        return $fights === 0 ? 0 : $this->rows->meters()->total(Meter::Taken) / $fights;
    }

    public function module(): ?Module
    {
        $meters = $this->rows->meters();
        $dealt = $meters->byLevel(Meter::Damage);
        $taken = $meters->byLevel(Meter::Taken);
        $levels = $this->rows->levels();
        if ($levels === [] || ($dealt === [] && $taken === [])) {
            return null;
        }
        $perFight = function (Meter $meter, array $totals, int $level) use ($meters): int|float {
            $fights = $meters->fights($meter, $level)['fights'];

            return $fights === 0 ? 0 : ($totals[$level] ?? 0) / $fights;
        };
        $points = [];
        $worst = null;
        foreach ($levels as $level) {
            $up = $perFight(Meter::Damage, $dealt, $level);
            $down = $perFight(Meter::Taken, $taken, $level);
            $points[] = new MirrorPoint($level, $up, $down);
            if ($up > 0 && $down > 0 && ($worst === null || $down / $up > $worst[1])) {
                $worst = [$level, $down / $up];
            }
        }
        $first = $levels[0];
        $last = $levels[count($levels) - 1];

        return new Module('Damage dealt and damage taken, by level', [new Mirror(
            $points, new Series('Damage dealt a fight', Hue::ofClass()), new Series('Damage taken a fight', new Hue('#c8452f')),
            'Damage dealt a fight, above the line, and damage taken a fight, below, at each level from '.$first.' to '.$last, Unit::Short,
        )], Span::W8, $worst === null ? null : 'Damage taken ran highest against damage dealt at level '.$worst[0].'.');
    }
}
