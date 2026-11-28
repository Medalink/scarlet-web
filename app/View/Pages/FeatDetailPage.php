<?php

namespace App\View\Pages;

use App\Support\Feat;
use App\Support\FeatText;
use App\View\Dates;
use App\View\Format;
use App\View\Sc\Crest;

/** The data of /feats/{key} from the answer of App\Actions\Feats\ShowFeatPage. */
final class FeatDetailPage
{
    /**
     * @param  array{feat: array<string, mixed>, collection: list<array<string, mixed>>, mine: array{character: string, value: int|float, tier: int}|null, held: array<string, array{tier: int, progress: float}>, account: array<string, mixed>|null}  $answer
     * @return array<string, mixed>
     */
    public static function data(array $answer): array
    {
        $feat = $answer['feat'];
        $mine = $answer['mine'];
        $kind = Feat::from($feat['key']);
        $you = $mine === null ? null : FeatsPage::first($mine['character']).', '.FeatText::readableCounted($kind, $mine['value']);

        return [
            'key' => $feat['key'], 'name' => $feat['name'], 'glyph' => $feat['glyph'], 'family' => $feat['family'], 'collection' => $feat['collection'],
            'tier' => $mine['tier'] ?? 0,
            'line' => self::line($feat),
            'ladder' => FeatLadder::rows($kind, $feat['thresholds'], $feat['shares'], $mine['tier'] ?? null, $you),
            'shares' => $feat['shares'] !== null,
            'unit' => ucfirst($feat['many']),
            'top' => array_map(fn (array $row): array => [
                'name' => Format::characterName($row['character']), 'href' => '/c/'.rawurlencode($row['character']), 'tier' => $row['tier'],
                'tierName' => $row['tier'] > 0 ? Crest::TIER_NAMES[$row['tier']] : null, 'count' => FeatText::readable($kind, $row['value']), 'value' => (float) $row['value'],
            ], $feat['top']),
            'recent' => array_map(fn (array $row): array => [
                'name' => Format::characterName($row['character']), 'href' => '/c/'.rawurlencode($row['character']), 'tier' => $row['tier'],
                'tierName' => Crest::TIER_NAMES[$row['tier']], 'when' => Dates::moment($row['at']),
            ], $feat['recent']),
            'others' => array_map(fn (array $other): array => [
                'key' => $other['key'], 'name' => $other['name'], 'glyph' => $other['glyph'], 'family' => $other['family'], 'tier' => $answer['held'][$other['key']]['tier'] ?? 0, 'progress' => $answer['held'][$other['key']]['progress'] ?? 0,
                'share' => FeatsPage::percent($other['shares'][0] ?? null), 'href' => '/feats/'.$other['key'],
            ], $answer['collection']),
            'mine' => $mine === null ? null : ['name' => Format::characterName($mine['character']), 'count' => FeatText::readableCounted($kind, $mine['value'])],
            'account' => self::account($feat, $kind, $answer['account']),
        ];
    }

    /**
     * @param  array<string, mixed>  $feat
     * @param  array<string, mixed>|null  $card  the account's card of the feat, for a signed-in owner
     * @return array{tier: int, ladder: list<array<string, int|string>>, card: array<string, mixed>|null}|null null for a feat with no account crest
     */
    private static function account(array $feat, Feat $kind, ?array $card): ?array
    {
        if ($feat['account_thresholds'] === null) {
            return null;
        }
        $tier = $card['tier'] ?? 0;
        $words = FeatText::readableCard($kind, $card ?? []);

        return [
            'tier' => $tier,
            'ladder' => FeatLadder::rows($kind, $feat['account_thresholds'], null, $card === null ? null : $tier, $card === null ? null : 'Account, '.$words['counted']),
            'card' => $card === null ? null : [
                'key' => $card['key'], 'name' => $card['name'], 'glyph' => $card['icon'], 'scope' => 'account', 'tier' => $tier,
                'progress' => $card['progress'], 'count' => $words['counted'], 'to' => $words['to_next'],
            ],
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
