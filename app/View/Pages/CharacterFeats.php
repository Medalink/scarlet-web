<?php

namespace App\View\Pages;

use App\Models\Character;
use App\Support\Feat;
use App\View\Format;

/** The Feats section of the character page, from the `feats` of App\Actions\Public\CharacterOverview. */
final class CharacterFeats
{
    private const int TOP_TIER = 4;

    /**
     * @param  list<array{key: string, name: string, value: int|float, tier: int, tier_name: string|null, threshold: int|null, next: int|null}>  $feats
     * @return array{held: int, total: int, tiers: list<array{count: int, name: string}>, since: string|null, items: list<array{tier: int, icon: string, name: string, tier_name: string|null, detail: string, at: float|null}>}|null
     */
    public static function section(array $feats, Character $character): ?array
    {
        if ($feats === []) {
            return null;
        }
        $tiers = [];
        for ($tier = self::TOP_TIER; $tier >= 1; $tier--) {
            $count = count(array_filter($feats, fn (array $feat): bool => $feat['tier'] === $tier));
            if ($count > 0) {
                $tiers[] = ['count' => $count, 'name' => strtolower(Feat::TIERS[$tier - 1])];
            }
        }

        return [
            'held' => count(array_filter($feats, fn (array $feat): bool => $feat['tier'] > 0)),
            'total' => count($feats),
            'tiers' => $tiers,
            'since' => $character->whole_life || $character->first_seen_at === null ? null : $character->first_seen_at->utc()->format('j M Y'),
            'items' => array_map(fn (array $feat): array => [
                'tier' => $feat['tier'], 'icon' => Feat::from($feat['key'])->icon(), 'name' => $feat['name'], 'tier_name' => $feat['tier_name'],
                'detail' => self::detail($feat), 'at' => self::share($feat),
            ], $feats),
        ];
    }

    /** @param  array{key: string, name: string, value: int|float, tier: int, tier_name: string|null, threshold: int|null, next: int|null}  $feat */
    private static function detail(array $feat): string
    {
        $kind = Feat::from($feat['key']);
        $decimals = $kind === Feat::Wayfarer ? 1 : 0;
        $value = $feat['value'];
        $says = $kind->says(Format::number($value, $decimals), $value == 1);
        $next = $feat['next'];
        if ($feat['tier'] === self::TOP_TIER || $next === null) {
            return $says.'. The top tier.';
        }
        if ($feat['tier'] === 0) {
            return Format::number($value, $decimals).' of '.Format::number($next).' '.$kind->noun();
        }

        return $says.', '.Format::number($next - $value, $decimals).' to '.strtolower(Feat::TIERS[$feat['tier']] ?? '').' at '.Format::number($next);
    }

    /** @param  array{key: string, name: string, value: int|float, tier: int, tier_name: string|null, threshold: int|null, next: int|null}  $feat */
    private static function share(array $feat): ?float
    {
        if ($feat['next'] === null) {
            return null;
        }
        $from = $feat['threshold'] ?? 0;

        return max(0.0, min(100.0, round(($feat['value'] - $from) / ($feat['next'] - $from) * 100, 1)));
    }
}
