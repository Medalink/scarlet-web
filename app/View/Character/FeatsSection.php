<?php

namespace App\View\Character;

use App\Models\Character;
use App\View\Pages\FeatsPage;
use App\View\Sc\Seal;

/** The `#feats` section of /c/{name}: pinned feats at 104, `Closest to the next tier` for the owner, and the wall by collection. */
final class FeatsSection
{
    private const int PINNED = 3;

    /**
     * @param  array{character: array<string, mixed>, feats: list<array<string, mixed>>, closest: list<array<string, mixed>>}  $hub  BuildCharacterFeats
     * @return array<string, mixed>|null
     */
    public static function data(array $hub, Character $character, bool $owner): ?array
    {
        $feats = $hub['feats'];
        if ($feats === []) {
            return null;
        }
        $summary = $hub['character'] + ['max' => FeatsPage::scoreMax()];

        return [
            'summary' => $summary,
            'hidden' => $character->show_economy ? null : 'gold feats hidden by the owner',
            'pinned' => array_map(self::pinned(...), self::pinnedFeats($feats, $character)),
            'closest' => $owner ? array_map(FeatsPage::closestCard(...), $hub['closest']) : [],
            'wall' => self::wall($feats),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $feats
     * @return list<array<string, mixed>>
     */
    private static function pinnedFeats(array $feats, Character $character): array
    {
        $by = array_column($feats, null, 'key');
        $pinned = [];
        foreach ($character->pinned_feats ?? [] as $key) {
            if (isset($by[$key]) && $by[$key]['tier'] > 0) {
                $pinned[] = $by[$key];
            }
        }
        if ($pinned === []) {
            $earned = array_values(array_filter($feats, fn (array $feat): bool => $feat['tier'] > 0));
            usort($earned, fn (array $a, array $b): int => [$b['tier'], $a['share'] ?? 0, $a['key']] <=> [$a['tier'], $b['share'] ?? 0, $b['key']]);
            $pinned = array_slice($earned, 0, self::PINNED);
        }

        return array_slice($pinned, 0, self::PINNED);
    }

    /**
     * @param  array<string, mixed>  $feat
     * @return array<string, mixed>
     */
    private static function pinned(array $feat): array
    {
        return [
            'key' => $feat['key'], 'name' => $feat['name'], 'glyph' => $feat['glyph'], 'family' => $feat['family'], 'tier' => $feat['tier'], 'progress' => $feat['progress'], 'spell' => $feat['spell'],
            'tierName' => Seal::TIER_NAMES[$feat['tier']], 'counted' => $feat['counted'], 'href' => '/feats/'.$feat['key'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $feats
     * @return list<array{name: string, family: string, feats: list<array<string, mixed>>}>
     */
    private static function wall(array $feats): array
    {
        $groups = [];
        foreach ($feats as $feat) {
            $groups[(string) $feat['collection']]['family'] = (string) $feat['family'];
            $groups[(string) $feat['collection']]['feats'][] = [
                'name' => $feat['name'], 'glyph' => $feat['glyph'], 'family' => $feat['family'], 'tier' => $feat['tier'], 'progress' => $feat['progress'], 'spell' => $feat['spell'], 'href' => '/feats/'.$feat['key'],
            ];
        }
        $wall = [];
        foreach ($groups as $name => $group) {
            $wall[] = ['name' => $name, 'family' => $group['family'], 'feats' => $group['feats']];
        }

        return $wall;
    }
}
