<?php

namespace App\View\Pages;

use App\Support\Threat;
use App\View\Format;

/**
 * The Threat seal and gauge of the creature and zone pages, rated from the figures their strips already show
 * (docs/DESIGN.md "Art and game layer"). A page with too little recorded gets null and no seal.
 */
final class RecordThreat
{
    /**
     * @return array{tier: int, name: string, detail: string, at: float, steps: list<array{name: string, from: string}>}|null
     */
    public static function creature(?int $kills, ?int $deaths): ?array
    {
        if ($kills === null || $deaths === null || $kills < Threat::CREATURE_MIN_KILLS) {
            return null;
        }
        $rate = round($deaths / $kills * 1000, 1);

        return self::seal($rate, Threat::CREATURE, Format::number($rate, 1).' deaths per 1,000 kills, from '.Format::count($kills, 'kill'));
    }

    /**
     * @return array{tier: int, name: string, detail: string, at: float, steps: list<array{name: string, from: string}>}|null
     */
    public static function zone(?float $hours, int $deaths): ?array
    {
        if ($hours === null || $hours < Threat::ZONE_MIN_HOURS) {
            return null;
        }
        $rate = round($deaths / $hours, 2);

        return self::seal($rate, Threat::ZONE, Format::number($rate, 2).' deaths an hour, over '.Format::count(round($hours), 'hour'));
    }

    /**
     * @param  array<int, int|float>  $set
     * @return array{tier: int, name: string, detail: string, at: float, steps: list<array{name: string, from: string}>}
     */
    private static function seal(float $rate, array $set, string $basis): array
    {
        $tier = Threat::tier($rate, $set);
        $next = $set[$tier] ?? null;
        $low = $tier === 0 ? 0 : $set[$tier - 1];
        $share = $next === null ? min(1.0, $low > 0 ? ($rate - $low) / $low : 0.0) : ($rate - $low) / ($next - $low);
        $steps = [['name' => 'Harmless', 'from' => 'under '.$set[0]]];
        foreach ($set as $i => $threshold) {
            $steps[] = ['name' => Threat::name($i + 1), 'from' => 'from '.$threshold];
        }

        return [
            'tier' => $tier,
            'name' => Threat::name($tier),
            'detail' => $basis.'. '.($next === null ? 'The top tier.' : Threat::name($tier + 1).' starts at '.$next.'.'),
            'at' => round(($tier + max(0.0, min(1.0, $share))) / count($steps) * 100, 1),
            'steps' => $steps,
        ];
    }
}
