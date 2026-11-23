<?php

namespace App\View\Pages;

use App\Support\Feat;
use App\Support\FeatText;
use App\View\Format;
use App\View\Sc\Crest;

/** The data of /feats/{key} from the answer of App\Actions\Feats\ShowFeatPage. */
final class FeatDetailPage
{
    /**
     * @param  array{feat: array<string, mixed>, collection: list<array<string, mixed>>, mine: array{character: string, value: int|float, tier: int}|null}  $answer
     * @return array<string, mixed>
     */
    public static function data(array $answer): array
    {
        $feat = $answer['feat'];
        $mine = $answer['mine'];
        $kind = Feat::from($feat['key']);
        $you = $mine === null ? null : FeatsPage::first($mine['character']).', '.FeatText::counted($kind, $mine['value']);

        return [
            'key' => $feat['key'], 'name' => $feat['name'], 'glyph' => $feat['glyph'], 'family' => $feat['family'], 'collection' => $feat['collection'],
            'tier' => $mine['tier'] ?? 0,
            'line' => self::line($feat),
            'ladder' => FeatLadder::rows($kind, $feat['thresholds'], $feat['shares'], $mine['tier'] ?? null, $you),
            'shares' => $feat['shares'] !== null,
            'top' => array_map(fn (array $row): array => [
                'name' => Format::characterName($row['character']), 'href' => '/c/'.rawurlencode($row['character']), 'tier' => $row['tier'],
                'tierName' => $row['tier'] > 0 ? Crest::TIER_NAMES[$row['tier']] : null, 'count' => FeatText::counted($kind, $row['value']),
            ], $feat['top']),
            'recent' => array_map(fn (array $row): array => [
                'name' => Format::characterName($row['character']), 'href' => '/c/'.rawurlencode($row['character']), 'tier' => $row['tier'],
                'tierName' => Crest::TIER_NAMES[$row['tier']], 'at' => $row['at'], 'day' => gmdate('j M Y', (int) strtotime($row['at'])),
            ], $feat['recent']),
            'others' => array_map(fn (array $other): array => [
                'key' => $other['key'], 'name' => $other['name'], 'glyph' => $other['glyph'], 'family' => $other['family'], 'tier' => 0, 'progress' => 0,
                'share' => FeatsPage::percent($other['shares'][0] ?? null), 'href' => '/feats/'.$other['key'],
            ], $answer['collection']),
            'mine' => $mine === null ? null : ['name' => Format::characterName($mine['character']), 'count' => FeatText::counted($kind, $mine['value'])],
        ];
    }

    /** @param  array<string, mixed>  $feat */
    private static function line(array $feat): string
    {
        $spells = $feat['spells'] ?? [];
        if (is_array($spells) && $spells !== []) {
            return 'Counts casts of '.str_replace('*', '…', implode(', ', $spells)).'.';
        }

        return 'Counts '.$feat['many'].'.';
    }
}
