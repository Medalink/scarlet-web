<?php

namespace App\View\Pages;

use App\Models\Character;
use App\Support\Feat;
use App\View\Format;

/**
 * The Feats section of the character page, from the `feats` of App\Actions\Public\CharacterOverview: the six at the top
 * of the list and the cards of the dialog that lists the rest.
 *
 * @phpstan-type Row array{key: string, name: string, value: int|float, tier: int, tier_name: string|null, threshold: int|null, next: int|null, pinned: bool}
 * @phpstan-type Card array{key: string, pinned: bool, tier: int, icon: string, name: string, tier_name: string|null, detail: string, track: list<int>}
 */
final class CharacterFeatWall
{
    private const int TOP = 6;

    /**
     * @param  list<Row>  $feats
     * @return array{held: int, total: int, tiers: list<array{count: int, name: string}>, since: string|null, limit: int, pinned: list<Card>, top: list<Card>, earned: list<Card>, unearned: list<Card>}|null
     */
    public static function section(array $feats, Character $character): ?array
    {
        if ($feats === []) {
            return null;
        }
        $byKey = array_column(array_filter($feats, fn (array $feat): bool => $feat['pinned']), null, 'key');
        $pinned = [];
        foreach ($character->pinned_feats ?? [] as $key) {
            if (isset($byKey[$key])) {
                $pinned[] = $byKey[$key];
            }
        }
        $unpinned = array_values(array_filter($feats, fn (array $feat): bool => ! $feat['pinned']));
        $earned = array_values(array_filter($feats, fn (array $feat): bool => $feat['tier'] >= 1));
        $tiers = [];
        for ($tier = count(Feat::TIERS); $tier >= 1; $tier--) {
            $count = count(array_filter($earned, fn (array $feat): bool => $feat['tier'] === $tier));
            if ($count > 0) {
                $tiers[] = ['count' => $count, 'name' => strtolower(Feat::TIERS[$tier - 1])];
            }
        }

        return [
            'held' => count($earned),
            'total' => count($feats),
            'tiers' => $tiers,
            'since' => $character->whole_life || $character->first_seen_at === null ? null : $character->first_seen_at->utc()->format('j M Y'),
            'limit' => Character::MAX_PINNED_FEATS,
            'pinned' => array_map(self::card(...), $pinned),
            'top' => array_map(self::card(...), array_slice($unpinned, 0, self::TOP)),
            'earned' => array_map(self::card(...), $earned),
            'unearned' => array_map(self::card(...), array_values(array_filter($feats, fn (array $feat): bool => $feat['tier'] === 0))),
        ];
    }

    /**
     * @param  Row  $feat
     * @return Card
     */
    private static function card(array $feat): array
    {
        return [
            'key' => $feat['key'], 'pinned' => $feat['pinned'], 'tier' => $feat['tier'], 'icon' => Feat::from($feat['key'])->icon(), 'name' => $feat['name'], 'tier_name' => $feat['tier_name'],
            'detail' => self::detail($feat), 'track' => self::track($feat),
        ];
    }

    /** @param  Row  $feat */
    private static function detail(array $feat): string
    {
        $kind = Feat::from($feat['key']);
        $decimals = $kind->decimals();
        $value = $feat['value'];
        $says = $kind->says(Format::number($value, $decimals), $value == 1);
        $next = $feat['next'];
        if ($next === null) {
            return $says.'. The top tier.';
        }
        if ($feat['tier'] === 0) {
            return Format::number($value, $decimals).' of '.Format::number($next).' '.$kind->noun($next === 1);
        }

        return $says.', '.Format::number($next - $value, $decimals).' to '.strtolower(Feat::TIERS[$feat['tier']] ?? '').' at '.Format::number($next);
    }

    /**
     * @param  Row  $feat
     * @return list<int>
     */
    private static function track(array $feat): array
    {
        $track = [];
        foreach (array_keys(Feat::TIERS) as $i) {
            $tier = $i + 1;
            if ($feat['tier'] >= $tier) {
                $track[] = 100;

                continue;
            }
            if ($feat['tier'] === $tier - 1) {
                $track[] = self::share($feat);

                continue;
            }
            $track[] = 0;
        }

        return $track;
    }

    /** @param  Row  $feat */
    private static function share(array $feat): int
    {
        if ($feat['next'] === null) {
            return 100;
        }
        $from = $feat['threshold'] ?? 0;

        return (int) max(0, min(100, floor(($feat['value'] - $from) / ($feat['next'] - $from) * 100)));
    }
}
